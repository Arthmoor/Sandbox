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

require_once './lib/comments.php';

class gallery extends module
{
	var $folder_array; // Used to generate folder trees

	function execute()
	{
		$this->comments = new comments($this);

		if ( isset($this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				// case 'create':		return $this->create_image();
				// case 'edit':		return $this->edit_image();
				// case 'del':		return $this->delete_image();
				case 'edit_comment':	return $this->comments->edit_comment();
				case 'del_comment':	return $this->comments->delete_comment();
			}
			return $this->error( 'Invalid option passed.' );
		}

		if ( isset($this->get['p']) )
			return $this->view_image(intval($this->get['p']));

		static $folder_array = false;
		$this->folder_array = &$folder_array;

		if ( isset($this->get['recent']) )
			return $this->display_recent_thumbs();

		$this->title( 'Image Gallery' );
		$this->meta_description( $this->settings['site_name'] . ' Image Gallery.' );

		$count = 0; $this_folder = null;

		if( $this->settings['friendly_urls'] ) {
			$fld = null;
			$this_folder = null;

			if( isset($this->get['f']) ) {
				$f = str_replace( '-', ' ', $this->get['f'] );
				$fld = $f;
			}

			$z = 0;
			if( isset($this->get['z']) ) {
				$z = intval($this->get['z']);
			}

			if( $fld )
				$this_folder = $this->db->quick_query( "SELECT * FROM %pphotofolders WHERE folder_name='%s' AND folder_id=%d", $fld, $z );
			if( !$this_folder )
				return $this->error( 'The folder you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

			$f = $this_folder['folder_id'];
		} else {
			$f = isset($this->get['f']) ? intval($this->get['f']) : 0;

			$this_folder = $this->db->quick_query( 'SELECT * FROM %pphotofolders WHERE folder_id=%d', $f );
		}

		if( $this_folder['folder_hidden'] && $this->user['user_id'] != $this_folder['folder_user'] && $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'The folder you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/gallery_folder.xtpl' );

		$xtpl->assign( 'imgsrc', $this->settings['site_address'] . 'skins/' . $this->skin );

		$tree = $this->build_folder_list($f);
		$xtpl->assign( 'tree', $tree );

		$folders = $this->folder_array;
		foreach( $folders as $folder )
		{
			if( $folder['folder_parent'] != $f )
				continue;

			$id = $folder['folder_id'];

			// Don't display a folder link for root
			if( $id == 0 )
				continue;

			if( $folder['folder_hidden'] && $this->user['user_id'] != $folder['folder_user'] && $this->user['user_level'] < USER_ADMIN )
				continue;

			$xtpl->assign( 'name', htmlspecialchars( $folder['folder_name']) );

			if( $this->settings['friendly_urls'] )
				$parent = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $folder['folder_name'] ) . '-' . $id . '/';
			else
				$parent = "{$this->settings['site_address']}index.php?a=gallery&amp;f={$folder['folder_id']}";

			$xtpl->assign( 'parent', $parent );

			$xtpl->parse( 'GalleryFolder.Link' );
		}

		$list = $this->build_file_list( $f );
		foreach( $list as $item )
		{
			if( !isset($item['name']) )
				continue;
			$item_name = $item['name'];
			if( strlen( $item_name ) > 23 )
				$item_name = substr( $item_name, 0, 20 ) . '...';
			$xtpl->assign( 'item_name', htmlspecialchars($item_name) );

			$src = getimagesize( '.' . $item['src'] );
			$xtpl->assign( 'imgWH', $src[3] );
			$xtpl->assign( 'item_src', $this->settings['site_address'] . $item['src'] );
			$xtpl->assign( 'item_type', $item['type'] );
			$xtpl->assign( 'item_dimensions', $item['dimensions'] );
			$xtpl->assign( 'item_size', $item['size'] );
			$xtpl->assign( 'item_link', $item['link'] );
			$xtpl->assign( 'item_num', $item['num'] );

			$xtpl->parse( 'GalleryFolder.Thumbnail' );
		}

		// Some slight SEO help for the folder display.
		$folder_summary = null;
		if( $this_folder['folder_id'] != 0 ) {
			$this->title( $this_folder['folder_name'] );
			$this->meta_description( $this_folder['folder_summary'] );
			$folder_summary = htmlspecialchars($this_folder['folder_summary']);
		}

		$xtpl->assign( 'folder_summary', $folder_summary );
		$xtpl->assign( 'folder_name', htmlspecialchars($this_folder['folder_name']) );

		$xtpl->parse( 'GalleryFolder' );
		return $xtpl->text( 'GalleryFolder' );
	}

