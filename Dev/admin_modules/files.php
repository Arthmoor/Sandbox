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

if ( !defined('SANDBOX') || !defined('SANDBOX_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class files extends module
{
	var $upload_dir;

	function execute()
	{
		$this->upload_dir = 'files/';

		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'upload':	return $this->upload_file();
				case 'del':	return $this->delete_file();
			}
		return $this->list_files();
	}

	function list_files()
	{
		$files = array();

		$dp  = opendir($this->upload_dir);
		while (false !== ($filename = readdir($dp)))
			if ( !is_dir($this->upload_dir . $filename) )
			   $files[] = $filename;
		closedir($dp);

		if( !empty($files) )
			sort($files);

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/files.xtpl' );

		foreach( $files as $f )
		{
			if ( $f == '.' || $f == '..' )
				continue;
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=files&amp;s=del&amp;f=' . $f . '">Delete</a>' );
			$xtpl->assign( 'filename', htmlspecialchars($f) );
			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->parse( 'Files.Entry' );
		}

		$xtpl->parse( 'Files' );
		return $xtpl->text( 'Files' );
	}

	function upload_file()
	{
		$this->title( 'File Upload' );

		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$fname = $this->files['upload_file']['tmp_name'];
			if ( move_uploaded_file($fname, $this->upload_dir . basename($this->files['upload_file']['name']) ) )
				return $this->message( 'File Upload', 'File uploaded successfully.', 'Continue', 'admin.php?a=files' );
			return $this->message( 'Uploading File', 'File failed to upload.' );
		}
		return $this->list_files();
	}

	function delete_file()
	{
		$this->title( 'Deleting File' );

		if( !isset($this->get['f']) && !isset($this->post['f']) )
			return $this->list_files( 'Delete which file?', 'admin.php?a=files&amp;s=del' );

		$f = isset($this->get['f']) ? $this->get['f'] : $this->post['f'];

		$path = $this->upload_dir . $f;

		if ( dirname($path) != 'files' )
			return $this->message( 'Deleting File', 'Security breach detected.' );

		if ( !file_exists( $path ) )
			return $this->message( 'Deleting File', 'File does not exist.' );

		if ( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/files.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=files&amp;s=del&amp;f=' . $f );
			$xtpl->assign( 'file_name', htmlspecialchars($f) );

			$xtpl->parse( 'FileDelete' );
			return $xtpl->text( 'FileDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if ( !@unlink( $path ) )
			return $this->message( 'Deleting File', 'Could not delete file.' );
		return $this->message( 'Deleting File', 'The file was deleted.', 'Continue', 'admin.php?a=files' );
	}
}
?>