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

class cat extends module
{
	function execute()
	{
		if ( isset($this->get['cat']) ) {
			if( $this->settings['friendly_urls'] )
				return $this->view_cat( $this->get['cat'] );
			else
				return $this->view_cat( intval($this->get['cat']) );
		}
	}

	function view_cat( $cid )
	{
		if( $this->settings['friendly_urls'] ) {
			$cid = str_replace( '-', ' ', $cid );
			$cat = $this->db->quick_query( "SELECT * FROM %pblogcats WHERE cat_name='%s'", $cid );
		} else {
			$cat = $this->db->quick_query( 'SELECT * FROM %pblogcats WHERE cat_id=%d', $cid );
		}

		if ( !$cat )
			return $this->error( 'The blog category you are looking for does not exist. It may have been deleted or the URL is incorrect.', 404 );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/cat.xtpl' );

		$this->title( 'Category: ' . $cat['cat_name'] );
		$this->meta_description( $cat['cat_description'] );

		$xtpl->assign( 'cat_name', htmlspecialchars($cat['cat_name']) );
		$xtpl->assign( 'cat_desc', $this->format( $cat['cat_description'], POST_BBCODE ) );

		$Posts = null;

		$where = null;
		if( $this->user['user_level'] > USER_VALIDATING ) {
			$where = "pc.pc_cat={$cat['cat_id']} AND post_flags & " . POST_PUBLISHED;
		} else {
			$where = "pc.pc_cat={$cat['cat_id']} AND post_flags & " . POST_PUBLISHED . " AND !(post_flags & " . POST_MEMBERSONLY . ")";
		}

		$result = $this->db->dbquery( 'SELECT p.post_id, p.post_subject, p.post_date, u.user_name
			   FROM %ppostcats pc
		  LEFT JOIN %pblogposts p ON p.post_id = pc.pc_post
		  LEFT JOIN %pusers u ON u.user_id=p.post_user
		      WHERE ' . $where . ' ORDER BY p.post_date DESC' );

		while ( $post = $this->db->assoc($result) )
		{
			$xtpl->assign( 'subject', htmlspecialchars($post['post_subject']) );
			$xtpl->assign( 'author', htmlspecialchars($post['user_name']) );
			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $post['post_date'] ) );

			if( $this->settings['friendly_urls'] )
				$post_url = $this->settings['site_address'] . $this->clean_url( $post['post_subject'] ) . "-{$post['post_id']}.html";
			else
				$post_url = "{$this->settings['site_address']}index.php?a=blog&amp;p={$post['post_id']}";
			$xtpl->assign( 'post_url', $post_url );

			$xtpl->parse( 'Category.Post' );
		}

		$SideBar = new sidebar($this);
		$xtpl->assign( 'sidebar', $SideBar->build_sidebar() );

		$xtpl->parse( 'Category' );
		return $xtpl->text( 'Category' );
	}
}
?>