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

class downloads extends module
{
	var $folder_array; // Used to generate folder trees

	function execute()
	{
		$this->comments = new comments($this);

		if ( isset($this->get['p']) )
			return $this->view_file(intval($this->get['p']));

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] )
			{
				case 'edit_comment':	return $this->comments->edit_comment();
				case 'del_comment':	return $this->comments->delete_comment();
				case 'get':
					if( isset($this->get['i']) )
						return $this->download_file(intval($this->get['i']));
					break;
			}
			return $this->error( 'The operation you specified is not recognized.' );
		}

		$this->title( 'Downloads' );
		$this->meta_description( $this->settings['site_name'] . ' Downloads Area.' );

		$Thumbs = null; $this_folder = null;

		static $folder_array = false;
		$this->folder_array = &$folder_array;

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
				$this_folder = $this->db->quick_query( "SELECT * FROM %pfilefolders WHERE folder_name='%s' AND folder_id=%d", $fld, $z );
			if( !$this_folder )
				return $this->error( 'The folder you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

			$f = $this_folder['folder_id'];
		} else {
			$f = isset($this->get['f']) ? intval($this->get['f']) : 0;

			$this_folder = $this->db->quick_query( 'SELECT * FROM %pfilefolders WHERE folder_id=%d', $f );
		}

		if( $this_folder['folder_hidden'] && $this->user['user_id'] != $this_folder['folder_user'] && $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'The folder you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/downloads_folder.xtpl' );

		$tree = $this->build_folder_list($f);
		$xtpl->assign( 'tree', $tree );
		$xtpl->assign( 'imgsrc', $this->settings['site_address'] . 'skins/' . $this->skin );

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

			$xtpl->assign( 'name', htmlspecialchars($folder['folder_name']) );

			if( $this->settings['friendly_urls'] )
				$parent = $this->settings['site_address'] . 'downloads/' . $this->clean_url( $folder['folder_name'] ) . '-' . $id . '/';
			else
				$parent = "{$this->settings['site_address']}index.php?a=downloads&amp;f={$folder['folder_id']}";

			$xtpl->assign( 'parent', $parent );

			$xtpl->parse( 'DownloadFolder.Link' );
		}

		$list = $this->build_file_list( $f );
		foreach( $list as $item )
		{
			if( !isset($item['name']) )
				continue;

			$xtpl->assign( 'item_name', htmlspecialchars($item['name'] . ' ' . $item['version']) );
			$xtpl->assign( 'item_icon', $item['icon'] );
			$xtpl->assign( 'item_link', $item['link'] );
			$xtpl->assign( 'item_date', $item['date'] );
			$xtpl->assign( 'item_user', htmlspecialchars($item['user']) );
			$xtpl->assign( 'item_image', $item['image'] );
			$xtpl->assign( 'item_summary', htmlspecialchars($item['summary']) );
			$xtpl->assign( 'item_size', $item['size'] );
			$xtpl->assign( 'item_downloads', $item['downloads'] );

			$xtpl->parse( 'DownloadFolder.File' );
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

		$xtpl->parse( 'DownloadFolder' );
		return $xtpl->text( 'DownloadFolder' );
	}

	function pretty_filetype( $type )
	{
		switch ( $type )
		{
			case 'esp':								return 'TES Mod File';
			case 'zip': 								return 'Zip File';
			case 'gz': case 'tar': case 'tgz': case 'tar.gz': 			return 'Linux Tarball';
			case '.7z': case '7z': 							return '7zip Archive';
			case 'c': case 'h': case 'cpp': case 'c++': case 'h++': case 'hpp':	return 'C/C++ Code';
			case 'gif': case 'jpg': case 'jpeg': case 'png':			return 'Image';
			case 'php': case 'html': case 'htm': case 'asp':			return 'Webpage';
			case 'txt':								return 'Text File';
		}
		return $type;
	}

	function view_file( $f )
	{
		$file = $this->db->quick_query( 'SELECT p.*, f.folder_hidden, u.user_name
			  FROM %pfilelist p
		 LEFT JOIN %pfilefolders f ON f.folder_id=p.file_folder
		 LEFT JOIN %pusers u ON u.user_id=p.file_user
			 WHERE p.file_id=%d', $f );

		if( isset($this->get['title']) ) {
			if( $this->clean_url( $file['file_name'] ) != $this->get['title'] )
				$file = null;
		}

		if( !($file['file_flags'] & POST_PUBLISHED) || ( ($file['file_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] == USER_GUEST) )
			$file = null;
		if( !$file || ($file['folder_hidden'] && $this->user['user_id'] != $file['folder_user'] && $this->user['user_level'] < USER_ADMIN) )
			return $this->error( 'The download you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		if( isset($this->post['submit']) || isset($this->post['preview']) )
		{
			if( $this->closed_content( $file, COMMENT_FILE ) ) {
				return $this->error( 'Sorry, this dowload entry is closed for commenting.', 403 );
			}

			$result = $this->comments->post_comment( COMMENT_FILE, $file['file_name'], $f );
			if( is_string($result) )
				return $result;

			if( isset($this->post['request_uri']) )
				header( 'Location: ' . $this->post['request_uri'] );

			if( $this->settings['friendly_urls'] )
				$link = $this->settings['site_address'] . 'downloads/' . $this->clean_url( $file['file_name'] ) . "-$f.html&c=$result#comment-$result";
			else
				$link = "{$this->settings['site_address']}index.php?a=downloads&p=$f&c=$result#comment-$result";
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
			$coms = $this->db->quick_query( "SELECT COUNT(comment_id) count FROM %pblogcomments WHERE comment_post=%d AND comment_id < %d AND comment_type=%d", $f, $cmt, COMMENT_FILE );

			if ($coms)
				$count = $coms['count'] + 1;
			else $count = 0;

			$min = 0; // Start at the first page regardless
			while ($count > ($min + $num)) {
				$min += $num;
			}
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/downloads_file.xtpl' );

		$this->title( $file['file_name'] );
		$this->meta_description( $file['file_summary'] );

		if( $this->user['user_level'] == USER_GUEST && $this->settings['download_size'] > 0 && $file['file_size'] >= $this->settings['download_size'] )
			$file_url = 'Registration required to download';
		else {
			if( $this->settings['friendly_urls'] )
				$file_url = '<a href="' . $this->settings['site_address'] . 'downloads/' . $this->clean_url( $file['file_name'] ) . "-$f\">Download</a>";
			else
				$file_url = "<a href=\"{$this->settings['site_address']}index.php?a=downloads&amp;s=get&amp;i=$f\">Download</a>";
		}

		$xtpl->assign( 'file_url', $file_url );

		$file_name = htmlspecialchars($file['file_name']);
		$xtpl->assign( 'file_name', $file_name );
		$xtpl->assign( 'file_version', htmlspecialchars($file['file_version']) );
		$xtpl->assign( 'user_name', htmlspecialchars($file['user_name']) );

		$xtpl->assign( 'desc', $this->format( $file['file_description'], POST_BBCODE ) );
		$xtpl->assign( 'type', $this->pretty_filetype( $file['file_type'] ) );
		$xtpl->assign( 'size', ceil( $file['file_size'] / 1024 ) );
		$xtpl->assign( 'downloads', $file['file_downcount'] . ' time' . ($file['file_downcount'] != 1 ? 's' : '') );
		$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $file['file_date'] ) );

		if( $file['file_downloaded'] < 1 )
			$down_time = 'Never';
		else
			$down_time = date( $this->settings['blog_dateformat'], $file['file_downloaded'] );
		$xtpl->assign( 'down_time', $down_time );

		if( $this->user['user_level'] >= USER_MEMBER ) {
			$author = htmlspecialchars($this->user['user_name']);
		} else {
			$author = isset($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) ? htmlspecialchars($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) : 'Anonymous';
		}

		if( $this->settings['friendly_urls'] ) {
			$action_link = $this->settings['site_address'] . 'downloads/' . $this->clean_url( $file['file_name'] ) . "-{$file['file_id']}.html";
		} else {
			$action_link = "{$this->settings['site_address']}index.php?a=downloads&amp;p={$file['file_id']}";
		}

		if( $file['file_comment_count'] > 0 ) {
			$xtpl->assign( 'comments', $this->comments->list_comments( COMMENT_FILE, $f, $file['file_name'], $file['file_user'], $file['file_comment_count'], $min, $num, $action_link ) );

			$xtpl->parse( 'DownloadFile.Comments' );
		}

		$closed = $this->closed_content( $file, COMMENT_FILE );
		$xtpl->assign( 'comment_form', $this->comments->generate_comment_form( $author, $file_name, $action_link, $closed ) );

		$xtpl->parse( 'DownloadFile' );
		return $xtpl->text( 'DownloadFile' );
	}

	function download_file( $f )
	{
		$file = $this->db->quick_query( 'SELECT p.file_id, p.file_name, p.file_filename, p.file_md5name, p.file_type, p.file_size, p.file_flags, f.folder_hidden, f.folder_user
			 FROM %pfilelist p
			 LEFT JOIN %pfilefolders f ON f.folder_id=p.file_folder
			 WHERE p.file_id=%d', $f );

		if( isset($this->get['title']) ) {
			if( $this->clean_url( $file['file_name'] ) != $this->get['title'] )
				$file = null;
		}
		if( !($file['file_flags'] & POST_PUBLISHED) || ( ($file['file_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] == USER_GUEST) )
			$file = null;
		if( !$file || ($file['folder_hidden'] && $this->user['user_id'] != $file['folder_user'] && $this->user['user_level'] < USER_ADMIN) )
			return $this->error( 'The file you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		if( $this->user['user_level'] == USER_GUEST && $this->settings['download_size'] > 0 && $file['file_size'] >= $this->settings['download_size'] )
			return $this->error( 'The file you are trying to download requires a valid user account.', 403 );

		$this->db->dbquery( 'UPDATE %pfilelist SET file_downcount=file_downcount+1, file_downloaded=%d WHERE file_id=%d', $this->time, $f );

		$this->nohtml = true;
		header('Connection: close');
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header("Content-Disposition: attachment; filename=\"{$file['file_filename']}\"");
		header('Content-Length: ' . $file['file_size']);
		header('X-Robots-Tag: noarchive, nosnippet, noindex');

		// directly pass through file to output buffer
		@readfile ( $this->file_dir . $file['file_md5name'] );
	}

	function folder_array()
	{
		if( $this->folder_array === false ) {
			$this->folder_array = array();

			$q = $this->db->dbquery( 'SELECT * FROM %pfilefolders ORDER BY folder_name' );

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
			$folder_link = $this->settings['site_address'] . 'downloads';
		else
			$folder_link = "{$this->settings['site_address']}index.php?a=downloads";

		$folder_list = "&raquo; <a href=\"$folder_link\">Root</a>";

		if( $fd != 0 )
		{
			$parents = explode( ',', $folders[$fd]['folder_tree'] );

			foreach( $parents as $parent )
			{
				if(!isset($folders[$parent]) || $folders[$parent]['folder_id'] == 0)
					continue;

				$folder_name = $folders[$parent]['folder_name'];

				if( $this->settings['friendly_urls'] )
					$folder_list .= " &raquo; <a href=\"{$this->settings['site_address']}downloads/" . $this->clean_url( $folder_name ) . "-$parent/\">";  
				else
					$folder_list .= "&raquo; <a href=\"{$this->settings['site_address']}index.php?a=downloads&amp;f=$parent>";

				$folder_list .= $folder_name . '</a>';
			}

			$folder_name = $folders[$fd]['folder_name'];
			$folder_list .= ' &raquo; ' . $folder_name;
		}
		return $folder_list;
	}

	function build_file_list( $f )
	{
		$list[] = array();

		$where = null;
		if( $this->user['user_level'] == USER_GUEST )
			$where .= ' AND (file_flags & ' . POST_PUBLISHED . ') AND !(file_flags & ' . POST_MEMBERSONLY . ')';
		else
			$where .= ' AND (file_flags & ' . POST_PUBLISHED . ')';

		$result = $this->db->dbquery( 'SELECT f.*, u.user_id, u.user_name, u.user_icon
			  FROM %pfilelist f
			  LEFT JOIN %pusers u ON u.user_id=f.file_user
			  WHERE file_folder=%d%s', $f, $where );

		while( $file = $this->db->assoc( $result ) )
		{
			if( $this->settings['friendly_urls'] )
				$file_name = $this->settings['site_address'] . 'downloads/' . $this->clean_url( $file['file_name'] ) . "-{$file['file_id']}.html";
			else
				$file_name = "{$this->settings['site_address']}index.php?a=downloads&amp;p={$file['file_id']}";

			$image = null;
			if( $file['file_flags'] & POST_HAS_IMAGE ) {
				$thumb = $this->settings['site_address'] . $this->thumb_dir . $file['file_md5name'] . '.' . $file['file_img_ext'];
				$image = '<img src="' . $thumb . '" alt="" />';
			}

			$date = date( $this->settings['blog_dateformat'], $file['file_date'] );

			$downloads = ' downloads';
			if( $file['file_downloaded'] == 1 )
				$downloads = ' download';

			$list[] = array(
				'name' => $file['file_name'],
				'version' => $file['file_version'],
				'class' => 'file',
				'link' => $file_name,
				'size' => ceil($file['file_size'] / 1024),
				'summary' => $file['file_summary'],
				'image' => $image,
				'date' => $date,
				'user' => $file['user_name'],
				'downloads' => $file['file_downcount'] . $downloads,
				'icon' => $this->display_icon( $file['user_icon'] ) );
		}
		return $list;
	}
}
?>