	function display_recent_thumbs()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/gallery_folder.xtpl' );

		// Some slight SEO help for the folder display.
		$this->title( 'Recent Images' );
		$this->meta_description( 'The 50 most recent images posted at ' . $this->settings['site_name'] . '.' );

		$xtpl->assign( 'folder_name', 'Recent Images' );
		$xtpl->assign( 'folder_summary', 'Recent Images' );
		$xtpl->assign( 'imgsrc', $this->settings['site_address'] . $this->skin );
		$xtpl->assign( 'tree', $this->build_folder_list(0) );

		$folders = $this->folder_array;
		foreach( $folders as $folder )
		{
			if( $folder['folder_parent'] != 0 )
				continue;

			$id = $folder['folder_id'];

			// Don't display a folder link for root
			if( $id == 0 )
				continue;

			if( $folder['folder_hidden'] && $this->user['user_id'] != $folder['folder_user'] && $this->user['user_level'] < USER_ADMIN )
				continue;

			$xtpl->assign( 'name', htmlspecialchars($folder['folder_name']) );

			if( $this->settings['friendly_urls'] )
				$parent = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $folder['folder_name'] ) . '-' . $id . '/';
			else
				$parent = "{$this->settings['site_address']}index.php?a=gallery&amp;f={$folder['folder_id']}";

			$xtpl->assign( 'parent', $parent );

