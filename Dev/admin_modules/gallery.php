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

class gallery extends module
{
	var $folder_array; // Used to generate folder trees

	function execute()
	{
		static $folder_array = false;
		$this->folder_array = &$folder_array;

		if ( isset($this->get['s']) )
			switch( $this->get['s'] )
			{
				case 'Delete':		return $this->show_images( 'Delete' );
				case 'Edit':		return $this->show_images( 'Edit' );
				case 'upload':		return $this->upload_image();
				case 'Deleteimage':	return $this->delete_image();
				case 'createfolder':	return $this->create_folder();
				case 'Editimage':	return $this->edit_image_info();
				case 'editfolder':	return $this->edit_folder();
				case 'deletefolder':	return $this->delete_folder();
			}
		return $this->message( 'Image Gallery', 'Sorry, you need to specify an action to use this module.' );
	}

	function show_images($action)
	{
		$this->title( 'Show Images' );

		$count = 0; $this_folder = null;

		$f = isset($this->get['f']) ? intval($this->get['f']) : 0;

		$this_folder = $this->db->quick_query( 'SELECT * FROM %pphotofolders WHERE folder_id=%d', $f );
		if ( $f != 0 )
			$this->title( $this_folder['folder_name'] );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/gallery_folder.xtpl' );

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
			$xtpl->assign( 'parent', "admin.php?a=gallery&amp;s=$action&amp;f={$folder['folder_id']}" );

			$xtpl->parse( 'GalleryFolder.Link' );
		}

		$list = $this->build_file_list( $f, $this_folder, $action );
		foreach( $list as $item )
		{
			if( !isset($item['name']) )
				continue;
			$item_name = $item['name'];
			if( strlen( $item_name ) > 23 )
				$item_name = substr( $item_name, 0, 20 ) . '...';
			$xtpl->assign( 'item_name', htmlspecialchars($item_name) );

			$src = getimagesize( './' . $item['src'] );
			$xtpl->assign( 'imgWH', $src[3] );
			$xtpl->assign( 'item_src', htmlspecialchars($item['src']) );
			$xtpl->assign( 'item_type', $item['type'] );
			$xtpl->assign( 'item_dimensions', $item['dimensions'] );
			$xtpl->assign( 'item_size', $item['size'] );
			$xtpl->assign( 'item_link', $item['link'] );
			$xtpl->assign( 'item_num', $item['num'] );

			$xtpl->parse( 'GalleryFolder.Thumbnail' );
		}

		$xtpl->assign( 'folder_summary', $action . ' which of these images?' ); // Yes, this is legit. Tricking the template into displaying the action text.
		$xtpl->assign( 'folder_name', htmlspecialchars($this_folder['folder_name']) );

		$xtpl->parse( 'GalleryFolder' );
		return $xtpl->text( 'GalleryFolder' );
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

	function build_folder_list( $fd, $action )
	{
		$folders = $this->folder_array();

		$folder_list = "&raquo; <a href=\"admin.php?a=gallery&amp;s=$action\">Root</a>";

		if( $fd != 0 )
		{
			$parents = explode( ',', $folders[$fd]['folder_tree'] );

			foreach( $parents as $parent )
			{
				if(!isset($folders[$parent]) || $folders[$parent]['folder_id'] == 0)
					continue;

				$folder_name = $folders[$parent]['folder_name'];

				$folder_list .= "&raquo; <a href=\"admin.php?a=gallery&amp;s=$action&amp;f=$parent\">";

				$folder_list .= $folder_name . '</a>';
			}

			$folder_name = $folders[$fd]['folder_name'];
			$folder_list .= ' &raquo; ' . $folder_name;
		}
		return $folder_list;
	}

