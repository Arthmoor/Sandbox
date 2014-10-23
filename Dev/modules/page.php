<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) http://www.kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2015
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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once './lib/sidebar.php';

class page extends module
{
	function execute()
	{
		if ( !isset($this->get['p']) )
			return $this->error( 'The page you are looking for does not exist. It may have been deleted or the URL is incorrect.', 404 );

		$p = intval($this->get['p']);
		return $this->display_page($p);
	}

	function display_page( $p )
	{
		$page = $this->db->quick_query( 'SELECT * FROM %ppages WHERE page_id=%d', $p );

		if ( !$page )
			return $this->error( 'The page you are looking for does not exist. It may have been deleted or the URL is incorrect.', 404 );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/page.xtpl' );

		$this->title( $page['page_title'] );
		$this->meta_description( $page['page_meta'] );

		$sidebar = null;

		$content = $this->format( $page['page_content'], $page['page_flags'] );

		if( ($page['page_flags'] & POST_HTML) && ($page['page_flags'] & POST_BBCODE) )
			$content = html_entity_decode($content, ENT_COMPAT, 'UTF-8');

		$xtpl->assign( 'content', $content );

		if ( $page['page_flags'] & POST_SIDEBAR ) {
			$SideBar = new sidebar($this);
			$sidebar = $SideBar->build_sidebar();

			$xtpl->parse( 'Page.HasSidebar' );
		} else {
			$xtpl->parse( 'Page.NoSidebar' );
		}

		$xtpl->assign( 'sidebar', $sidebar );
		$xtpl->parse( 'Page' );
		return $xtpl->text( 'Page' );
	}
}
?>