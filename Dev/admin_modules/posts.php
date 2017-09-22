<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) http://www.kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2018
 * Roger Libiez [Samson] https://www.iguanadons.net
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

class posts extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'create':		return $this->create_post();
				case 'edit':		return $this->edit_post();
				case 'del':		return $this->delete_post();
				case 'edit_comment':	return $this->edit_comment();
				case 'del_comment':	return $this->delete_comment();
				case 'ping':		return $this->ping_sites();
				case 'ping_notifications':	return $this->ping_notifications();
			}
		return $this->list_posts();
	}

	// Override the global version with this so that the blog editing screens display the proper status.
	function closed_content( $post, $type = 0 )
	{
		// Manual close. Always return true regardless of other settings.
		if( ( $post['post_flags'] & POST_CLOSED ) )
			return true;

		// Autoclose override - if it's not set, and the time has passed, returns true.
		if( !( $post['post_flags'] & POST_OVERRIDE ) && $this->settings['blog_autoclose'] != 0 ) {
			if( $this->time - $post['post_date'] > 86400 * $this->settings['blog_autoclose'] )
				return true;
		}

		// Not manually closed but set to override, so just return false now.
		return false;
	}

	function list_posts()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_list.xtpl' );

		$posts = $this->db->dbquery( 'SELECT post_id, post_date, post_subject, post_user, post_flags
			  FROM %pblogposts ORDER BY post_date DESC' );

		while ( $post = $this->db->assoc($posts) )
		{
			$author = $this->db->quick_query( 'SELECT user_name FROM %pusers WHERE user_id=%d', $post['post_user'] );

			$xtpl->assign( 'user_name', htmlspecialchars($author['user_name']) );
			$xtpl->assign( 'subject', htmlspecialchars($post['post_subject']) );
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=posts&amp;s=edit&amp;p=' . $post['post_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=posts&amp;s=del&amp;p=' . $post['post_id'] . '">Delete</a>' );

			$status = 'Open';
			if( $this->closed_content( $post, COMMENT_BLOG ) )
				$status = 'Closed';
			$xtpl->assign( 'status', $status );

			$xtpl->assign( 'date', date($this->settings['blog_dateformat'], $post['post_date'] ) );

			$xtpl->parse( 'PostList.Entry' );
		}
		$xtpl->parse( 'PostList' );
		return $xtpl->text( 'PostList' );
	}

	function create_post()
	{
		$errors = array();

		$subject = '';
		$summary = '';
		$text = '';

		$file = null;
		if( isset($this->post['existing_image']) && $this->post['existing_image'] != 'No Image' )
			$file = $this->post['existing_image'];

		if( !$file && isset( $this->files['image_file'] ) && $this->files['image_file']['error'] == UPLOAD_ERR_OK ) {
			$fname = $this->files['image_file']['tmp_name'];
			$system = explode( '.', $this->files['image_file']['name'] );
			$system[1] = strtolower($system[1]);

			if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) ) {
				array_push( $errors, 'Invalid image type ' . $system[1] . '. Valid file types are jpg, png and gif.' );
			} else {
				$new_fname = $this->postimages_dir . $this->files['image_file']['name'];
				if ( !move_uploaded_file( $fname, $new_fname ) )
					array_push( $errors, 'Image failed to upload!' );
				else
					$file = $this->files['image_file']['name'];
			}
		}

		$flags = 0;
		if( isset( $this->post['post_flags'] ) ) {
			foreach( $this->post['post_flags'] as $flag )
				$flags |= intval($flag);
		}

		if( isset( $this->post['post_subject'] ) )
			$subject = $this->post['post_subject'];
		if( isset( $this->post['post_summary'] ) )
			$summary = $this->post['post_summary'];
		if( isset( $this->post['post_text'] ) )
			$text = $this->post['post_text'];

		if ( isset($this->post['submit']) )
		{
			if ( !isset( $this->post['post_subject'] ) || empty($this->post['post_subject']) )
				array_push( $errors, 'You did not enter a subject.' );
			if ( !isset( $this->post['post_summary'] ) || empty($this->post['post_summary']) )
				array_push( $errors, 'You did not enter a post summary.' );
			if ( !isset( $this->post['post_text'] ) || empty($this->post['post_text']))
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && !isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are posting this entry is either invalid or expired. Please try again.' );
		}

		if ( !isset( $this->post['submit'] ) || count($errors) != 0 || isset( $this->post['preview'] ) )
		{
			$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_newpost.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'subject', htmlspecialchars( $subject ) );
			$xtpl->assign( 'summary', htmlspecialchars( $summary ) );
			$xtpl->assign( 'text', htmlspecialchars( $text ) );

			if( $file ) {
				$xtpl->assign( 'image', $this->postimages_dir . $file );
				$xtpl->parse( 'BlogNewPost.Preview.Image' );
			}

			$xtpl->assign( 'html', POST_HTML );
			$xtpl->assign( 'bb', POST_BBCODE );
			$xtpl->assign( 'em', POST_EMOTICONS );
			$xtpl->assign( 'br', POST_BREAKS );
			$xtpl->assign( 'pub', POST_PUBLISHED );
			$xtpl->assign( 'cls', POST_CLOSED );
			$xtpl->assign( 'ovr', POST_OVERRIDE );
			$xtpl->assign( 'mbo', POST_MEMBERSONLY );
			$xtpl->assign( 'cmr', POST_RESTRICTED_COMMENTS );

			if( isset($this->post['post_flags']) ) {
				$xtpl->assign( 'htmlbox', $flags & POST_HTML ? " checked=\"checked\"" : null );
				$xtpl->assign( 'bbbox', $flags & POST_BBCODE ? " checked=\"checked\"" : null );
				$xtpl->assign( 'embox', $flags & POST_EMOTICONS ? " checked=\"checked\"" : null );
				$xtpl->assign( 'brbox', $flags & POST_BREAKS ? " checked=\"checked\"" : null );
				$xtpl->assign( 'pubbox', $flags & POST_PUBLISHED ? " checked=\"checked\"" : null );
				$xtpl->assign( 'clsbox', $flags & POST_CLOSED ? " checked=\"checked\"" : null );
				$xtpl->assign( 'ovrbox', $flags & POST_OVERRIDE ? " checked=\"checked\"" : null );
				$xtpl->assign( 'mbobox', $flags & POST_MEMBERSONLY ? " checked=\"checked\"" : null );
				$xtpl->assign( 'cmrbox', $flags & POST_RESTRICTED_COMMENTS ? " checked=\"checked\"" : null );
			} else {
				$xtpl->assign( 'htmlbox', null );
				$xtpl->assign( 'brbox', null );
				$xtpl->assign( 'clsbox', null );
				$xtpl->assign( 'ovrbox', null );
				$xtpl->assign( 'mbobox', null );
				$xtpl->assign( 'bbbox', ' checked="checked"' );
				$xtpl->assign( 'pubbox', ' checked="checked"' );
				$xtpl->assign( 'embox', ' checked="checked"' );
			}

			$xtpl->assign( 'icon', $this->display_icon($this->user['user_icon']) );
			$xtpl->assign( 'action_link', "admin.php?a=posts&amp;s=create" );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emoticons', $this->bbcode->generate_emote_links() );
			$xtpl->assign( 'existing_images', $this->generate_files_list($file) );

			$result = $this->db->dbquery( 'SELECT cat_id, cat_name FROM %pblogcats ORDER BY cat_name' );

			$pcats = array();
			if( isset($this->post['post_categories']) ) {
				foreach( $this->post['post_categories'] as $cat => $id ) {
					$pcats[] = $id;
				}
			}

			$cats = null;
			while( $cat = $this->db->assoc($result) )
			{
				$cats .= "<option value=\"{$cat['cat_id']}\"" . (in_array( $cat['cat_id'], $pcats ) ? ' selected="selected"' : null) . '>' . htmlspecialchars($cat['cat_name']) . "</option>";
			}
			$xtpl->assign( 'cats', $cats );

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode( $errors, "<br />\n" ) );

				$xtpl->parse( 'BlogNewPost.Errors' );
			}

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_subject', htmlspecialchars($subject) );
				$xtpl->assign( 'preview_text', $this->format( $text, $flags ) );
				$xtpl->parse( 'BlogNewPost.Preview' );
			}

			$xtpl->parse( 'BlogNewPost' );
			return $xtpl->text( 'BlogNewPost' );
		}

		if( !$file )
			$file = '';

		$date = $this->time;
		if ( isset($this->post['post_backdate']) && !empty($this->post['post_backdate']) ) {
			$date = strtotime( $this->post['post_backdate'] );
		}

		if ( isset($this->post['post_newcat']) && !empty($this->post['post_newcat']) )
		{
			$name = $this->post['post_newcat'];
			$existing = $this->db->quick_query( "SELECT cat_id FROM %pblogcats WHERE cat_name='%s'", $name );
			if ( !$existing )
			{
				// Because only site owners can create new categories.
				if( $this->user['user_level'] < USER_ADMIN ) {
					unset($this->post['post_categories']);
				} else {
					$this->db->dbquery( "INSERT INTO %pblogcats (cat_name) VALUES('%s')", $name );
					$this->post['post_categories'][] = $this->db->insert_id();
				}
			}
			else
				$this->post['post_categories'][] = $existing['cat_id'];
		}
		if ( !isset( $this->post['post_categories']) )
			$this->post['post_categories'] = array( 0 );

		$this->db->dbquery( "INSERT INTO %pblogposts (post_subject, post_summary, post_text, post_user, post_date, post_flags, post_image)
			     VALUES ( '%s', '%s', '%s', %d, %d, %d, '%s' )",
				$subject, $summary, $text, $this->user['user_id'], $date, $flags, $file );
		$id = $this->db->insert_id();

		foreach( $this->post['post_categories'] as $cid )
			$this->db->dbquery( 'INSERT INTO %ppostcats (pc_post, pc_cat) VALUES( %d, %d )', $id, $cid );

		$ping_errors = null;
		if( $flags & POST_PUBLISHED && !($flags & POST_MEMBERSONLY) )
			$ping_errors = '<br /><br />' . $this->ping_sites(false) . '<br /><br />';
		return $this->message( 'Post Blog Entry', "{$ping_errors}Blog entry posted.", 'Continue', 'admin.php' );
	}

	function edit_post()
	{
		if ( !isset($this->get['p']) )
			return $this->error( 'No post was specified for editing.' );

		$p = intval($this->get['p']);

		$user = $this->db->quick_query( 'SELECT post_user FROM %pblogposts WHERE post_id=%d', $p );
		if( $user ) {
			if( $this->user['user_id'] != $user['post_user'] && $this->user['user_level'] < USER_ADMIN )
				return $this->error( 'Access Denied: You do not own the blog entry you are attempting to edit.' );
		}

		$errors = array();

		$subject = '';
		$summary = '';
		$text = '';

		$flags = 0;
		if( isset( $this->post['post_flags'] ) ) {
			foreach( $this->post['post_flags'] as $flag )
				$flags |= intval($flag);
		}

		if( isset( $this->post['post_subject'] ) )
			$subject = $this->post['post_subject'];
		if( isset( $this->post['post_summary'] ) )
			$summary = $this->post['post_summary'];
		if( isset( $this->post['post_text'] ) )
			$text = $this->post['post_text'];

		if ( isset($this->post['submit']) )
		{
			if ( !isset( $this->post['post_subject'] ) || empty($this->post['post_subject']) )
				array_push( $errors, 'You did not enter a subject.' );
			if ( !isset( $this->post['post_summary'] ) || empty($this->post['post_summary']) )
				array_push( $errors, 'You did not enter a post summary.' );
			if ( !isset( $this->post['post_text'] ) || empty($this->post['post_text']))
				array_push( $errors, 'You did not enter any text in the body.' );
			if( !$this->is_valid_token() && !isset( $this->post['preview'] ) )
				array_push( $errors, 'The security validation token used to verify you are editing this entry is either invalid or expired. Please try again.' );
		}

		$file = null;
		if( isset($this->post['existing_image']) && $this->post['existing_image'] != 'No Image' ) {
			$file = $this->post['existing_image'];
		}

		if( !$file && isset( $this->files['image_file'] ) && $this->files['image_file']['error'] == UPLOAD_ERR_OK ) {
			$fname = $this->files['image_file']['tmp_name'];
			$system = explode( '.', $this->files['image_file']['name'] );
			$system[1] = strtolower($system[1]);

			if ( !preg_match( '/jpg|jpeg|png|gif/', $system[1] ) ) {
				array_push( $errors, 'Invalid image type ' . $system[1] . '. Valid file types are jpg, png and gif.' );
			} else {
				$new_fname = $this->postimages_dir . $this->files['image_file']['name'];
				if ( !move_uploaded_file( $fname, $new_fname ) )
					array_push( $errors, 'Image failed to upload!' );
				else {
					$file = $this->files['image_file']['name'];
				}
			}
		}

		if ( !isset( $this->post['submit'] ) || count($errors) != 0 || isset( $this->post['preview'] ) )
		{
			$post = $this->db->quick_query( 'SELECT p.*, u.*
				  FROM %pblogposts p
				  LEFT JOIN %pusers u ON u.user_id=p.post_user
				  WHERE post_id=%d', $p );
			if ( !$post )
				return $this->message( 'Edit Blog Entry', 'No such entry.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_editpost.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			if ( !isset( $this->post['post_subject'] ) || empty($this->post['post_subject']) )
				$subject = $post['post_subject'];
			$xtpl->assign( 'subject', htmlspecialchars($subject) );

			if ( !isset( $this->post['post_summary'] ) || empty($this->post['post_summary']) )
				$summary = $post['post_summary'];
			$xtpl->assign( 'summary', htmlspecialchars($summary) );

			if( $file === null ) {
				if( !empty($post['post_image']) ) {
					$file = $post['post_image'];
					$xtpl->assign( 'image', $this->postimages_dir . $post['post_image'] );
					$xtpl->parse( 'BlogEditPost.Preview.Image' );
				}
			} else {
				$xtpl->assign( 'image', $this->postimages_dir . $file );
				$xtpl->parse( 'BlogEditPost.Preview.Image' );
			}
			$xtpl->assign( 'existing_images', $this->generate_files_list($file) );

			if ( !isset( $this->post['post_text'] ) || empty($this->post['post_text']))
				$text = $post['post_text'];
			$xtpl->assign( 'text', htmlspecialchars($text) );

			$xtpl->assign( 'author', htmlspecialchars($post['user_name']) );
			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $post['post_date'] ) );
			$xtpl->assign( 'icon', $this->display_icon($post['user_icon']) );

			$xtpl->assign( 'action_link', "admin.php?a=posts&amp;s=edit&amp;p={$post['post_id']}" );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'emoticons', $this->bbcode->generate_emote_links() );

			$xtpl->assign( 'html', POST_HTML );
			$xtpl->assign( 'bb', POST_BBCODE );
			$xtpl->assign( 'em', POST_EMOTICONS );
			$xtpl->assign( 'br', POST_BREAKS );
			$xtpl->assign( 'pub', POST_PUBLISHED );
			$xtpl->assign( 'cls', POST_CLOSED );
			$xtpl->assign( 'ovr', POST_OVERRIDE );
			$xtpl->assign( 'mbo', POST_MEMBERSONLY );

			$xtpl->assign( 'htmlbox', $post['post_flags'] & POST_HTML ? " checked=\"checked\"" : null );
			$xtpl->assign( 'bbbox', $post['post_flags'] & POST_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $post['post_flags'] & POST_EMOTICONS ? " checked=\"checked\"" : null );
			$xtpl->assign( 'brbox', $post['post_flags'] & POST_BREAKS ? " checked=\"checked\"" : null );
			$xtpl->assign( 'pubbox', $post['post_flags'] & POST_PUBLISHED ? " checked=\"checked\"" : null );
			$xtpl->assign( 'clsbox', $post['post_flags'] & POST_CLOSED ? " checked=\"checked\"" : null );
			$xtpl->assign( 'ovrbox', $post['post_flags'] & POST_OVERRIDE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'mbobox', $post['post_flags'] & POST_MEMBERSONLY ? " checked=\"checked\"" : null );

			$pcats = array();

			$catresult = $this->db->dbquery( 'SELECT c.cat_id, c.cat_name
				  FROM %ppostcats pc
				  LEFT JOIN %pblogcats c ON c.cat_id=pc.pc_cat
			 	  WHERE pc.pc_post=%d', $post['post_id'] );

			while( $cat = $this->db->assoc($catresult) )
				$pcats[] = $cat['cat_id'];

			$result = $this->db->dbquery( 'SELECT cat_id, cat_name FROM %pblogcats ORDER BY cat_name' );

			$cats = null;
			while( $cat = $this->db->assoc($result) )
				$cats .= "<option value=\"{$cat['cat_id']}\"" . (in_array( $cat['cat_id'], $pcats ) ? ' selected="selected"' : null) . '>' . htmlspecialchars($cat['cat_name']) . "</option>";
			$xtpl->assign( 'cats', $cats );

			if( isset( $this->post['preview'] ) ) {
				$xtpl->assign( 'preview_subject', htmlspecialchars($subject) );
				$xtpl->assign( 'preview_text', $this->format( $text, $post['post_flags'] ) );

				$xtpl->parse( 'BlogEditPost.Preview' );
			}

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode( $errors, "<br />\n" ) );
				$xtpl->parse( 'BlogEditPost.Errors' );
			}

			$xtpl->assign( 'comment_list', $this->list_comments( $post['post_id'] ) );
			$xtpl->parse( 'BlogEditPost' );
			return $xtpl->text( 'BlogEditPost' );
		}

		if( !$file )
			$file = '';

		$flags = 0;
		foreach( $this->post['post_flags'] as $flag)
			$flags |= intval($flag);

		$date = strtotime( $this->post['original_date'] );
		if ( isset($this->post['post_backdate']) && !empty($this->post['post_backdate']) ) {
			$date = strtotime( $this->post['post_backdate'] );
		}

		if ( isset($this->post['post_newcat']) && !empty($this->post['post_newcat']) )
		{
			$name = $this->post['post_newcat'];
			$existing = $this->db->quick_query( "SELECT cat_id FROM %pblogcats WHERE cat_name='%s'", $name );
			if ( !$existing )
			{
				// Because only site owners can create new categories.
				if( $this->user['user_level'] < USER_ADMIN ) {
					unset($this->post['post_categories']);
				} else {
					$this->db->dbquery( "INSERT INTO %pblogcats (cat_name) VALUES('%s')", $name );
					$this->post['post_categories'][] = $this->db->insert_id();
				}
			}
			else
				$this->post['post_categories'][] = $existing['cat_id'];
		}
		if ( !isset($this->post['post_categories']) )
			$this->post['post_categories'] = array( 0 );

		$this->db->dbquery( "UPDATE %pblogposts SET post_subject='%s', post_summary='%s', post_text='%s', post_flags=%d, post_date=%d, post_image='%s' WHERE post_id=%d",
					$subject, $summary, $text, $flags, $date, $file, $p );
		$this->db->dbquery( 'DELETE FROM %ppostcats WHERE pc_post=%d', $p );

		foreach( $this->post['post_categories'] as $cid )
			$this->db->dbquery( 'INSERT INTO %ppostcats (pc_post, pc_cat) VALUES( %d, %d )', $p, $cid );

		return $this->message( 'Edit Blog Entry', "Blog entry edited.", 'Continue', 'admin.php?a=posts' );
	}

	function delete_post()
	{
		if ( !isset($this->get['p']) )
			return $this->error( 'No post was specified for deletion.' );

		$p = intval($this->get['p']);

		if( !isset($this->post['confirm'])) {
			$post = $this->db->quick_query( 'SELECT p.*, u.* FROM %pblogposts p
				  LEFT JOIN %pusers u ON u.user_id=p.post_user
				  WHERE post_id=%d', $p );
			if( !$post )
				return $this->message( 'Delete Blog Entry', 'No such blog entry.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_deletepost.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$user = $this->db->quick_query( 'SELECT user_name FROM %pusers WHERE user_id=%d', $post['post_user'] );

			$xtpl->assign( 'action_link', 'admin.php?a=posts&amp;s=del&amp;p=' . $post['post_id'] . '&amp;confirm=1' );
			$xtpl->assign( 'author', htmlspecialchars($user['user_name']) );
			$xtpl->assign( 'subject', htmlspecialchars($post['post_subject']) );
			$xtpl->assign( 'text', $this->format( $post['post_text'], $post['post_flags'] ) );
			$xtpl->assign( 'icon', $this->display_icon($post['user_icon']) );
			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $post['post_date'] ) );

			if( !empty($post['post_image']) ) {
				$xtpl->assign( 'image', $this->postimages_dir . $post['post_image'] );
				$xtpl->parse( 'BlogPostDelete.Image' );
			}

			$count = $post['post_comment_count'];
			$xtpl->assign( 'count', $count );
			$confirm_message = "Are you sure you wish to delete this post";
			if( $count <= 0 )
				$confirm_message .= '?';
			else if( $count == 1 )
				$confirm_message .= ' and 1 attached comment?';
			else
				$confirm_message .= " and ALL $count attached comments?";
			$xtpl->assign( 'confirm_message', $confirm_message );

			$xtpl->parse( 'BlogPostDelete' );
			return $xtpl->text( 'BlogPostDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'The security validation token used to verify you are deleting this entry is either invalid or expired. Please go back and try again.' );
		}

		$post = $this->db->quick_query( 'SELECT post_image FROM %pblogposts WHERE post_id=%d', $p );
		$this->db->dbquery( 'DELETE FROM %pblogposts WHERE post_id=%d', $p );
		$this->db->dbquery( 'DELETE FROM %pblogcomments WHERE comment_post=%d AND comment_type=%d', $p, COMMENT_BLOG );
		$this->db->dbquery( 'DELETE FROM %ppostcats WHERE pc_post=%d', $p );

		if( isset($this->post['image']) ) {
			@unlink( $this->postimages_dir . $post['post_image'] );
		}

		return $this->message( 'Delete Blog Entry', 'Blog entry and all attached comments have been deleted.', 'Continue', 'admin.php?a=posts' );
	}

	function list_comments( $post_id )
	{
		$comments = $this->db->dbquery( 'SELECT c.*, u.user_name FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE comment_post=%d AND comment_type=%d', $post_id, COMMENT_BLOG );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_comment_list.xtpl' );

		while ( $comment = $this->db->assoc($comments) )
		{
			foreach ( $comment as $key => $value )
				$comment[$key] = htmlspecialchars($value);

			$xtpl->assign( 'edit_link', '<a href="admin.php?a=posts&amp;s=edit_comment&amp;p=' . $post_id . '&amp;c='. $comment['comment_id'] . '">Edit Comment</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;p='. $post_id . '&amp;c=' . $comment['comment_id'] . '">Delete Comment</a>' );
			$xtpl->assign( 'spam_link', '<a href="admin.php?a=posts&amp;s=del_comment&amp;t=spam&amp;p=' . $post_id . '&amp;c=' . $comment['comment_id'] . '">Report Spam</a>' );
			$xtpl->assign( 'user_name', htmlspecialchars($comment['user_name']) );
			$xtpl->assign( 'user_ip', $comment['comment_ip'] );
			$xtpl->assign( 'comment_text', htmlspecialchars($comment['comment_message']) );

			$xtpl->parse( 'Comments.Entry' );
 		}
		$xtpl->parse( 'Comments' );
		return $xtpl->text( 'Comments' );
	}

	function edit_comment()
	{
		if( !isset($this->get['c']) )
			return $this->message( 'Edit Comment', 'No comment was specified for editing.' );

		$c = intval($this->get['c']);

		$comment = $this->db->quick_query( 'SELECT c.*, u.* FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE comment_id=%d', $c );

		if( !$comment )
			return $this->message( 'Edit Comment', 'No such comment was found for editing.' );

		$page = '';
		if( $comment['comment_type'] == COMMENT_BLOG )
			$page = 'blog';
		elseif( $comment['comment_type'] == COMMENT_GALLERY )
			$page = 'gallery';
		elseif( $comment['comment_type'] == COMMENT_FILE )
			$page = 'downloads';

		if ( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_comment_edit.xtpl' );

			$xtpl->assign( 'author', htmlspecialchars($comment['user_name']) );

			$message = null;
			$text = null;
			$params = POST_BBCODE | POST_EMOTICONS;
			if( isset($this->post['post_text']) ) {
				$text = $this->post['post_text'];
				$message = $this->format( $this->post['post_text'], $params );
			} else {
				$text = $comment['comment_message'];
				$message = $this->format( $comment['comment_message'], $params );
			}
			$xtpl->assign( 'text', htmlspecialchars($text) );

			$xtpl->assign( 'emoticons', $this->bbcode->generate_emote_links() );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

			$action_link = 'admin.php?a=posts&amp;s=edit_comment&amp;p=' . $comment['comment_post'] . '&amp;c=' . $comment['comment_id'];

			if( isset($this->post['preview']) ) {
				$xtpl->assign( 'icon', $this->display_icon($comment['user_icon']) );
				$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $comment['comment_date'] ) );
				$xtpl->assign( 'message', $message );

				$xtpl->parse( 'Comment.Preview' );
			}
			$xtpl->assign( 'action_link', $action_link );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );

			$xtpl->parse( 'Comment' );
			return $xtpl->text( 'Comment' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if (!isset($this->post['post_text']) || empty($this->post['post_text']) )
			return $this->error( 'You cannot post an empty comment!' );

		$text = $this->post['post_text'];
		$editedby = $this->user['user_name'];

		$this->db->dbquery( "UPDATE %pblogcomments SET comment_editdate=%d, comment_editedby='%s', comment_message='%s' WHERE comment_id=%d",
					$this->time, $editedby, $text, $c );

		return $this->message( 'Edit Comment', 'Comment edited.', 'Continue', 'admin.php?a=posts&s=edit&p=' . $comment['comment_post'] );
	}

	function delete_comment()
	{
		if( !isset($this->get['c']) )
			return $this->message( 'Delete Comment', 'No comment was specified for editing.' );

		$c = intval($this->get['c']);

		$comment = $this->db->quick_query( 'SELECT c.*, u.* FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE comment_id=%d', $c );

		if( !$comment )
			return $this->message( 'Delete Comment', 'No such comment was found for deletion.' );

		if( !isset($this->get['confirm']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/post_comment_edit.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->assign( 'author', htmlspecialchars($comment['user_name']) );
			$params = POST_BBCODE | POST_EMOTICONS;
			$xtpl->assign( 'text', $this->format( $comment['comment_message'], $params ) );
			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $comment['comment_date'] ) );

			$link = 'admin.php?a=posts&s=del_comment&c=' . $c;
			$sp = null;
			if( isset($this->get['t']) && $this->get['t'] == 'spam' ) {
				$link .= '&amp;t=spam';
				$sp = '<br />This comment will be reported as spam.';
			}
			$xtpl->assign( 'action_link', $link );
			$xtpl->assign( 'sp', $sp );

			$xtpl->parse( 'Comment.Delete' );
			return $xtpl->text( 'Comment.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$out = null;

		if( isset($this->get['t']) && $this->get['t'] == 'spam' ) {
			// Time to report the spammer before we delete the comment. Hopefully this is enough info to strike back with.
			require_once( 'lib/akismet.php' );
			$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key']);
			$akismet->setCommentAuthor($comment['user_name']);
			$akismet->setCommentAuthorURL($comment['user_url']);
			$akismet->setCommentContent($comment['comment_message']);
			$akismet->setUserIP($comment['comment_ip']);
			$akismet->setReferrer($comment['comment_referrer']);
			$akismet->setCommentUserAgent($comment['comment_agent']);
			$akismet->setCommentType('comment');

			$akismet->submitSpam();

			$this->settings['spam_count']++;
			$this->settings['spam_uncaught']++;
			$this->save_settings();

			$out .= 'Comment tagged as spam and reported.<br />';
		}

		$this->db->dbquery( 'DELETE FROM %pblogcomments WHERE comment_id=%d', $c );
		if( $comment['comment_type'] == COMMENT_BLOG ) {
			$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=post_comment_count-1 WHERE post_id=%d', $comment['comment_post'] );
		} elseif( $comment['comment_type'] == COMMENT_GALLERY ) {
			$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=photo_comment_count-1 WHERE photo_id=%d', $comment['comment_post'] );
		} elseif( $comment['comment_type'] == COMMENT_FILE ) {
			$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=file_comment_count-1 WHERE file_id=%d', $comment['comment_post'] );
		}
		$out .= 'Comment has been deleted.';

		return $this->message( 'Delete Comment', $out, 'Continue', "admin.php?a=posts&s=edit&p={$comment['comment_post']}" );
	}

	function ping_sites($report = true)
	{
		if( !extension_loaded('xmlrpc'))
		{
			return $this->message( 'Ping Services', 'php5-xmlrpc is not available. This function is disabled.', 'Continue', 'admin.php' );
		}

		if( !isset( $this->settings['site_pings'] ) )
		{
			if( $report )
				return $this->message( 'Ping Services', 'There are no ping services setup yet.', 'Continue', 'admin.php' );
			else
				return '';
		}

		// In case of errors, they need to be reported to the user. Successes also reported by site.
		$errors = array();

		foreach( $this->settings['site_pings'] as $ping )
		{
			$ping = trim($ping);

			$request = xmlrpc_encode_request( 'weblogUpdates.ping', array( $this->settings['site_name'], $this->settings['site_address'] ) );
			$context = stream_context_create( array('http' => array( 'method' => "POST", 'header' => "Content-Type: text/xml", 'content' => $request ) ) );

			$file = file_get_contents( $ping, false, $context );
			$response = xmlrpc_decode( $file );
			if( $response && xmlrpc_is_fault($response) ) {
				array_push( $errors, "ERROR: $ping - " . $response[faultCode] . "<br />" );
			} else {
				array_push( $errors, "Pinged $ping successfully.<br />" );
			}
		}

		return implode( $errors, '<br />' );
	}

	function ping_notifications()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( !extension_loaded('xmlrpc'))
		{
			return $this->message( 'Ping Services', 'php5-xmlrpc is not available. This function is disabled.', 'Continue', 'admin.php' );
		}

		if( !isset($this->post['submit']))
		{
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/pings.xtpl' );

			$links = null;
			if( isset($this->settings['site_pings']) )
				$links = implode("\n", $this->settings['site_pings']);
			$xtpl->assign( 'links', $links );

			$xtpl->parse( 'Pings' );
			return $xtpl->text( 'Pings' );
		}
		$links = trim($this->post['ping_sites']);
		if ( $links )
			$links = explode("\n", $links);
		else
			$links = array();
		$this->settings['site_pings'] = $links;
		$this->save_settings();
		return $this->message( 'Pings and Notifications', 'Notification URLs Updated.', 'Continue', 'admin.php' );
	}
}
?>