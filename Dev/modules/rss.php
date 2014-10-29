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

class rss extends module
{
	function execute()
	{
		$this->nohtml = true;
		header( 'Content-type: text/xml', 1 );

		if ( isset($this->get['type']) ) {
			switch( $this->get['type'] )
			{
				case 'comments':		return $this->comment_rss();
				case 'gallery':			return $this->gallery_rss();
				case 'downloads':		return $this->downloads_rss();
				case 'posts':			return $this->post_rss();
			}
		} else { // Default to posts
			return $this->post_rss();
		}
	}

	function remove_breaks($in)
	{
		return preg_replace( "/(<br\s*\/?>\s*)+/", ' ', $in );
	}

	function gallery_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

		$where = null;
		if( $this->user['user_level'] > USER_GUEST )
			$where = "(i.photo_flags & " . POST_PUBLISHED . ")";
		else
			$where = "((i.photo_flags & " . POST_PUBLISHED . ") AND !(i.photo_flags & " . POST_MEMBERSONLY . "))";

  		$result = $this->db->dbquery( 'SELECT i.*, u.user_name, f.folder_hidden, f.folder_user
		   FROM %pphotogallery i
		   LEFT JOIN %pusers u ON u.user_id=i.photo_user
		   LEFT JOIN %pphotofolders f ON f.folder_id=i.photo_folder
		   WHERE '. $where . ' ORDER BY photo_date DESC LIMIT %d', $this->settings['rss_items'] );

		while ( $entry = $this->db->assoc($result) )
		{
			if( $entry['folder_hidden'] ) {
				if( $this->user['user_level'] == USER_GUEST )
					continue;

				if( $this->user['user_id'] != $entry['folder_user'] && $this->user['user_level'] < USER_ADMIN )
					continue;
			}

			$xtpl->assign( 'item_title', htmlspecialchars($entry['photo_caption']) );

			if( $this->settings['friendly_urls'] )
				$link = 'gallery/' . $this->clean_url( $entry['photo_caption'] ) . "-{$entry['photo_id']}.html";
			else
				$link = "index.php?a=gallery&amp;p={$entry['photo_id']}";
			$xtpl->assign( 'item_link', htmlspecialchars($this->settings['site_address']) . $link );

			$size = ceil( $entry['photo_size'] / 1024 );
			$xtpl->assign( 'item_desc',  htmlspecialchars($entry['photo_summary'] . " " . $entry['photo_width'] . " x " . $entry['photo_height'] . " " . $size . "KB" ));

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'item_date', date( 'D, j M Y H:i:s T', $entry['photo_date'] ) );
			$xtpl->assign( 'item_category', 'Image Gallery' );
			$xtpl->assign( 'item_author', htmlspecialchars('nobody@example.com (' . $entry['user_name'] . ')') );

			$xtpl->parse( 'RSSFeed.Item' );
		}

		$xtpl->assign( 'rss_title', htmlspecialchars( $this->settings['site_name'] . ' :: Image Gallery' ) );
		$xtpl->assign( 'rss_link', htmlspecialchars( $this->settings['site_address'] ) );
		$xtpl->assign( 'rss_desc', htmlspecialchars( $this->settings['rss_description'] ) );
		$xtpl->assign( 'rss_refresh', intval($this->settings['rss_refresh']) );

		$rss_copyright = $this->remove_breaks( $this->settings['copyright_terms'] );
		$rss_copyright = str_replace( '{year}', date( 'Y', $this->time ), $rss_copyright );
		$xtpl->assign( 'rss_copyright', htmlspecialchars($rss_copyright) );