	function build_file_list( $f, &$this_folder, $action )
	{
		$list[] = array();

		$result = $this->db->dbquery( 'SELECT photo_id, photo_user, photo_caption, photo_md5name, photo_type, photo_size, photo_width, photo_height
			  FROM %pphotogallery WHERE photo_folder=%d', $f );

		while( $photo = $this->db->assoc( $result ) )
		{
			if( $this->user['user_level'] < USER_ADMIN && $this->user['user_id'] != $photo['photo_user'] )
				continue;

			$size = ceil( $photo['photo_size'] / 1024 );
			$list[] = array(
				'name' 		 => $photo['photo_caption'],
				'num' => '',
				'class'		 => 'thumbnail',
				'src' 		 => $this->thumb_dir . $photo['photo_md5name'] . '.' . $photo['photo_type'],
				'type'		 => $photo['photo_type'],
				'link'		 => 'admin.php?a=gallery&amp;s=' . $action . 'image&amp;p=' . $photo['photo_id'],
				'size'		 => $size,
				'dimensions' => $photo['photo_width'] . 'x' . $photo['photo_height'] );
		}
		return $list;
	}

	function edit_image_info( )
	{
		$this->title( 'Edit Image' );

		$p = 0;

		if( isset( $this->get['p'] ) )
			$p = intval( $this->get['p'] );
		else if( isset( $this->post['p'] ) )
			$p = intval( $this->post['p'] );

		$photo = $this->db->quick_query( '
			SELECT photo_id, photo_user, photo_caption, photo_summary, photo_details, photo_md5name, photo_type, photo_folder,
				   photo_size, photo_width, photo_height, photo_flags
			  FROM %pphotogallery WHERE photo_id=%d', $p );

		if ( !$photo )
			return $this->message( 'Edit Image', 'No such image.' );

		if( $this->user['user_level'] < USER_ADMIN && $photo['photo_user'] != $this->user['user_id'] )
			return $this->error( 'Access Denied: You do not own the image you are trying to edit.' );

		if ( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			if ( isset( $this->files['image_file'] ) && $this->files['image_file']['error'] == UPLOAD_ERR_OK )
			{
				$old_filename = $photo['photo_md5name'];
				$old_thumbnail = $old_filename;

				$fname = $this->files['image_file']['tmp_name'];
				$system = explode( '.', $this->files['image_file']['name'] );
				$system[1] = strtolower($system[1]);

				if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) )
					return $this->message( 'Update Image', 'Invalid file type ' . $system[1] . '. Valid file types are jpg, png and gif.' );

				$md5 = md5( $this->files['image_file']['name'] . time() );
				$name = $md5 . '.' . $system[1];
				$new_fname = $this->gallery_dir . $name;

				if ( !move_uploaded_file( $fname, $new_fname ) )
					return $this->message( 'Update Image', 'Image failed to upload!' );

				$size = $this->files['image_file']['size'];
				$image = $this->createthumb( $new_fname, $this->thumb_dir . $name, $system[1], $this->settings['gallery_thumb_w'], $this->settings['gallery_thumb_h'] );

				$this->db->dbquery( "UPDATE %pphotogallery
				   SET photo_md5name='%s', photo_size=%d, photo_width=%d, photo_height=%d, photo_type='%s'
				   WHERE photo_id=%d", $md5, $size, $image['width'], $image['height'], $system[1], $p );

				chmod( $new_fname, 0644 );
				chmod( $this->thumb_dir . $name, 0644 );
				@unlink( $this->thumb_dir . "{$photo['photo_md5name']}.{$photo['photo_type']}" );
				@unlink( $this->gallery_dir . "{$photo['photo_md5name']}.{$photo['photo_type']}" );
			}

			$caption = $this->post['image_caption'];
			$details = $this->post['image_details'];
			$summary = $this->post['image_summary'];
			$folder = intval($this->post['image_folder']);

			$flags = 0;
			foreach( $this->post['image_flags'] as $flag)
				$flags |= intval($flag);

			$this->db->dbquery( "UPDATE %pphotogallery SET photo_caption='%s', photo_summary='%s', photo_details='%s', photo_folder=%d, photo_flags=%d
			   WHERE photo_id=%d", $caption, $summary, $details, $folder, $flags, $p );

			$link = 'admin.php?a=gallery&s=Edit&f=' . $photo['photo_folder'];
			return $this->message( 'Edit Image', 'Image information has been updated.', 'Continue', $link );
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Edit Image Information' );
		$xtpl->assign( 'action_link', 'admin.php?a=gallery&amp;s=Editimage&amp;p=' . $photo['photo_id'] );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'imgsrc', $this->gallery_dir . $photo['photo_md5name'] . '.' . $photo['photo_type'] );
		$xtpl->assign( 'replace', 'Replace ' );
		$xtpl->assign( 'folder_select', $this->folder_options( "image_folder", true, $photo['photo_folder'] ) );
		$xtpl->assign( 'caption', htmlspecialchars($photo['photo_caption']) );
		$xtpl->assign( 'summary', htmlspecialchars($photo['photo_summary']) );
		$xtpl->assign( 'details', htmlspecialchars($photo['photo_details']) );
		$xtpl->assign( 'width', $photo['photo_width'] );
		$xtpl->assign( 'height', $photo['photo_height'] );
		$xtpl->assign( 'type', $photo['photo_type'] );

		$xtpl->assign( 'pub', POST_PUBLISHED );
		$xtpl->assign( 'cls', POST_CLOSED );
		$xtpl->assign( 'ovr', POST_OVERRIDE );
		$xtpl->assign( 'mbo', POST_MEMBERSONLY );

		$flags = $photo['photo_flags'];
		$xtpl->assign( 'pubbox', $flags & POST_PUBLISHED ? " checked=\"checked\"" : null );
		$xtpl->assign( 'clsbox', $flags & POST_CLOSED ? " checked=\"checked\"" : null );
		$xtpl->assign( 'ovrbox', $flags & POST_OVERRIDE ? " checked=\"checked\"" : null );
		$xtpl->assign( 'mbobox', $flags & POST_MEMBERSONLY ? " checked=\"checked\"" : null );

		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		$xtpl->assign( 'comment_list', $this->list_comments( $p ) );

		$xtpl->parse( 'ImageForm.Edit' );
		$xtpl->parse( 'ImageForm' );
		return $xtpl->text( 'ImageForm' );
	}

	function list_comments( $image_id )
	{
		$comments = $this->db->dbquery( 'SELECT c.*, u.user_name FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE comment_post=%d AND comment_type=%d', $image_id, COMMENT_GALLERY );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_comment_list.xtpl' );

		while ( $comment = $this->db->assoc($comments) )
		{
			foreach ( $comment as $key => $value )
				$comment[$key] = htmlspecialchars($value);

			$xtpl->assign( 'edit_link', '<a href="admin.php?a=posts&amp;s=edit_comment&amp;p=' . $image_id . '&amp;c='. $comment['comment_id'] . '">Edit Comment</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;p='. $image_id . '&amp;c=' . $comment['comment_id'] . '">Delete Comment</a>' );
			$xtpl->assign( 'spam_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;t=spam&amp;p=' . $image_id . '&amp;c=' . $comment['comment_id'] . '">Report Spam</a>' );
			$xtpl->assign( 'user_name', htmlspecialchars($comment['user_name']) );
			$xtpl->assign( 'user_ip', $comment['comment_ip'] );
			$xtpl->assign( 'comment_text', htmlspecialchars($comment['comment_message']) );

			$xtpl->parse( 'Comments.Entry' );
 		}
		$xtpl->parse( 'Comments' );
		return $xtpl->text( 'Comments' );
	}

	function upload_image()
	{
		if ( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$caption = $this->post['image_caption'];
			$summary = $this->post['image_summary'];
			$details = $this->post['image_details'];
			$folder = intval( $this->post['image_folder'] );
			$fname = $this->files['image_file']['tmp_name'];
			$system = explode( '.', $this->files['image_file']['name'] );
			$system[1] = strtolower($system[1]);

			if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) )
				return $this->message( 'Upload Image', 'Invalid file type ' . $system[1] . '. Valid file types are jpg, png and gif.' );

			$md5 = md5( $this->files['image_file']['name'] . time() );
			$name = $md5 . '.' . $system[1];
			$new_fname = $this->gallery_dir . $name;

			if ( !move_uploaded_file( $fname, $new_fname ) )
				return $this->message( 'Upload Image', 'Image failed to upload!' );

			$size = $this->files['image_file']['size'];
			$image = $this->createthumb( $new_fname, $this->thumb_dir . $name, $system[1], $this->settings['gallery_thumb_w'], $this->settings['gallery_thumb_h'] );

			$flags = 0;
			foreach( $this->post['image_flags'] as $flag )
				$flags |= intval($flag);

			chmod( $new_fname, 0644 );
			chmod( $this->thumb_dir . $name, 0644 );

			$this->db->dbquery(
				"INSERT INTO %pphotogallery (photo_user,photo_caption,photo_summary,photo_folder,photo_md5name,photo_type,photo_width,photo_height,photo_size,photo_date,photo_flags,photo_details)
				VALUES (%d, '%s', '%s', %d, '%s', '%s', %d, %d, %d, %d, %d, '%s' )",
				 $this->user['user_id'], $caption, $summary, $folder, $md5, $system[1], $image['width'], $image['height'], $size, $this->time, $flags, $details );

			return $this->message( 'Upload Image', 'Image uploaded.', 'Continue', 'admin.php' );
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

		$xtpl->assign( 'pub', POST_PUBLISHED );
		$xtpl->assign( 'cls', POST_CLOSED );
		$xtpl->assign( 'ovr', POST_OVERRIDE );
		$xtpl->assign( 'mbo', POST_MEMBERSONLY );

		$xtpl->assign( 'clsbox', null );
		$xtpl->assign( 'ovrbox', null );
		$xtpl->assign( 'mbobox', null );
		$xtpl->assign( 'pubbox', ' checked="checked"' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Upload Image' );
		$xtpl->assign( 'action_link', 'admin.php?a=gallery&amp;s=upload' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'folder_select', $this->folder_options( "image_folder" ) );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

		$xtpl->parse( 'ImageForm' );
		return $xtpl->text( 'ImageForm' );
	}

	function delete_image()
	{
		if ( !isset($this->post['submit']) )
		{
			$p = intval($this->get['p']);
			$photo = $this->db->quick_query( 'SELECT photo_id, photo_user, photo_md5name, photo_type
				FROM %pphotogallery WHERE photo_id=%d', $p );
			if ( !$photo )
				return $this->message( 'Delete Image', 'No such image.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=gallery&amp;s=Deleteimage&amp;p=' . $p );
			$xtpl->assign( 'photo_id', $p );
			$xtpl->assign( 'imgsrc', $this->gallery_dir . $photo['photo_md5name'] . '.' . $photo['photo_type'] );

			$xtpl->parse( 'ImageDelete' );
			return $xtpl->text( 'ImageDelete' );
		}

		if ( isset($this->post['p']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$p = intval( $this->post['p'] );

			$photo = $this->db->quick_query( 'SELECT photo_id, photo_user, photo_md5name, photo_type, photo_folder
				FROM %pphotogallery WHERE photo_id=%d', $p );
			if ( !$photo )
				return $this->message( 'Delete Image', 'No such image.' );

			@unlink( $this->thumb_dir . "{$photo['photo_md5name']}.{$photo['photo_type']}" );
			@unlink( $this->gallery_dir . "{$photo['photo_md5name']}.{$photo['photo_type']}" );
			$this->db->dbquery( 'DELETE FROM %pphotogallery WHERE photo_id=%d', $p );
			$this->db->dbquery( 'DELETE FROM %pblogcomments WHERE comment_post=%d AND comment_type=%d', $p, COMMENT_GALLERY );

			$link = 'admin.php?a=gallery&s=delete&f=' . $photo['photo_folder'];
			return $this->message( 'Delete Image', 'Image deleted.', 'Continue', $link );
		}
		return $this->message( 'Delete Image', 'No image selected.' );
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

	function folder_options( $name = 'photo_folder', $root = true, $select_id = -1, $exclude = -1 )
	{
		$out = null;	$folders = array();

		$f_and = null;
		if( $this->user['user_level'] < USER_ADMIN ) {
			if( !$root )
				$f_and = 'AND folder_user=%d';
			else
				$f_and = 'WHERE folder_user=%d OR folder_id=0';
		}

		$result = $this->db->dbquery( 'SELECT folder_id, folder_name, folder_parent
			  FROM %pphotofolders ' . (!$root ? 'WHERE folder_id != 0 ' : null) . $f_and .
		  	  'ORDER BY folder_parent', $this->user['user_id'] );

		while ( $folder = $this->db->assoc($result) )
			if ( $folder['folder_id'] != $exclude )
				$folders[$folder['folder_id']] = array( "name" => $folder['folder_name'], "parent" => $folder['folder_parent'] );
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
			  FROM %pphotofolders ' . (!$root ? 'WHERE folder_id !=0 ' : null) . $f_and .
			  ' ORDER BY folder_parent', $this->user['user_id'] );

		while ( $folder = $this->db->assoc($result) )
			$folders[$folder['folder_id']] = array( 'name' => $folder['folder_name'], 'parent' => $folder['folder_parent'] );

		$links = '';
		foreach( $folders as $id => $folder )
			$links .= "<li><a href=\"{$link}$id\">" . $this->build_foldertree( $id, $folders ) . "</a></li>\n";

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

		$xtpl->assign( 'heading', $header );
		$xtpl->assign( 'links', $links );

		$xtpl->parse( 'FolderPick' );
		return $xtpl->text( 'FolderPick' );
	}

	function folder_form( $header, $action, $folder = array('folder_name' => null, 'folder_summary' => null, 'folder_parent' => 0, 'folder_hidden' => 0) )
	{
		$f = isset($this->get['f']) ? intval($this->get['f']) : null;

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

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
				return $this->error( 'You must specify a folder name.' );

			$this->db->dbquery( "INSERT INTO %pphotofolders (folder_name, folder_summary, folder_parent, folder_user, folder_hidden)
				VALUES( '%s', '%s', %d, %d, %d )", $name, $summary, $parent, $this->user['user_id'], $hidden );

			$this->update_folder_trees();
			return $this->message( 'Create Gallery Folder', 'Folder created.', 'Continue', 'admin.php' );
		}
		return $this->folder_form( 'Create Gallery Folder', 'admin.php?a=gallery&amp;s=createfolder' );
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
		$q = $this->db->dbquery( 'SELECT folder_id, folder_parent FROM %pphotofolders ORDER BY folder_parent' );

		while ($f = $this->db->assoc($q))
		{
			if ($f['folder_parent']) {
				$folders[$f['folder_id']] = $f['folder_parent'];
			}
		}

		// Run through group
		$q = $this->db->dbquery( 'SELECT folder_parent FROM %pphotofolders GROUP BY folder_parent' );

		while ($f = $this->db->assoc($q))
		{
			if ($f['folder_parent']) {
				$tree = $this->buildTree($folders, $f['folder_parent']);
			} else {
				$tree = '';
			}

			$this->db->dbquery( "UPDATE %pphotofolders SET folder_tree='%s' WHERE folder_parent=%d", $tree, $f['folder_parent'] );
		}
	}

	function edit_folder()
	{
		$this->title( 'Edit Gallery Folder' );

		if ( !isset($this->get['f']) && !isset($this->post['f']) )
			return $this->folder_list( 'Edit which gallery folder?', 'admin.php?a=gallery&amp;s=editfolder&amp;f=', false );

		$f = isset($this->get['f']) ? intval($this->get['f']) : intval($this->post['f']);

		$folder = $this->db->quick_query(
			'SELECT folder_id, folder_user, folder_name, folder_summary, folder_parent, folder_hidden
			   FROM %pphotofolders WHERE folder_id=%d', $f );

		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['folder_name'];
			$summary = $this->post['folder_summary'];
			$parent = intval($this->post['folder_parent']);
			$hidden = isset($this->post['folder_hidden']) ? 1 : 0;

			$this->db->dbquery( "UPDATE %pphotofolders SET folder_name='%s', folder_summary='%s', folder_parent=%d, folder_hidden=%d
				WHERE folder_id=%d", $name, $summary, $parent, $hidden, $f );

			$this->update_folder_trees();
			return $this->message( 'Edit Gallery Folder', 'Folder updated.', 'Continue', 'admin.php' );
		}
		return $this->folder_form( 'Edit Gallery Folder', "admin.php?a=gallery&amp;s=editfolder&amp;f=$f", $folder );
	}

	function delete_fold( $f )
	{
		if ( $f == 0 )
			return false;

		$result = $this->db->dbquery( 'SELECT folder_id FROM %pphotofolders WHERE folder_parent=%d', $f );

		while ( $folder = $this->db->assoc( $result ) )
			$this->delete_fold($folder['folder_id']);

		$result = $this->db->dbquery( 'SELECT photo_id FROM %pphotogallery WHERE photo_folder=%d', $f );

		while ( $pic = $this->db->assoc($result) )
			$this->db->dbquery( 'UPDATE %pphotogallery SET photo_folder=0 WHERE photo_id=%d', $pic['photo_id'] );

		$this->db->dbquery( 'DELETE FROM %pphotofolders WHERE folder_id=%d', $f );
		return true;
	}

	function delete_folder()
	{
		if ( !isset($this->get['f']) && !isset($this->post['f']) )
			return $this->folder_list( 'Delete which gallery folder?', 'admin.php?a=gallery&amp;s=deletefolder&amp;f=', false );

		$f = isset($this->get['f']) ? intval($this->get['f']) : intval($this->post['f']);

		$folder = $this->db->quick_query( 'SELECT folder_name, folder_user FROM %pphotofolders WHERE folder_id=%d', $f );

		if( $this->user['user_level'] < USER_ADMIN && $folder['folder_user'] != $this->user['user_id'] )
			return $this->error( 'Access Denied: You do not own the folder you are trying to delete.' );

		if ( !isset($this->post['submit']) )
		{
			$count = $this->db->quick_query( 'SELECT COUNT(photo_id) as count FROM %pphotogallery WHERE photo_folder=%d', $f );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/gallery.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=gallery&amp;s=deletefolder&amp;f=' . $f );
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
			return $this->message( 'Delete Gallery Folder', 'Could not delete folder.' );
		return $this->message( 'Delete Gallery Folder', 'The folder has been deleted.', 'Continue', 'admin.php' );
	}
}
?>