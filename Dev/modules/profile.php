<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) https://kiasyn.com
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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once './lib/sidebar.php';

class profile extends module
{
	function select_input( $name, $value, $values = array() )
	{
		$out = null;
		foreach( $values as $key )
			$out .= '<option' . ($key == $value ? ' selected="selected"' : '') . ">$key</option>";
		return "<select name=\"$name\">$out</select>";
	}

	function execute()
	{
		if( $this->user['user_level'] == USER_GUEST ) {
			return $this->error( 'Access Denied: You do not have permission to perform that action.', 403 );
		}

		$errors = array();

		$email = $this->user['user_email'];
		$sig = $this->user['user_signature'];
		$url = $this->user['user_url'];
		$stylesheet = $this->user['user_stylesheet'];
		$gravatar = null;
		$newtz = $this->user['user_timezone'];

		if( $this->is_email($this->user['user_icon']) )
			$gravatar = $this->user['user_icon'];

		if( isset($this->post['user_email']) )
			$email = $this->post['user_email'];
		if( isset($this->post['user_sig']) )
			$sig = $this->post['user_sig'];
		if( isset($this->post['user_url']) )
			$url = $this->post['user_url'];
		if( isset($this->post['user_stylesheet']) )
			$stylesheet = $this->post['user_stylesheet'];
		if( isset($this->post['user_timezone']) )
			$newtz = $this->post['user_timezone'];

		if( isset($this->post['submit']) ) {
			if( isset($this->post['user_email']) && !empty($this->post['user_email']) ) {
				if( !$this->is_email($this->post['user_email']) )
					array_push( $errors, 'You did not enter a valid email address.' );
			}

			if( isset($this->post['user_gravatar']) && !empty($this->post['user_gravatar']) ) {
				if( !$this->is_email($this->post['user_gravatar']) )
					array_push( $errors, 'You did not specify a valid Gravatar email address.' );
			}

			if( isset($this->post['user_password']) && isset($this->post['user_pass_confirm']) ) {
				if( $this->post['user_password'] != $this->post['user_pass_confirm'] )
					array_push( $errors, 'Entered passwords do not match.' );
			}
			if( !$this->is_valid_token() )
				array_push( $errors, 'The security validation token used to verify you are making this change is either invalid or expired. Please try again.' );
		}

		$icon = null;
		$old_icon = $this->user['user_icon'];
		if( !isset( $this->post['user_gravatar'] ) || empty($this->post['user_gravatar']) ) {
			if( isset( $this->files['user_icon'] ) && $this->files['user_icon']['error'] == UPLOAD_ERR_OK )	{
				$fname = $this->files['user_icon']['tmp_name'];
				$system = explode( '.', $this->files['user_icon']['name'] );
				$ext = strtolower(end($system));

				if ( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
					array_push( $errors, 'Invalid icon file type ' . $ext . '. Valid file types are jpg, png and gif.' );
				} else {
					$icon = $this->user['user_name'] . '.' . $ext;
					$new_fname = $this->icon_dir . $this->user['user_name'] . '.' . $ext;

					if ( !move_uploaded_file( $fname, $new_fname ) ) {
						array_push( $errors, 'Post icon failed to upload!' );
					} else {
						$this->createthumb( $new_fname, $new_fname, $ext, $this->settings['blog_icon_width'], $this->settings['blog_icon_height'] );

						if( $old_icon != 'Anonymous.png' )
							@unlink( $this->icon_dir . $old_icon );
					}
				}
			} else {
				$icon = $old_icon;
			}
		} else {
			if( $this->is_email($this->post['user_gravatar']) ) {
				$icon = $this->post['user_gravatar'];

				if( $old_icon != 'Anonymous.png' )
					@unlink( $this->icon_dir . $old_icon );
			} else {
				$icon = $old_icon;
			}
		}

		if( $this->settings['friendly_urls'] )
			$action_link = $this->settings['site_address'] . 'profile';
		else
			$action_link = "{$this->settings['site_address']}index.php?a=profile";

		if( !isset($this->post['submit']) || count($errors) != 0 ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/profile.xtpl' );

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode($errors,"<br />\n") );
				$xtpl->parse( 'Profile.Errors' );
			}

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', $action_link );
			$xtpl->assign( 'name', htmlspecialchars($this->user['user_name']) );
			$xtpl->assign( 'email', htmlspecialchars($email) );
			$xtpl->assign( 'sig', htmlspecialchars($sig) );
			$xtpl->assign( 'url', htmlspecialchars($url) );
			$xtpl->assign( 'icon', $this->display_icon( $icon ) );
			$xtpl->assign( 'timezone', $this->select_timezones( $this->user['user_timezone'], 'user_timezone' ) );
			$xtpl->assign( 'gravatar', htmlspecialchars($gravatar) );
			$xtpl->assign( 'skin', $this->select_input( 'user_skin', $this->skin, $this->get_skins() ) );
			$params = POST_BBCODE | POST_EMOTICONS;
			$xtpl->assign( 'sigdisplay', $this->format( $sig, $params ) );