			$xtpl->parse( 'GalleryFolder.Link' );
		}

		// Instruct the function to build the last 50 list instead.
		$list = $this->build_file_list( 0, true );
		foreach( $list as $item )
		{
			if( !isset($item['name']) )
				continue;
			$item_name = $item['name'];
			if( strlen( $item_name ) > 23 )
				$item_name = substr( $item_name, 0, 20 ) . '...';
			$xtpl->assign( 'item_name', htmlspecialchars($item_name) );

			$src = getimagesize( '.' . $item['src'] );
			$xtpl->assign( 'imgWH', $src[3] );
			$xtpl->assign( 'item_src', $this->settings['site_address'] . $item['src'] );
			$xtpl->assign( 'item_type', $item['type'] );
			$xtpl->assign( 'item_dimensions', $item['dimensions'] );
			$xtpl->assign( 'item_size', $item['size'] );
			$xtpl->assign( 'item_link', $item['link'] );
			$xtpl->assign( 'item_num', $item['num'] );

			$xtpl->parse( 'GalleryFolder.Thumbnail' );
		}

		$xtpl->parse( 'GalleryFolder' );
		return $xtpl->text( 'GalleryFolder' );
	}

	function view_image( $p )
	{
		$photo = $this->db->quick_query( 'SELECT p.*, f.folder_hidden, f.folder_name, f.folder_id, f.folder_user, u.user_name
			FROM %pphotogallery p
			LEFT JOIN %pphotofolders f ON f.folder_id=p.photo_folder
			LEFT JOIN %pusers u ON u.user_id=p.photo_user
			WHERE p.photo_id=%d', $p );

		if( isset($this->get['title']) ) {
			if( $this->clean_url( $photo['photo_caption'] ) != $this->get['title'] )
				$photo = null;
		}

		if( !($photo['photo_flags'] & POST_PUBLISHED) || ( ($photo['photo_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] == USER_GUEST ) )
			$photo = null;
		if( !$photo || ($photo['folder_hidden'] && $this->user['user_id'] != $photo['folder_user'] && $this->user['user_level'] < USER_ADMIN) )
			return $this->error( 'The image you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		if( isset($this->post['submit']) || isset($this->post['preview']) )
		{
			if( $this->closed_content( $photo, COMMENT_GALLERY ) ) {
				return $this->error( 'Sorry, this image entry is closed for commenting.', 403 );
			}

			$result = $this->comments->post_comment( COMMENT_GALLERY, $photo['photo_caption'], $p );
			if( is_string($result) )
				return $result;

			if( isset($this->post['request_uri']) )
				header( 'Location: ' . $this->post['request_uri'] );
 
			if( $this->settings['friendly_urls'] )
				$link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $photo['photo_caption'] ) . "-$p.html&c=$result#comment-$result";
			else
				$link = "{$this->settings['site_address']}index.php?a=gallery&p=$p&c=$result#comment-$result";
			header( 'Location: ' . $link );
		}

		if( isset( $this->get['num'] ) ) {
			$num = intval( $this->get['num'] );
		} else {
			$num = $this->settings['blog_commentsperpage'];
		}
		if( $num > $this->settings['blog_commentsperpage'] )
			$num = $this->settings['blog_commentsperpage'];
		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		if( isset($this->get['c'])) {
			$cmt = intval($this->get['c']);

			// We need to find what page the requested comment is on
			$coms = $this->db->quick_query( "SELECT COUNT(comment_id) count FROM %pblogcomments WHERE comment_post=%d AND comment_id < %d AND comment_type=%d", $p, $cmt, COMMENT_GALLERY );

			if ($coms)
				$count = $coms['count'] + 1;
			else $count = 0;

			$min = 0; // Start at the first page regardless
			while ($count > ($min + $num)) {
				$min += $num;
			}
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/gallery_image.xtpl' );

		$xtpl->assign( 'count', $photo['photo_comment_count'] );

		$older = null;
		$newer = null;

		if( $this->settings['friendly_urls'] )
			$folder_link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $photo['folder_name'] ) . '-' . $photo['folder_id'] . '/';
		else
			$folder_link = "{$this->settings['site_address']}index.php?a=gallery&amp;f={$photo['folder_id']}";
		$xtpl->assign( 'folder_link', $folder_link );

		$next_image = $this->db->quick_query( 'SELECT photo_id, photo_caption FROM %pphotogallery
			WHERE photo_date < %d AND photo_folder = %d
			ORDER BY photo_date DESC LIMIT 1', $photo['photo_date'], $photo['photo_folder'] );

		if( $next_image ) {
			if( $this->settings['friendly_urls'] )
				$new_cap_link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $next_image['photo_caption'] ) . "-{$next_image['photo_id']}.html";
			else
				$new_cap_link = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$next_image['photo_id']}";
			$new_cap = htmlspecialchars($next_image['photo_caption']);
			$newer = "<a href=\"$new_cap_link\">$new_cap</a> &raquo;";

			$xtpl->assign( 'newer', $newer );
		}

		$prev_image = $this->db->quick_query( 'SELECT photo_id, photo_caption FROM %pphotogallery
			WHERE photo_date > %d AND photo_folder = %d
			ORDER BY photo_date ASC LIMIT 1', $photo['photo_date'], $photo['photo_folder'] );

		if( $prev_image ) {
			if( $this->settings['friendly_urls'] )
				$new_cap_link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $prev_image['photo_caption'] ) . "-{$prev_image['photo_id']}.html";
			else
				$new_cap_link = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$prev_image['photo_id']}";
			$new_cap = htmlspecialchars($prev_image['photo_caption']);
			$older = "&laquo; <a href=\"$new_cap_link\">$new_cap</a>";
	
			$xtpl->assign( 'older', $older );
		}

		if( $newer || $older ) {
			$xtpl->assign( 'folder_name', htmlspecialchars($photo['folder_name']) );

			$xtpl->parse( 'GalleryImage.NavLinks' );
		}

		$this->title( 'Image Gallery: ' . $photo['photo_caption'] );
		$this->meta_description( $photo['photo_summary'] );

		$caption = htmlspecialchars($photo['photo_caption']);
		$xtpl->assign( 'caption', $caption );

		$details = null;
		if( $photo['photo_details'] ) {
			$params = POST_BBCODE | POST_EMOTICONS;
			$details = $this->format( $photo['photo_details'], $params );
		}
		$xtpl->assign( 'details', $details );

		$xtpl->assign( 'width', $photo['photo_width'] );
		$xtpl->assign( 'height', $photo['photo_height'] );

		$xtpl->assign( 'size', ceil( $photo['photo_size'] / 1024 ) );
		$date = null;
		if( $photo['photo_date'] > 0 )
			$date = ' on ' . date( $this->settings['blog_dateformat'], $photo['photo_date'] );
		$xtpl->assign( 'date', $date );

		$xtpl->assign( 'name', htmlspecialchars( $photo['user_name']) );

		$xtpl->assign( 'img_src', $this->settings['site_address'] . $this->gallery_dir . $photo['photo_md5name'] . '.' . $photo['photo_type'] );

		if( $this->settings['friendly_urls'] ) {
			$image_link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $photo['photo_caption'] ) . "-{$photo['photo_id']}.html";
		} else {
			$image_link = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$photo['photo_id']}";
		}
		$xtpl->assign( 'image_link', $image_link );

		$image_url = urlencode( $image_link );
		$xtpl->assign( 'image_url', $image_url );

		$data = base64_encode( $photo['photo_caption'] ) . ';' . base64_encode( $image_link );
		$this->generate_social_links( $xtpl, $data );

		if( $photo['photo_comment_count'] > 0 ) {
			$xtpl->assign( 'comments', $this->comments->list_comments( COMMENT_GALLERY, $p, $photo['photo_caption'], $photo['photo_user'], $photo['photo_comment_count'], $min, $num, $image_link ) );

			$xtpl->parse( 'GalleryImage.Comments' );
		}

		if( $this->user['user_level'] >= USER_MEMBER ) {
			$author = htmlspecialchars($this->user['user_name']);
		} else {
			$author = isset($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) ? htmlspecialchars($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) : 'Anonymous';
		}

		if( $this->settings['friendly_urls'] ) {
			$action_link = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $photo['photo_caption'] ) . "-{$photo['photo_id']}.html#newcomment";
		} else {
			$action_link = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$photo['photo_id']}#newcomment";
		}

		$closed = $this->closed_content( $photo, COMMENT_GALLERY );
		$xtpl->assign( 'comment_form', $this->comments->generate_comment_form( $author, $caption, $action_link, $closed ) );

		$xtpl->parse( 'GalleryImage' );
		return $xtpl->text( 'GalleryImage' );
	}

	function folder_array()
	{
		if( $this->folder_array === false ) {
			$this->folder_array = array();

			$q = $this->db->dbquery( 'SELECT * FROM %pphotofolders ORDER BY folder_name' );

			while ($f = $this->db->assoc($q))
			{
				$this->folder_array[$f['folder_id']] = $f;
			}
			return $this->folder_array;
		}
		return $this->folder_array;
	}

	function build_folder_list( $fd )
	{
		$folders = $this->folder_array();

		if( $this->settings['friendly_urls'] )
			$folder_link = $this->settings['site_address'] . 'gallery';
		else
			$folder_link = "{$this->settings['site_address']}index.php?a=gallery&amp;f=0";

		$folder_list = "&raquo; <a href=\"$folder_link\">Root</a>";

		if( $fd != 0 )
		{
			$parents = explode( ',', $folders[$fd]['folder_tree'] );

			foreach( $parents as $parent )
			{
				if(!isset($folders[$parent]) || $folders[$parent]['folder_id'] == 0)
					continue;

				$folder_name = $folders[$parent]['folder_name'];
				$folder_id = $folders[$parent]['folder_id'];

				if( $this->settings['friendly_urls'] )
					$folder_list .= " &raquo; <a href=\"{$this->settings['site_address']}gallery/" . $this->clean_url( $folder_name ) . "-" . $folder_id . "/\">";
				else
					$folder_list .= "&raquo; <a href=\"{$this->settings['site_address']}index.php?a=gallery&amp;f=$parent\">";

				$folder_list .= $folder_name . '</a>';
			}

			$folder_name = $folders[$fd]['folder_name'];
			$folder_list .= ' &raquo; ' . $folder_name;
		}
		return $folder_list;
	}

	function build_file_list( $f, $recent = false )
	{
		$list[] = array();

		$sql = 'SELECT photo_id, photo_caption, photo_md5name, photo_type, photo_size, photo_width, photo_height, photo_comment_count FROM %pphotogallery';
		if( $recent )
			$sql .= ' ORDER BY photo_date DESC LIMIT 50';
		else {
			$where = null;
			if( $this->user['user_level'] == USER_GUEST )
				$where .= ' AND (photo_flags & ' . POST_PUBLISHED . ') AND !(photo_flags & ' . POST_MEMBERSONLY . ')';
			else
				$where .= ' AND (photo_flags & ' . POST_PUBLISHED . ')';
			$sql .= " WHERE photo_folder=$f $where ORDER BY photo_date DESC";
		}

		$result = $this->db->dbquery( $sql );

		while( $photo = $this->db->assoc( $result ) )
		{
			if( $this->settings['friendly_urls'] )
				$caption = $this->settings['site_address'] . 'gallery/' . $this->clean_url( $photo['photo_caption'] ) . "-{$photo['photo_id']}.html";
			else
				$caption = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$photo['photo_id']}";

			$num = '';
			if( $photo['photo_comment_count'] > 0 )
				$num = ' (' . $photo['photo_comment_count'] . ')';

			$list[] = array(
				'name' 		 => $photo['photo_caption'],
				'num'		 => $num,
				'class'		 => 'thumbnail',
				'src' 		 => '/' . $this->thumb_dir . $photo['photo_md5name'] . '.' . $photo['photo_type'],
				'type'		 => $photo['photo_type'],
				'link'		 => $caption,
				'size'		 => ceil($photo['photo_size'] / 1024),
				'dimensions' => $photo['photo_width'] . 'x' . $photo['photo_height'] );
		}
		return $list;
	}
}
?>