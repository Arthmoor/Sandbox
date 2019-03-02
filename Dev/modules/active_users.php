<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) https://kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2019
 * Roger Libiez [Samson] https://www.afkmods.com/
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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

function do_active($mod, $module)
{
	$idlers = array();
	$expire = $mod->time - 1800;

	$active = $mod->db->dbquery( 'SELECT * FROM %pactive' );
	while( $user = $mod->db->assoc($active) )
	{
		if( $user['active_time'] < $expire )
			$idlers[] = $user['active_ip'];
	}
	if( $idlers ) {
		$mod->db->dbquery( 'DELETE FROM %pactive WHERE active_time < %d', $expire );
	}

	$action = 'Lurking in the shadows';
	switch( $module ) {
		case 'blog':
			if( isset($mod->get['p']))
				$action = 'Viewing blog entry: ' . $mod->get['p'];
			else if( isset($mod->get['time']))
				$action = 'Viewing the blog archives';
			else
				$action = 'Viewing the home page';
			break;
		case 'search':		$action = 'Searching the blog';		break;
		case 'gallery':		$action = 'Viewing the gallery';	break;
		case 'downloads':	$action = 'Viewing the downloads';	break;
		case 'cat':		$action = 'Viewing blog categories';	break;
		case 'contact':		$action = 'Using the contact form';	break;
		case 'page':
			if( isset($mod->get['p']))
				$action = 'Viewing page: ' . $mod->get['p'];
			else
				$action = 'Viewing a custom page';
			break;
		case 'rss':		$action = 'Viewing the rss feed';	break;
		default:		$action = 'Lurking in the shadows';
	}

	$ip = $mod->ip;
	if( $mod->user['user_level'] > USER_GUEST )
		$ip = $mod->user['user_name'];

	$mod->db->dbquery( "REPLACE INTO %pactive (active_action, active_time, active_ip, active_user_agent) 
				VALUES ( '%s', %d, '%s', '%s' )", $action, $mod->time, $ip, $mod->agent );
}
?>