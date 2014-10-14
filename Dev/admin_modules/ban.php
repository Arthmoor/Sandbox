<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) http://www.kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2011
 * Roger Libiez [Samson] http://www.iguanadons.net
 *
 * This software is provided 'as-is', without any express or implied warranty.
 * In no event will the authors be held liable for any damages arising from the
 * use of this software.
 *
 * Permission is granted to anyone to use this software for any purpose,
 * including commercial applications, and to alter it and redistribute it freely,
 * subject to the following restrictions:
 *
 * 1. The origin of this software must not be misrepresented; you must not claim
 * that you wrote the original software. If you use this software in a product,
 * an acknowledgment in the product documentation is required.
 *
 * 2. Altered source versions must be plainly marked as such, and must not be
 * misrepresented as being the original software.
 *
 * 3. This notice may not be removed or altered from any source distribution.
 *
 * 4. You must make an effort to notify the author (Sam O'Connor) at the email
 * address sandbox@kiasyn.com if you plan on publicly distributing a derivative
 * of this software, whether by email, download or a form of disk/disc.
 *
 * Notifying Roger Libiez is not required but would still be appreciated :)
 */

if ( !defined('SANDBOX') || !defined('SANDBOX_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class ban extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		$this->title( 'Banned IPs' );

		if( !isset($this->post['submit']) )
		{
			$ips = null;
			if( isset($this->settings['banned_ips']) )
				$ips = implode("\n", $this->settings['banned_ips']);

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/ban.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'ip_addresses', $ips );

			$xtpl->parse( 'Bans' );
			return $xtpl->text( 'Bans' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$banned_ips = trim($this->post['banned_ips']);
		if ( $banned_ips )
			$banned_ips = explode("\n", $banned_ips);
		else
			$banned_ips = array();
		$this->settings['banned_ips'] = $banned_ips;
		$this->save_settings();
		return $this->message( 'Banned IPs', 'Bans updated.', 'Continue', 'admin.php?a=ban' );
	}
}
?>