			$xtpl->assign( 'date', $this->t_date( $this->user['user_joined'] ) );
			$level = $this->user['user_level'];

			$comments = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments' );
			$posts = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments WHERE comment_user=%d', $this->user['user_id'] );
			$xtpl->assign( 'count', intval($posts['count']) );
			if( $comments['count'] > 0 )
				$xtpl->assign( 'percent', floor(( $posts['count'] / $comments['count'] ) * 100) );
			else
				$xtpl->assign( 'percent', 0 );

			if( $level == USER_CONTRIBUTOR || $level == USER_ADMIN ) {
				$blogposts = $this->db->quick_query( 'SELECT COUNT(post_id) count FROM %pblogposts' );
				$blogpostcount = $this->db->quick_query( 'SELECT COUNT(post_id) count FROM %pblogposts WHERE post_user=%d', $this->user['user_id'] );
				$xtpl->assign( 'blogcount', intval($blogpostcount['count']) );

				if( $blogposts['count'] > 0 )
					$xtpl->assign( 'blogpercent', floor(( $blogpostcount['count'] / $blogposts['count'] ) * 100) );
				else
					$xtpl->assign( 'blogpercent', 0 );

				$xtpl->parse( 'Profile.Contributor' );
			}

			$xtpl->assign( 'width', $this->settings['blog_icon_width'] );
			$xtpl->assign( 'height', $this->settings['blog_icon_height'] );
 
			if( $this->user['user_level'] > USER_MEMBER ) {
				$xtpl->assign( 'stylesheet', htmlspecialchars($stylesheet) );

				$xtpl->parse( 'Profile.Stylesheet' );
			}

			$SideBar = new sidebar($this);
			$xtpl->assign( 'sidebar', $SideBar->build_sidebar() );

			$xtpl->parse( 'Profile' );
			return $xtpl->text( 'Profile' );
		}

		$skins = $this->get_skins();
		if( in_array( $this->post['user_skin'], $this->skins ) ) {
			setcookie($this->settings['cookie_prefix'] . 'skin', $this->post['user_skin'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			$this->skin = $this->post['user_skin'];
		}

		$url = ( !stristr( $url, '://' ) ? 'http://' : null ) . $url;
		if( $url == 'http://' || $url == 'https://' )
			$url = '';
		$stylesheet = '';
		if( isset($this->post['user_stylesheet']) && $this->user['user_level'] > USER_MEMBER )
			$stylesheet = $this->post['user_stylesheet'];

		if( !empty( $this->post['user_password'] ) && !empty( $this->post['user_pass_confirm'] ) ) {
			$newpass = $this->sandbox_password_hash( $this->post['user_password'] );

			$this->db->dbquery( "UPDATE %pusers SET user_email='%s', user_url='%s', user_stylesheet='%s', user_icon='%s', user_signature='%s', user_password='%s', user_timezone='%s' WHERE user_id=%d",
				$email, $url, $stylesheet, $icon, $sig, $newpass, $newtz, $this->user['user_id'] );

			$action_link = '/';
		}
		else {
			$this->db->dbquery( "UPDATE %pusers SET user_email='%s', user_url='%s', user_stylesheet='%s', user_icon='%s', user_signature='%s', user_timezone='%s' WHERE user_id=%d",
				$email, $url, $stylesheet, $icon, $sig, $newtz, $this->user['user_id'] );
		}
		return $this->message( 'Edit Your Profile', 'Your profile has been updated.', 'Continue', $action_link );
	}
}
?>