		if( isset($this->settings['rss_image_url']) && !empty($this->settings['rss_image_url']) ) {
			$xtpl->assign( 'rss_image_url', htmlspecialchars($this->settings['rss_image_url']) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}

	function downloads_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

		$where = null;
		if( $this->user['user_level'] > USER_GUEST )
			$where = "(f.file_flags & " . POST_PUBLISHED . ")";
		else
			$where = "((f.file_flags & " . POST_PUBLISHED . ") AND !(f.file_flags & " . POST_MEMBERSONLY . "))";

  		$result = $this->db->dbquery( 'SELECT f.*, u.user_name, d.folder_hidden, d.folder_user
		   FROM %pfilelist f
		   LEFT JOIN %pusers u ON u.user_id=f.file_user
		   LEFT JOIN %pfilefolders d ON d.folder_id=f.file_folder
		   WHERE ' . $where . ' ORDER BY file_date DESC LIMIT %d', $this->settings['rss_items'] );

		$items = '';
		while ( $entry = $this->db->assoc($result) )
		{
			if( $entry['folder_hidden'] ) {
				if( $this->user['user_level'] == USER_GUEST )
					continue;

				if( $this->user['user_id'] != $entry['folder_user'] && $this->user['user_level'] < USER_ADMIN )
					continue;
			}

			$xtpl->assign( 'item_title', htmlspecialchars($entry['file_name']) );

			if( $this->settings['friendly_urls'] )
				$link = 'downloads/' . $this->clean_url( $entry['file_name'] ) . "-{$entry['file_id']}.html";
			else
				$link = "index.php?a=downloads&amp;p={$entry['file_id']}";
			$xtpl->assign( 'item_link', htmlspecialchars($this->settings['site_address']) . $link );

			$xtpl->assign( 'item_desc', htmlspecialchars(substr($entry['file_description'],0,200)) );

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'item_date', date( 'D, j M Y H:i:s T', $entry['file_date'] ) );
			$xtpl->assign( 'item_category', 'Downloads' );
			$xtpl->assign( 'item_author', htmlspecialchars('nobody@example.com (' . $entry['user_name'] . ')') );

			$xtpl->parse( 'RSSFeed.Item' );
		}

		$xtpl->assign( 'rss_title', htmlspecialchars($this->settings['site_name'] . ' :: Downloads') );
		$xtpl->assign( 'rss_link', htmlspecialchars($this->settings['site_address']) );
		$xtpl->assign( 'rss_desc', htmlspecialchars($this->settings['rss_description']) );
		$xtpl->assign( 'rss_refresh', intval($this->settings['rss_refresh']) );

		$rss_copyright = $this->remove_breaks( $this->settings['copyright_terms'] );
		$rss_copyright = str_replace( '{year}', date( 'Y', $this->time ), $rss_copyright );
		$xtpl->assign( 'rss_copyright', htmlspecialchars($rss_copyright) );

		if( isset($this->settings['rss_image_url']) && !empty($this->settings['rss_image_url']) ) {
			$xtpl->assign( 'rss_image_url', htmlspecialchars($this->settings['rss_image_url']) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}

	function comment_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

		// Wow. Could this get any uglier, please?
		$where = null;
		if( $this->user['user_level'] > USER_GUEST )
			$where = "(c.comment_type = " . COMMENT_BLOG . " AND (p.post_flags & " . POST_PUBLISHED . ")) OR (c.comment_type = " . COMMENT_GALLERY . " AND (i.photo_flags & " . POST_PUBLISHED . ")) OR (c.comment_type = " . COMMENT_FILE . " AND (f.file_flags & " . POST_PUBLISHED . "))";
		else
			$where = "(c.comment_type = " . COMMENT_BLOG . " AND (p.post_flags & " . POST_PUBLISHED . ") AND !(p.post_flags & " . POST_MEMBERSONLY . ")) OR (c.comment_type = " . COMMENT_GALLERY . " AND (i.photo_flags & " . POST_PUBLISHED . ") AND !(i.photo_flags & " . POST_MEMBERSONLY . ")) OR (c.comment_type = " . COMMENT_FILE . " AND (f.file_flags & " . POST_PUBLISHED . ") AND !(f.file_flags & " . POST_MEMBERSONLY . "))";

  		$result = $this->db->dbquery(
			'SELECT c.comment_id, c.comment_date, c.comment_type, comment_message, p.post_id, p.post_subject, i.photo_id, i.photo_caption, f.file_id, f.file_name, u.user_name
			FROM %pblogcomments c
			LEFT JOIN %pblogposts p ON p.post_id=c.comment_post
			LEFT JOIN %pphotogallery i ON i.photo_id=c.comment_post
			LEFT JOIN %pfilelist f ON f.file_id=c.comment_post
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE ' . $where . ' ORDER BY c.comment_date DESC LIMIT %d', $this->settings['rss_items'] );

		while ( $entry = $this->db->assoc($result) )
		{
			$item_title = '';
			$link = '';
			if( isset($entry['post_subject']) && $entry['comment_type'] == COMMENT_BLOG ) {
				if( $this->settings['friendly_urls'] )
					$link = $this->clean_url( $entry['post_subject'] ) . "-{$entry['post_id']}.html&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";
				else
					$link = "index.php?a=blog&amp;p={$entry['post_id']}&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";

				$item_title = $entry['post_subject'];
			} else if( isset($entry['photo_caption']) && $entry['comment_type'] == COMMENT_GALLERY ) {
				if( $this->settings['friendly_urls'] )
					$link = 'gallery/' . $this->clean_url( $entry['photo_caption'] ) . "-{$entry['photo_id']}.html&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";
				else
					$link = "index.php?a=gallery&amp;p={$entry['photo_id']}&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";

				$item_title = $entry['photo_caption'];
			} else if( isset($entry['file_name']) && $entry['comment_type'] == COMMENT_FILE ) {
				if( $this->settings['friendly_urls'] )
					$link = 'downloads/' . $this->clean_url( $entry['file_name'] ) . "-{$entry['file_id']}.html&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";
				else
					$link = "index.php?a=downloads&amp;p={$entry['file_id']}&amp;c={$entry['comment_id']}#comment-{$entry['comment_id']}";

				$item_title = $entry['file_name'];
			}
			$xtpl->assign( 'item_title', htmlspecialchars($item_title) );
			$xtpl->assign( 'item_link', htmlspecialchars($this->settings['site_address']) . $link );
			$xtpl->assign( 'item_desc', htmlspecialchars(substr($entry['comment_message'],0,200)) );

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'item_date', date( 'D, j M Y H:i:s T', $entry['comment_date'] ) );
			$xtpl->assign( 'item_category', 'Comments' );
			$xtpl->assign( 'item_author', htmlspecialchars('nobody@example.com (' . $entry['user_name'] . ')') );

			$xtpl->parse( 'RSSFeed.Item' );
		}

		$xtpl->assign( 'rss_title', htmlspecialchars($this->settings['site_name'] . ' :: Comments') );
		$xtpl->assign( 'rss_link', htmlspecialchars($this->settings['site_address']) );
		$xtpl->assign( 'rss_desc', htmlspecialchars($this->settings['rss_description']) );
		$xtpl->assign( 'rss_refresh', intval($this->settings['rss_refresh']) );

		$rss_copyright = $this->remove_breaks( $this->settings['copyright_terms'] );
		$rss_copyright = str_replace( '{year}', date( 'Y', $this->time ), $rss_copyright );
		$xtpl->assign( 'rss_copyright', htmlspecialchars($rss_copyright) );

		if( isset($this->settings['rss_image_url']) && !empty($this->settings['rss_image_url']) ) {
			$xtpl->assign( 'rss_image_url', htmlspecialchars($this->settings['rss_image_url']) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}

	function post_rss()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/rss.xtpl' );

  		$cat = null;
  		if ( isset($this->get['cat']) )
			$cat = intval($this->get['cat']);

		$where = null;
		if( $this->user['user_level'] > USER_GUEST ) {
			$where = $cat ? ( "WHERE pc.pc_post=p.post_id AND pc.pc_id=$cat AND post_flags & " . POST_PUBLISHED )
				: ( "WHERE post_flags & " . POST_PUBLISHED );
		} else {
			$where = $cat ? ( "WHERE pc.pc_post=p.post_id AND pc.pc_id=$cat AND post_flags & " . POST_PUBLISHED . " AND !(post_flags & " . POST_MEMBERSONLY . ")" )
				: ( "WHERE post_flags & " . POST_PUBLISHED . " AND !(post_flags & " . POST_MEMBERSONLY . ")" );
		}
		
		$result = $this->db->dbquery(
			'SELECT p.post_id, p.post_subject, p.post_summary, p.post_date, p.post_user, u.user_name, u.user_id' . ($cat ? ', pc.pc_cat' : null) . '
			   FROM %pblogposts p' . ($cat ? ', %ppostcats pc' : null) . '
			   LEFT JOIN %pusers u ON u.user_id=p.post_user ' .
			   $where . ' ORDER BY p.post_date DESC LIMIT %d', $this->settings['rss_items'] );

		while ( $entry = $this->db->assoc($result) )
		{
			$cats = array();
			$catresult = $this->db->dbquery( 'SELECT c.cat_id, c.cat_name
				FROM %ppostcats pc
				LEFT JOIN %pblogcats c ON c.cat_id=pc.pc_cat
				WHERE pc.pc_post=%d', $entry['post_id'] );

			while( $cat = $this->db->assoc($catresult) )
				$cats[] = htmlspecialchars($cat['cat_name']);

			$xtpl->assign( 'item_title', htmlspecialchars($entry['post_subject']) );

			if( $this->settings['friendly_urls'] )
				$link = $this->clean_url( $entry['post_subject'] ) . "-{$entry['post_id']}.html";
			else
				$link = "index.php?a=blog&amp;p={$entry['post_id']}";
			$xtpl->assign( 'item_link', htmlspecialchars($this->settings['site_address']) . $link );

			$xtpl->assign( 'item_desc', htmlspecialchars($entry['post_summary']) );

			// ISO822 format is standard for XML feeds
			$xtpl->assign( 'item_date', date( 'D, j M Y H:i:s T', $entry['post_date'] ) );
			$item_category = 'Uncategorized';
			if( count($cats) > 0 && !empty($cats[0]) )
				$item_category = implode($cats, ', ');
			$xtpl->assign( 'item_category', htmlspecialchars($item_category) );

			$xtpl->assign( 'item_author', htmlspecialchars('nobody@example.com (' . $entry['user_name'] . ')') );

			$xtpl->parse( 'RSSFeed.Item' );
  		}

		$xtpl->assign( 'rss_title', htmlspecialchars($this->settings['site_name'] . ' :: Blog') );
		$xtpl->assign( 'rss_link', htmlspecialchars($this->settings['site_address']) );
		$xtpl->assign( 'rss_desc', htmlspecialchars($this->settings['rss_description']) );
		$xtpl->assign( 'rss_refresh', intval($this->settings['rss_refresh']) );

		$rss_copyright = $this->remove_breaks( $this->settings['copyright_terms'] );
		$rss_copyright = str_replace( '{year}', date( 'Y', $this->time ), $rss_copyright );
		$xtpl->assign( 'rss_copyright', htmlspecialchars($rss_copyright) );

		if( isset($this->settings['rss_image_url']) && !empty($this->settings['rss_image_url']) ) {
			$xtpl->assign( 'rss_image_url', htmlspecialchars($this->settings['rss_image_url']) );
			$xtpl->parse( 'RSSFeed.Image' );
		}

		$xtpl->parse( 'RSSFeed' );
		return $xtpl->text( 'RSSFeed' );
	}
}
?>