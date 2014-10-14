<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) http://www.kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2012
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

class emoticons extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if (!isset($this->get['s'])) {
			$this->get['s'] = null;
		}

		switch($this->get['s'])
		{
		case null:
		case 'edit':
			$edit_id = isset($this->get['edit']) ? intval($this->get['edit']) : 0;
			$delete_id = isset($this->get['delete']) ? intval($this->get['delete']) : 0;

			if (isset($this->get['delete'])) {
				$this->db->dbquery('DELETE FROM %pemoticons WHERE emote_id=%d', $delete_id );
			}

			if (!isset($this->get['edit'])) {
				$this->get['edit'] = null;
			}

			if (isset($this->post['submit']) && (trim($this->post['new_string']) != '') && (trim($this->post['new_image']) != '')) {
				$new_click = intval( isset($this->post['new_click']) );
				$this->db->dbquery("UPDATE %pemoticons SET emote_string='%s', emote_image='%s', emote_clickable=%d WHERE emote_id=%d",
					$this->post['new_string'], $this->post['new_image'], $new_click, $edit_id );
				$this->get['edit'] = null;
			}

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/emoticons.xtpl' );

			$query = $this->db->dbquery('SELECT * FROM %pemoticons ORDER BY emote_clickable,emote_string ASC');
			while ($data = $this->db->assoc($query))
			{
				$xtpl->assign( 'em_id', $data['emote_id'] );

				$em_string = $data['emote_string'];
				$xtpl->assign( 'em_string', $em_string );

				$em_image = $data['emote_image'];
				$xtpl->assign( 'em_image', $em_image );

				$xtpl->assign( 'em_clickable', 'Yes' );
				if( $data['emote_clickable'] == 0 )
					$xtpl->assign( 'em_clickable', 'No' );

				$xtpl->assign( 'em_edit', '<a href="' . $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=edit&amp;edit=' . $data['emote_id'] . '">Edit</a>' );
				$xtpl->assign( 'em_delete', '<a href="' . $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=edit&amp;delete=' . $data['emote_id'] . '">Delete</a>' );

				if ( !$this->get['edit'] || ($edit_id != $data['emote_id']) ) {
					$xtpl->assign( 'img_src', '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $em_image . '" alt="' . $em_string . '" />' );

					$xtpl->parse( 'Emoticons.SingleEntryDisplay' );
				} else {
					$checked = '';
					if( $data['emote_clickable'] == 1 )
						$checked = 'checked';
					$xtpl->assign( 'checked', $checked );

					$xtpl->assign( 'img_src', '<img name="emot_preview" src="' . $this->settings['site_address'] . 'files/emoticons/' . $em_image . '" alt="' . $em_string . '" />' );
					$xtpl->assign( 'em_list', $this->list_emoticons( $em_image ) );

					$xtpl->parse( 'Emoticons.SingleEntryEdit' );
				}
			}

			$xtpl->assign( 'form_action', $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=edit&amp;edit=' . $edit_id );
			$xtpl->assign( 'add_form_action', $this->settings['site_address'] . 'admin.php?a=emoticons&amp;s=add' );
			$xtpl->assign( 'em_add_list', $this->list_emoticons( 'New' ) );

			$xtpl->parse( 'Emoticons' );
			return $xtpl->text( 'Emoticons' );
			break;

		case 'add':
			if (!isset($this->post['submit'])) {
				$this->get['s'] = null;
				$this->execute();
				return;
			} else {
				$new_clickable = intval( isset($this->post['new_click']) );
				$new_string = isset($this->post['new_string']) ? $this->post['new_string'] : '';

				if (trim($new_string) == '') {
					return $this->error('Add New Emoticon', 'No emoticon text was given.');
				}

				$new_image = '';
				if( $this->post['existing_image'] != 'New' )
					$new_image = $this->post['existing_image'];
				else {
					if( isset( $this->files['new_image'] ) && $this->files['new_image']['error'] == UPLOAD_ERR_OK ) {
						$fname = $this->files['new_image']['tmp_name'];
						$system = explode( '.', $this->files['new_image']['name'] );
						$ext = strtolower(end($system));

						if ( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
							return $this->error( 'Add New Emoticon', sprintf('Invalid image type %s. Valid file types are jpg, png and gif.', $ext) );
						} else {
							$new_fname = $this->emote_dir . $this->files['new_image']['name'];
							if ( !move_uploaded_file( $fname, $new_fname ) )
								return $this->error( 'Add New Emoticon', 'Image failed to upload!' );
							else
								$new_image = $this->files['new_image']['name'];
						}
					}
				}

				$this->db->dbquery("INSERT INTO %pemoticons (emote_string, emote_image, emote_clickable) VALUES ('%s', '%s', %d )", $new_string, $new_image, $new_clickable );

				return $this->message( 'Add New Emoticon', 'Emoticon added.', 'Back to Emoticon Controls', $this->settings['site_address'] . 'admin.php?a=emoticons' );
			}
			break;
		}
	}

	function list_emoticons($select)
	{
		$dirname = $this->emote_dir;

		$out = null;
		$files = array();

		if( $select == 'New' )
			$out .= '\n<option value="New" selected="selected">New</option>';

		$dir = opendir($dirname);
		while (($emo = readdir($dir)) !== false)
		{
			$ext = substr($emo, -3);
			if (($ext != 'png')
			&& ($ext != 'gif')
			&& ($ext != 'jpg')) {
				continue;
			}

			if (is_dir($dirname . $emo)) {
				continue;
			}

			$files[] = $emo;
		}

		sort($files);

		foreach( $files as $key => $name ) {
			$out .= "\n<option value='$name'" . (($name == $select) ? ' selected' : '') . ">$name</option>";
		}
		return $out;
	}
}
?>