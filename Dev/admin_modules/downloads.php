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

if ( !defined('SANDBOX') || !defined('SANDBOX_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class downloads extends module
{
	var $folder_array; // Used to generate folder trees

	function execute()
	{
		static $folder_array = false;
		$this->folder_array = &$folder_array;

		if ( isset($this->get['s']) )
			switch( $this->get['s'] )
			{
				case 'Delete':		return $this->show_files( 'Delete' );
				case 'Edit':		return $this->show_files( 'Edit' );
				case 'uploadfile':	return $this->upload_file();
				case 'Editfile':	return $this->edit_file();
				case 'Deletefile':	return $this->delete_file();
				case 'createfolder':	return $this->create_folder();
				case 'editfolder':	return $this->edit_folder();
				case 'deletefolder':	return $this->delete_folder();
			}
		return $this->message( 'Downloads', 'Sorry, you need to specify an action to use this module.' );
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

	function build_folder_list( $fd, $action )
	{
		$folders = $this->folder_array();

		$folder_list = "&raquo; <a href=\"admin.php?a=downloads&amp;s=$action\">Root</a>";

		if( $fd != 0 )
		{
			$parents = explode( ',', $folders[$fd]['folder_tree'] );

			foreach( $parents as $parent )
			{
				if(!isset($folders[$parent]) || $folders[$parent]['folder_id'] == 0)
					continue;

				$folder_name = $folders[$parent]['folder_name'];

				$folder_list .= "&raquo; <a href=\"admin.php?a=downloads&amp;s=$action&amp;f=$parent\">";

				$folder_list .= $folder_name . '</a>';
			}

			$folder_name = $folders[$fd]['folder_name'];
			$folder_list .= ' &raquo; ' . $folder_name;
		}
		return $folder_list;
	}

	function build_file_list( $f, $this_folder, $action )
	{
		$list[] = array();

		$result = $this->db->dbquery( 'SELECT f.*, u.user_id, u.user_name, u.user_icon
			  FROM %pfilelist f
			  LEFT JOIN %pusers u ON u.user_id=f.file_user
			  WHERE file_folder=%d', $f );

		while( $file = $this->db->assoc( $result ) )
		{
			$image = null;
			if( $file['file_flags'] & POST_HAS_IMAGE ) {
				$thumb = $this->settings['site_address'] . $this->thumb_dir . $file['file_md5name'] . '.' . $file['file_img_ext'];
				$image = '<img src="' . $thumb . '" alt="" />';
			}

			$date = $this->t_date( $file['file_date'] );

			$downloads = ' downloads';
			if( $file['file_downloaded'] == 1 )
				$downloads = ' download';

			$list[] = array(
				'name' => $file['file_name'],
				'version' => $file['file_version'],
				'class' => 'file',
				'link' => "admin.php?a=downloads&amp;s=" . $action . "file&amp;p={$file['file_id']}",
				'size' => ceil($file['file_size'] / 1024),
				'summary' => $file['file_summary'],
				'image' => $image,
				'date' => $date,
				'user' => $file['user_name'],
				'downloads' => $file['file_downcount'] . $downloads,
				'icon' => $this->display_icon($file['user_icon']) );
		}
		return $list;
	}

	function show_files($action)
	{
		$this->title( 'Show Files' );

		$count = 0; $this_folder = null;

		$f = isset($this->get['f']) ? intval($this->get['f']) : 0;

		$this_folder = $this->db->quick_query( 'SELECT * FROM %pfilefolders WHERE folder_id=%d', $f );
		if ( $f != 0 )
			$this->title( $this_folder['folder_name'] );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/downloads_folder.xtpl' );

		$xtpl->assign( 'tree', $this->build_folder_list($f, $action) );
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

			if( $this->user['user_id'] != $folder['folder_user'] && $this->user['user_level'] < USER_ADMIN )
				continue;

			$xtpl->assign( 'name', htmlspecialchars($folder['folder_name']) );
			$xtpl->assign( 'parent', "admin.php?a=downloads&amp;s=$action&amp;f={$folder['folder_id']}" );

			$xtpl->parse( 'DownloadFolder.Link' );
		}

		$list = $this->build_file_list( $f, $this_folder, $action );
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

		$xtpl->assign( 'folder_summary', $action . ' which of these files?' ); // Yes, this is legit. Tricking the template into displaying the action text.
		$xtpl->assign( 'folder_name', htmlspecialchars($this_folder['folder_name']) );

		$xtpl->parse( 'DownloadFolder' );
		return $xtpl->text( 'DownloadFolder' );
	}

	function file_error_message($error_code) {
		switch ($error_code) {
			case UPLOAD_ERR_INI_SIZE:
				return 'Upload size exceeds size allowed by the server php.ini file. Contact your system administrator.';
			case UPLOAD_ERR_FORM_SIZE:
				return 'Upload size exceeds MAX_FILE_SIZE directive in the HTML form. Verify your HTML code is not specifying a size too small.';
			case UPLOAD_ERR_PARTIAL:
				return 'Partial file uploaded.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Temporary folder missing. Contact your system administrator.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Server disk is full. Contact your system administrator.';
			case UPLOAD_ERR_EXTENSION:
				return 'Canceled by a PHP extension.';
			default:
				return 'An unknown error occured. Check your web server logs for details.';
		}
	}

	function build_foldertree( $folder_id, &$folders )
	{
		$tree = array();
		$orig_f = $folder_id;

		do {
			$old = $folder_id;
			if ( isset($folders[$folder_id]) && $folders[$folder_id]['parent'] != $old )
				array_unshift( $tree, htmlspecialchars($folders[$folder_id]['name']) );
		}
		while( isset($folders[$folder_id]) && ( $folder_id = $folders[$folder_id]['parent'] ) != $old );

		if ( isset($folders[$folder_id]) )
			array_unshift( $tree, $folders[$folder_id]['name'] );
		return implode( ' &raquo; ', $tree );
	}

	function folder_options( $name = 'file_folder', $root = true, $select_id = -1, $exclude = -1 )
	{
		$out = null;	$folders = array();

		$f_and = null;
		if( $this->user['user_level'] < USER_ADMIN ) {
			if( !$root )
				$f_and = 'AND folder_user=%d';
			else
				$f_and = 'WHERE folder_user=%d OR folder_id=0';
		}

		$result = $this->db->dbquery( 'SELECT folder_id, folder_user, folder_name, folder_parent
			  FROM %pfilefolders ' . (!$root ? 'WHERE folder_id != 0 ' : null) . $f_and .
			  ' ORDER BY folder_parent', $this->user['user_id'] );

		while ( $folder = $this->db->assoc($result) )
		{
			if( $this->user['user_level'] < USER_ADMIN && $folder['folder_user'] != $this->user['user_id'] )
				continue;

			if ( $folder['folder_id'] != $exclude )
				$folders[$folder['folder_id']] = array( "name" => $folder['folder_name'], "parent" => $folder['folder_parent'] );
		}
		foreach( $folders as $id => $folder )
			$out .= "<option value=\"$id\"" . ($id == $select_id ? " selected" : "") . ">" . $this->build_foldertree( $id, $folders ) . "</option>\n";
		return "<select name=\"$name\" id=\"$name\">\n$out</select>";
	}

	function folder_list( $header, $link, $root = true )
	{
		$folders = array();

		$f_and = null;
		if( $this->user['user_level'] < USER_ADMIN ) {
			if( !$root )
				$f_and = 'AND folder_user=%d';
			else
				$f_and = 'WHERE folder_user=%d OR folder_id=0';
		}

		$result = $this->db->dbquery( 'SELECT folder_id, folder_name, folder_parent
			  FROM %pfilefolders ' . (!$root ? 'WHERE folder_id != 0 ' : null) . $f_and .
			  ' ORDER BY folder_parent', $this->user['user_id'] );

		while ( $folder = $this->db->assoc($result) )
			$folders[$folder['folder_id']] = array( 'name' => $folder['folder_name'], 'parent' => $folder['folder_parent'] );

		$links = '';
		foreach( $folders as $id => $folder )
			$links .= "<li><a href=\"{$link}$id\">" . $this->build_foldertree( $id, $folders ) . "</a></li>\n";

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/downloads.xtpl' );

		$xtpl->assign( 'heading', $header );
		$xtpl->assign( 'links', $links );

		$xtpl->parse( 'FolderPick' );
		return $xtpl->text( 'FolderPick' );
	}

	function file_list( $header, $link, $folder )
	{
		$out = null; $files = array();

		$result = $this->db->dbquery( 'SELECT file_id, file_user, file_name, file_size FROM %pfilelist WHERE file_folder=%d', $folder );
		while ( $file = $this->db->assoc($result) )
		{
			if( $this->user['user_level'] < USER_ADMIN && $file['file_user'] != $this->user['user_id'] )
				continue;
			$out .= "<li><a href=\"{$link}{$file['file_id']}\">" . htmlspecialchars($file['file_name']) . "</a> (" . ceil($file['file_size'] / 1024) . " KB)</li>\n";
		}

		return "
<table>
 <tr>
  <td class=\"header\">" . htmlspecialchars($header) . "</td>
 </tr>
 <tr>
  <td><ul>\n$out</ul></td>
 </tr>
</table>";
	}

	function list_comments( $file_id )
	{
		$comments = $this->db->dbquery( 'SELECT c.*, u.user_name FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE comment_post=%d AND comment_type=%d', $file_id, COMMENT_FILE );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_comment_list.xtpl' );

		while ( $comment = $this->db->assoc($comments) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=posts&amp;s=edit_comment&amp;p=' . $file_id . '&amp;c='. $comment['comment_id'] . '">Edit Comment</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;p='. $file_id . '&amp;c=' . $comment['comment_id'] . '">Delete Comment</a>' );
			$xtpl->assign( 'spam_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;t=spam&amp;p=' . $file_id . '&amp;c=' . $comment['comment_id'] . '">Report Spam</a>' );
			$xtpl->assign( 'user_name', htmlspecialchars($comment['user_name']) );
			$xtpl->assign( 'user_ip', $comment['comment_ip'] );
			$xtpl->assign( 'comment_text', htmlspecialchars($comment['comment_message']) );

			$xtpl->parse( 'Comments.Entry' );
 		}
		$xtpl->parse( 'Comments' );
		return $xtpl->text( 'Comments' );
	}

	function file_form( $header, $action_link, $file = array('file_flags' => 4, 'file_name' => null, 'file_version' => 1, 'file_description' => null, 'file_summary' => null, 'file_folder' => 0 ) )
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/downloads.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', $header );
		$xtpl->assign( 'action_link', $action_link );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'folder_options', $this->folder_options( "file_folder", true, $file['file_folder'] ) );
		$xtpl->assign( 'filename', htmlspecialchars($file['file_name']) );
		$xtpl->assign( 'version', htmlspecialchars($file['file_version']) );
		$xtpl->assign( 'summary', htmlspecialchars($file['file_summary']) );
		$xtpl->assign( 'desc', htmlspecialchars($file['file_description']) );

		$xtpl->assign( 'img', POST_HAS_IMAGE );
		$xtpl->assign( 'pub', POST_PUBLISHED );
		$xtpl->assign( 'cls', POST_CLOSED );
		$xtpl->assign( 'ovr', POST_OVERRIDE );
		$xtpl->assign( 'mbo', POST_MEMBERSONLY );

		$flags = $file['file_flags'];
		$xtpl->assign( 'imgbox', $flags & POST_HAS_IMAGE ? " checked=\"checked\"" : null );
		$xtpl->assign( 'pubbox', $flags & POST_PUBLISHED ? " checked=\"checked\"" : null );
		$xtpl->assign( 'clsbox', $flags & POST_CLOSED ? " checked=\"checked\"" : null );
		$xtpl->assign( 'ovrbox', $flags & POST_OVERRIDE ? " checked=\"checked\"" : null );
		$xtpl->assign( 'mbobox', $flags & POST_MEMBERSONLY ? " checked=\"checked\"" : null );

		
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		if( isset($file['file_id']) )
			$xtpl->assign( 'comment_list', $this->list_comments( $file['file_id'] ) );

		$xtpl->parse( 'FileForm' );
		return $xtpl->text( 'FileForm' );
	}

	function upload_file()
	{
		if ( isset( $this->post['submit'] ) )
		{
			if ( $this->files['file_file']['error'] != UPLOAD_ERR_OK )
				return $this->error( 'An error occured while trying to upload that file: ' . $this->file_error_message($this->files['file_file']['error']) );

			$skipimage = false;
			if( $this->files['file_image']['error'] == UPLOAD_ERR_NO_FILE ) {
				$skipimage = true;
			} else {
				if( $this->files['file_image']['error'] != UPLOAD_ERR_OK )
					return $this->error( 'An error occured while trying to upload the image: ' . $this->file_error_message($this->files['file_image']['error']) );
			}

			$file = $this->files['file_file'];
			$folder = intval($this->post['file_folder']);

			$f = $this->db->quick_query( 'SELECT folder_id, folder_user FROM %pfilefolders WHERE folder_id=%d', $folder );
			if( $this->user['user_level'] < USER_ADMIN && $f['folder_user'] != $this->user['user_id'] && $f['folder_id'] != 0 )
				return $this->error( 'Access Denied: You do not own the folder you are attempting to upload to.' );

			$ext = '';
			if (strtolower(substr($file['name'], -7)) == '.tar.gz')
				$ext = 'tar.gz';
			else
				$ext = strtolower(substr($file['name'], -3));

			$md5name = md5( $file['name'] . microtime() );
			$new_fname = $this->file_dir . $md5name;
			if ( !move_uploaded_file( $file['tmp_name'], $new_fname ) )
				return $this->error( 'File failed to upload!' );

			chmod( $new_fname, 0644 );
			$filename = basename($file['name']);
			$name = $this->post['file_name'];
			$version = $this->post['file_version'];
			$desc = $this->post['file_description'];
			$summary = $this->post['file_summary'];
			$size = intval($file['size']);
			$flags = 0;
			foreach( $this->post['file_flags'] as $flag)
				$flags |= intval($flag);
			$img_ext = '';

			if( !$skipimage ) {
				$flags |= POST_HAS_IMAGE;

				$img_file = $this->files['file_image']['tmp_name'];
				$system = explode( '.', $this->files['file_image']['name'] );
				$system[1] = strtolower($system[1]);

				if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) )
					return $this->message( 'Upload File Image', 'Invalid file type ' . $system[1] . '. Valid file types are jpg, png and gif.' );

				$img_ext = $system[1];
				$fname = $this->files['file_image']['tmp_name'];
				$img_new_name = $this->thumb_dir . $md5name . '.' . $img_ext;
				if ( !move_uploaded_file( $fname, $img_new_name ) )
					return $this->message( 'Upload File Image', 'Image failed to upload!' );

				$image = $this->createthumb( $img_new_name, $img_new_name, $img_ext, $this->settings['gallery_thumb_w'], $this->settings['gallery_thumb_h'] );
			}

			$this->db->dbquery(
				"INSERT INTO %pfilelist (file_name,file_version,file_user,file_filename,file_description,file_summary,file_img_ext,file_folder,file_md5name,file_size,file_type,file_date,file_flags)
				      VALUES ('%s', '%s', %d, '%s', '%s', '%s', '%s', %d, '%s', %d, '%s', %d, %d)",
					$name, $version, $this->user['user_id'], $filename, $desc, $summary, $img_ext, $folder, $md5name, $size, $ext, $this->time, $flags );
			return $this->message( 'Upload File', 'The file had been uploaded.', 'Continue', 'admin.php' );
		}
		return $this->file_form( 'Upload File', 'admin.php?a=downloads&amp;s=uploadfile' );
	}

	function edit_file()
	{
		if( !isset($this->get['p']) && !isset($this->post['p']) )
			return $this->message( 'Edit File', 'No file was specified for editing.' );

		$p = isset($this->get['p']) ? intval($this->get['p']) : intval($this->post['p']);

		$dbfile = $this->db->quick_query( 'SELECT * FROM %pfilelist WHERE file_id=%d', $p );
		if ( !$dbfile )
			return $this->message( 'Edit File', 'No such file.' );

		if ( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$newupload = false;
			$old_filename = $dbfile['file_md5name'];
			$md5name = '';

			if( $this->files['file_file']['error'] == UPLOAD_ERR_OK )
			{
				$file = $this->files['file_file'];
				$ext = explode( '.', $file['name'] );
				$ext = strtolower($ext[1]);

				if ( !ctype_alnum( $ext ) )
					return $this->message( 'Edit File', 'Unknown filetype extension.' );

				$md5name = md5( $file['name'] . microtime() );
				$new_fname = $this->file_dir . $md5name;
				if ( !move_uploaded_file( $file['tmp_name'], $new_fname ) )
					return $this->message( 'Edit File', 'File failed to upload!' );

				$newupload = true;
				chmod( $new_fname, 0644 );
				unlink( $this->file_dir . $old_filename );

				$filename = basename($file['name']);
				$size = intval( $file['size'] );

				$this->db->dbquery( "UPDATE %pfilelist SET file_filename='%s', file_md5name='%s', file_size=%d, file_type='%s', file_date=%d WHERE file_id=%d",
					$filename, $md5name, $size, $ext, $this->time, $p );
			}
			$folder = intval($this->post['file_folder']);
			$name = $this->post['file_name'];
			$version = $this->post['file_version'];
			$desc = $this->post['file_description'];
			$summary = $this->post['file_summary'];
			$img_ext = $dbfile['file_img_ext'];
			$flags = 0;
			foreach( $this->post['file_flags'] as $flag)
				$flags |= intval($flag);

			if( $this->files['file_image']['error'] == UPLOAD_ERR_OK )
			{
				$flags |= POST_HAS_IMAGE;

				$old_ext = $dbfile['file_img_ext'];
				$img_file = $this->files['file_image']['tmp_name'];
				$system = explode( '.', $this->files['file_image']['name'] );
				$system[1] = strtolower($system[1]);

				if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) )
					return $this->message( 'Upload File Image', 'Invalid file type ' . $system[1] . '. Valid file types are jpg, png and gif.' );

				$img_ext = $system[1];
				$fname = $this->files['file_image']['tmp_name'];
				if( $newupload )
					$img_new_name = $this->thumb_dir . $md5name . '.' . $img_ext;
				else
					$img_new_name = $this->thumb_dir . $old_filename . '.' . $img_ext;

				if( $old_ext != $img_ext || $newupload )
					unlink( $this->thumb_dir . $old_filename . '.' . $old_ext );

				$image = $this->createthumb( $fname, $img_new_name, $img_ext, $this->settings['gallery_thumb_w'], $this->settings['gallery_thumb_h'] );
			} elseif( $newupload ) {
				if( $flags & POST_HAS_IMAGE ) {
					rename( $this->thumb_dir . $old_filename . '.' . $img_ext, $this->thumb_dir . $md5name . '.' . $img_ext );
				}
			}

			$count = $dbfile['file_downcount'];
			if( isset($this->post['reset_downloads']))
				$count = 0;

			$this->db->dbquery( "UPDATE %pfilelist
				SET file_name='%s', file_version='%s', file_description='%s', file_summary='%s', file_folder=%d, file_img_ext='%s', file_flags=%d, file_downcount=%d WHERE file_id=%d",
				 $name, $version, $desc, $summary, $folder, $img_ext, $flags, $count, $p );

			$update = (isset($this->files['file_file']) && $this->files['file_file']['error'] == UPLOAD_ERR_OK ? ' and new file uploaded.' : '.' );
			return $this->message( 'Edit File', 'File updated' . $update, 'Continue', 'admin.php' );
		}
		return $this->file_form( 'Edit File', "admin.php?a=downloads&amp;s=Editfile&amp;p=$p", $dbfile );
	}

	function do_delete_file( $id )
	{
		$file = $this->db->quick_query(	'SELECT file_id, file_md5name, file_type FROM %pfilelist WHERE file_id=%d', $id );

		if ( !$file )
			return false;
		unlink( "{$this->file_dir}{$file['file_md5name']}" );
		$this->db->dbquery( 'DELETE FROM %pfilelist WHERE file_id=%d', $id );
		return true;
	}

	function delete_file()
	{
		if ( !isset($this->post['submit']) )
		{
			$p = intval($this->get['p']);

			$file = $this->db->quick_query( 'SELECT file_id, file_user, file_name, file_md5name FROM %pfilelist WHERE file_id=%d', $p );
			if ( !$file )
				return $this->message( 'Delete File', 'No such file.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/downloads.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=downloads&amp;s=Deletefile&amp;p='. $p );
			$xtpl->assign( 'file_name', $file['file_name'] );
			$xtpl->assign( 'file_id', $p );

			$xtpl->parse( 'FileDelete' );
			return $xtpl->text( 'FileDelete' );

			return $this->message( 'Delete File', "Are you sure you want to delete {$file['file_name']} and all attached comments?", 'Delete', "admin.php?a=downloads&amp;s=Deletefile&amp;p=$p&amp;confirm=1", 0 );
		}

		if ( isset($this->post['p']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$p = intval( $this->post['p'] );

			$file = $this->db->quick_query( 'SELECT file_id, file_user, file_md5name, file_folder, file_img_ext
				FROM %pfilelist WHERE file_id=%d', $p );
			if ( !$file )
				return $this->message( 'Delete File', 'No such file.' );

			@unlink( $this->thumb_dir . "{$file['file_md5name']}.{$file['file_img_ext']}" );
			@unlink( $this->file_dir . $file['file_md5name'] );
			$this->db->dbquery( 'DELETE FROM %pfilelist WHERE file_id=%d', $p );
			$this->db->dbquery( 'DELETE FROM %pblogcomments WHERE comment_post=%d AND comment_type=%d', $p, COMMENT_FILE );

			$link = 'admin.php?a=downloads&s=Delete&f=' . $file['file_folder'];
			return $this->message( 'Delete File', 'File deleted.', 'Continue', $link );
		}
		return $this->message( 'Delete File', 'No file selected.' );
	}

	function folder_form( $header, $action, $folder = array('folder_name' => null, 'folder_summary' => null, 'folder_parent' => 0, 'folder_hidden' => 0) )
	{
		$f = isset($this->get['f']) ? intval($this->get['f']) : null;

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/downloads.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'action_link', $action );
		$xtpl->assign( 'heading', $header );
		$xtpl->assign( 'folder_name', htmlspecialchars($folder['folder_name']) );
		$xtpl->assign( 'folder_summary', htmlspecialchars($folder['folder_summary']) );

		$xtpl->assign( 'folder_options', $this->folder_options( "folder_parent", true, $folder['folder_parent'], $f ) );
		$xtpl->assign( 'fchecked', $folder['folder_hidden'] ? 'checked="checked"' : '' );

		$xtpl->parse( 'FolderForm' );
		return $xtpl->text( 'FolderForm' );
	}

	function create_folder()
	{
		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['folder_name'];
			$summary = $this->post['folder_summary'];
			$parent = intval($this->post['folder_parent']);
			$hidden = isset($this->post['folder_hidden']) ? 1 : 0;

			if ( empty($name) )
				return $this->message( 'Create Downloads Folder', 'Specify a folder name.' );

			$f = $this->db->quick_query( 'SELECT folder_id, folder_user FROM %pfilefolders WHERE folder_parent=%d', $parent );
			if( $this->user['user_level'] < USER_ADMIN && $f['folder_user'] != $this->user['user_id'] && $f['folder_id'] != 0 )
				return $this->error( 'Access Denied: You cannot create a folder in someone else\'s parent folder.' );

			$this->db->dbquery( "INSERT INTO %pfilefolders (folder_name,folder_user,folder_parent,folder_hidden,folder_summary)
				VALUES ('%s', %d, %d, %d, '%s' )", $name, $this->user['user_id'], $parent, $hidden, $summary );

			$this->update_folder_trees();
			return $this->message( 'Create Downloads Folder', 'Folder created.', 'Continue', 'admin.php' );
		}
		return $this->folder_form( 'Create Downloads Folder', 'admin.php?a=downloads&amp;s=createfolder' );
	}

	function create_tree($array, $id)
	{
		foreach ($array as $cat) {
			if ($cat['folder_parent'] == $id) {
				return preg_replace('/^,/', '', $cat['folder_tree'] . ",$id");
			}
		}
	}

	function buildTree($folders, $parent)
	{
		$tree = '';
		if (isset($folders[$parent]) && $folders[$parent]) {
			$tree = $this->buildTree($folders, $folders[$parent]);
			$tree .= ',';
		}
		$tree .= $parent;
		return $tree;
	}

	function update_folder_trees()
	{
		$folders = array();
		
		// Build tree structure of 'id' => 'parent' structure
		$q = $this->db->dbquery( 'SELECT folder_id, folder_parent FROM %pfilefolders ORDER BY folder_parent' );

		while ($f = $this->db->assoc($q))
		{
			if ($f['folder_parent']) {
				$folders[$f['folder_id']] = $f['folder_parent'];
			}
		}

		// Run through group
		$q = $this->db->dbquery( 'SELECT folder_parent FROM %pfilefolders GROUP BY folder_parent' );

		while ($f = $this->db->assoc($q))
		{
			if ($f['folder_parent']) {
				$tree = $this->buildTree($folders, $f['folder_parent']);
			} else {
				$tree = '';
			}

			$this->db->dbquery( "UPDATE %pfilefolders SET folder_tree='%s' WHERE folder_parent=%d", $tree, $f['folder_parent'] );
		}
	}

	function edit_folder()
	{
		$this->title( 'Edit Downloads Folder' );

		if ( !isset($this->get['f']) && !isset($this->post['f']) )
			return $this->folder_list( 'Edit which downloads folder?', 'admin.php?a=downloads&amp;s=editfolder&amp;f=', false );

		$f = isset($this->get['f']) ? intval($this->get['f']) : intval($this->post['f']);

		$folder = $this->db->quick_query( 'SELECT folder_id, folder_user, folder_name, folder_summary, folder_parent, folder_hidden
			   FROM %pfilefolders WHERE folder_id=%d', $f );

		if( $this->user['user_level'] < USER_ADMIN && $folder['folder_user'] != $this->user['user_id'] )
			return $this->error( 'Access Denied: You do not own the folder you are trying to edit.' );

		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['folder_name'];
			$summary = $this->post['folder_summary'];
			$parent = intval($this->post['folder_parent']);
			$hidden = isset($this->post['folder_hidden']) ? 1 : 0;

			$this->db->dbquery( "UPDATE %pfilefolders SET folder_name='%s', folder_parent=%d, folder_hidden=%d, folder_summary='%s'
				WHERE folder_id=%d", $name, $parent, $hidden, $summary, $f );

			$this->update_folder_trees();
			return $this->message( 'Edit Downloads Folder', 'Folder updated.', 'Continue', 'admin.php' );
		}
		return $this->folder_form( 'Edit Downloads Folder', "admin.php?a=downloads&amp;s=editfolder&amp;f=$f", $folder );
	}

	function delete_fold( $f )
	{
		if ( $f == 0 )
			return false;

		$result = $this->db->dbquery( 'SELECT folder_id FROM %pfilefolders WHERE folder_parent=%d', $f );
		while ( $folder = $this->db->assoc( $result ) )
			$this->delete_fold($folder['folder_id']);

		$result = $this->db->dbquery( 'SELECT file_id FROM %pfilelist WHERE file_folder=%d', $f );

		while ( $file = $this->db->assoc($result) )
			$this->db->dbquery( 'UPDATE %pfilelist SET file_folder=0 WHERE file_id=%d', $file['file_id'] );

		$this->db->dbquery( 'DELETE FROM %pfilefolders WHERE folder_id=%d', $f );
		return true;
	}

	function delete_folder()
	{
		if ( !isset($this->get['f']) && !isset($this->post['f']) )
			return $this->folder_list( 'Delete which downloads folder?', 'admin.php?a=downloads&amp;s=deletefolder&amp;f=', false );

		$f = isset($this->get['f']) ? intval($this->get['f']) : intval($this->post['f']);

		$folder = $this->db->quick_query( 'SELECT folder_name, folder_user FROM %pfilefolders WHERE folder_id=%d', $f );

		if( $this->user['user_level'] < USER_ADMIN && $folder['folder_user'] != $this->user['user_id'] )
			return $this->error( 'Access Denied: You do not own the folder you are trying to delete.' );

		if ( !isset($this->post['submit']) )
		{
			$count = $this->db->quick_query( 'SELECT COUNT(file_id) as count FROM %pfilelist WHERE file_folder=%d', $f );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/downloads.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=downloads&amp;s=deletefolder&amp;f=' .$f );
			$xtpl->assign( 'folder_name', $folder['folder_name'] );
			$xtpl->assign( 'folder_id', $f );
			$xtpl->assign( 'count', $count['count'] );

			$xtpl->parse( 'FolderDelete' );
			return $xtpl->text( 'FolderDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if ( !$this->delete_fold($f) )
			return $this->message( 'Delete Downloads Folder', 'Could not delete folder.' );
		return $this->message( 'Delete Downloads Folder', 'The folder has been deleted.', 'Continue', 'admin.php' );
	}
}
?>