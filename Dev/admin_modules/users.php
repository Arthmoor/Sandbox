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

class users extends module
{
	var $user_groups = array( USER_GUEST => 'Anonymous', USER_VALIDATING => 'Validating', USER_MEMBER => 'Member', USER_PRIVILEGED => 'Privileged', USER_CONTRIBUTOR => 'Contributor', USER_ADMIN => 'Administrator' );

	function execute()
	{
		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'create':	return $this->create_user();
				case 'edit':	return $this->edit_user();
				case 'delete':	return $this->delete_user();
			}

		return $this->list_users();
	}

	function list_users()
	{
		$num = $this->settings['acp_users_per_page'];

		if( isset( $this->get['num'] ) )
			$num = intval( $this->get['num'] );

		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		$users = $this->db->dbquery( 'SELECT user_id, user_name, user_icon, user_email, user_level, user_url, user_joined
		   FROM %pusers
		   ORDER BY user_joined DESC
		   LIMIT %d, %d', $min, $num );

		$total = $this->db->quick_query( 'SELECT COUNT(user_id) count FROM %pusers' );
		$list_total = $total['count'];

		$comments = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/users.xtpl' );

		$xtpl->assign( 'header', 'User List' );

 		while( $user = $this->db->assoc( $users ) )
		{
			$icon_file = $user['user_icon'];
			if( empty($icon_file) )
				$icon_file = 'Anonymous.png';
			$xtpl->assign( 'user_icon', $this->display_icon($icon_file) );

			$xtpl->assign( 'user_id', $user['user_id'] );
			$xtpl->assign( 'user_name', htmlspecialchars($user['user_name']) );
			$xtpl->assign( 'user_email', htmlspecialchars($user['user_email']) );
			$xtpl->assign( 'user_group', $this->user_groups[$user['user_level']] );
			$xtpl->assign( 'user_url', htmlspecialchars($user['user_url']) );
			$xtpl->assign( 'join_date', date( $this->settings['blog_dateformat'], $user['user_joined'] ) );

			$posts = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments WHERE comment_user=%d', $user['user_id'] );
			$xtpl->assign( 'post_count', $posts['count'] );
			if( $comments['count'] > 0 )
				$xtpl->assign( 'percent', floor(( $posts['count'] / $comments['count'] ) * 100) );
			else
				$xtpl->assign( 'percent', 0 );

			$xtpl->parse( 'Users.Member' );
		}

		$pagelinks = $this->make_links( $list_total, $min, $num );

		$xtpl->assign( 'pagelinks', $pagelinks );
		$xtpl->parse( 'Users.PageLinks' );

		$xtpl->parse( 'Users' );
		return $xtpl->text( 'Users' );
	}

	function user_form( $header, $link, $label, $id = -1, $user = array( 'user_perms' => 7, 'user_name' => null, 'user_email' => null, 'user_url' => null, 'user_stylesheet' => null, 'user_icon' => 'Anonymous.png', 'user_signature' => null, 'user_level' => USER_MEMBER ) )
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/user_form.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'link', $link );
		$xtpl->assign( 'header', $header );
		$xtpl->assign( 'user_name', htmlspecialchars($user['user_name']) );
		$xtpl->assign( 'email', htmlspecialchars($user['user_email']) );

		if( $label == 'Edit' ) {
			$xtpl->assign( 'url', htmlspecialchars($user['user_url']) );
			$xtpl->assign( 'sig_text', htmlspecialchars($user['user_signature']) );
			$xtpl->assign( 'icon_file', $this->display_icon( $user['user_icon']) );
			$xtpl->assign( 'stylesheet', htmlspecialchars($user['user_stylesheet']) );

			$xtpl->parse( 'UserForm.Edit' );
		}

		$options = null;
		for( $x = USER_GUEST; $x <= USER_ADMIN; $x++ )
		{
			if( $x == $user['user_level'] )
				$options .= "<option value=\"$x\" selected=\"selected\">{$this->user_groups[$x]}</option>";
			else
				$options .= "<option value=\"$x\">{$this->user_groups[$x]}</option>";
		}
		$xtpl->assign( 'group_options', $options );

		$xtpl->assign( 'perm_url', PERM_URL );
		$xtpl->assign( 'perm_sig', PERM_SIG );
		$xtpl->assign( 'perm_icon', PERM_ICON );

		$xtpl->assign( 'urlbox', $user['user_perms'] & PERM_URL ? ' checked="checked"' : null );
		$xtpl->assign( 'sigbox', $user['user_perms'] & PERM_SIG ? ' checked="checked"' : null );
		$xtpl->assign( 'iconbox', $user['user_perms'] & PERM_ICON ? ' checked="checked"' : null );

		$xtpl->parse( 'UserForm' );
		return $xtpl->text( 'UserForm' );
	}

	function create_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			if ( empty($this->post['user_name']) || empty($this->post['user_email']) )
				return $this->message( 'Create User', 'You must fill in all fields.' );

			if ( !$this->valid_user( $this->post['user_name'] ) )
				return $this->message( 'Create User', 'User name contains illegal characters.' );

			if ( !$this->is_email( $this->post['user_email'] ) )
				return $this->message( 'Create User', 'User email contains illegal charcters.' );

			$name = $this->post['user_name'];
			$exists = $this->db->quick_query( "SELECT user_id, user_name FROM %pusers WHERE user_name='%s'", $name );
			if ( $exists )
				return $this->message( 'Create User', 'User already exists. Do you want to edit them?', 'Edit', "admin.php?a=users&amp;s=edit&amp;user={$exists['user_id']}", 0 );

			$email = $this->post['user_email'];
			$pass = $this->generate_pass(8);
			$dbpass = $this->sandbox_password_hash( $pass );
			$level = intval($this->post['user_level']);
			if( $level < USER_MEMBER || $level > USER_ADMIN )
				$level = USER_MEMBER;

			$perms = 0;
			if( isset( $this->post['user_perms'] ) ) {
				foreach( $this->post['user_perms'] as $flag )
					$perms |= intval($flag);
			}

			$this->db->dbquery( "INSERT INTO %pusers (user_name, user_password, user_email, user_level, user_perms, user_icon, user_joined)
					   VALUES( '%s', '%s', '%s', %d, %d, 'Anonymous.png', %d )", $name, $dbpass, $email, $level, $perms, $this->time );

			$this->settings['user_count']++;
			$this->save_settings();

			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = "New account creation";
			$message = "A new account has been registered for you at {$this->settings['site_name']}: {$this->settings['site_address']}\n\n";
			$message .= "Your user name is: {$this->post['user_name']}\n";
			$message .= "Your temporary password is: $pass\n\n";
			$message .= 'Please write this information down as you will need it in order to log on to the site. You should change this password at your earliest convenience to something you will more easily remember. ';
			$message .= 'You will be able to make any changes to your user profile once you log on the first time.';

			mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
			return $this->message( 'Create User', 'User created. Their password has been mailed to them.', 'Continue', 'admin.php?a=users' );
		}
		return $this->user_form( 'Create User', 'admin.php?a=users&amp;s=create', 'Create' );
	}

	function edit_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['user']) )
		{
			$id = intval( $this->get['user'] );

			$user = $this->db->quick_query( 'SELECT user_name, user_email, user_signature, user_url, user_stylesheet, user_icon, user_level, user_perms FROM %pusers WHERE user_id=%d', $id );
			if( !$user )
				return $this->message( 'Edit User', 'No such user exists.' );

			if ( isset($this->post['submit']) )
			{
				if( !$this->is_valid_token() ) {
					return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
				}

				if ( !$this->is_email( $this->post['user_email'] ) )
					return $this->message( 'Edit User', 'Email contains illegal characters.' );

				$name = $this->post['user_name'];
				$email = $this->post['user_email'];
				$url = ( !stristr( $this->post['user_url'], '://' ) ? 'http://' : null ) . $this->post['user_url'];
				$stylesheet = ( !stristr( $this->post['user_stylesheet'], '://' ) ? 'http://' : null ) . $this->post['user_stylesheet'];

				if( $url == 'http://' )
					$url = '';
				if( $stylesheet == 'http://' )
					$stylesheet = '';

				$sig = $this->post['user_signature'];

				$icon = null;
				if( isset($this->post['user_icon']) )
					$icon = "user_icon='Anonymous.png',";

				$level = intval($this->post['user_level']);
				if( $level < USER_MEMBER || $level > USER_ADMIN )
					$level = USER_MEMBER;

				$perms = 0;
				if( isset( $this->post['user_perms'] ) ) {
					foreach( $this->post['user_perms'] as $flag )
						$perms |= intval($flag);
				}

				$passgen = null;
				if( isset($this->post['user_pass']) ) {
					$pass = $this->generate_pass(8);
					$dbpass = $this->sandbox_password_hash( $pass );
					$passgen = '<br />New password generated and emailed.';

					$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n" . "X-Mailer: PHP/" . phpversion();
					$subject = "Administrative Password Reset";
					$message = "Your password at {$this->settings['site_name']} has been reset by an administrator.\n\n";
					$message .= "Your temporary password is: $pass\n\n";
					$message .= 'Please write this information down as you will need it in order to log on to the site. You should change this password at your earliest convenience to something you will more easily remember.';
					$message .= 'You can change your password via the user profile management screen after logging in.\n';
					$message .= "Site URL: {$this->settings['site_address']}";

					mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

					$this->db->dbquery( "UPDATE %pusers SET user_password='%s', $icon user_name='%s', user_email='%s', user_signature='%s', user_url='%s', user_stylesheet='%s', user_level=%d, user_perms=%d WHERE user_id=%d",
						$dbpass, $name, $email, $sig, $url, $stylesheet, $level, $perms, $id );
				}
				else {
					$this->db->dbquery( "UPDATE %pusers SET $icon user_name='%s', user_email='%s', user_signature='%s', user_url='%s', user_stylesheet='%s', user_level=%d, user_perms=%d WHERE user_id=%d",
						$name, $email, $sig, $url, $stylesheet, $level, $perms, $id );
				}

				return $this->message( 'Edit User', "User edited.$passgen", 'Continue', 'admin.php?a=users' );
			}
			return $this->user_form( 'Edit User', "admin.php?a=users&amp;s=edit&amp;user=$id", 'Edit', $id, $user );
		}
		return $this->list_users();
	}

	function delete_user()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( $this->settings['user_count'] <= 1 )
			return $this->message( 'Delete User', 'You cannot delete the only user left.' );

		if ( isset($this->get['user']) )
		{
			$id = intval( $this->get['user'] );

			$user = $this->db->quick_query( 'SELECT user_name, user_icon FROM %pusers WHERE user_id=%d', $id );
			if( !$user )
				return $this->message( 'Delete User', 'No such user exists.' );

			if ( $this->user['user_id'] == $id )
				return $this->message( 'Delete User', 'You cannot delete yourself.' );

			if( $id == 1 )
				return $this->message( 'Delete User', 'You cannot delete the Anonymous user.' );

			if ( !isset($this->post['submit']) ) {
				$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/user_form.xtpl' );

				$xtpl->assign( 'token', $this->generate_token() );
				$xtpl->assign( 'action_link', 'admin.php?a=users&amp;s=delete&amp;user=' . $id );
				$xtpl->assign( 'user_name', $user['user_name'] );

				$xtpl->parse( 'UserDelete' );
				return $xtpl->text( 'UserDelete' );
			}

			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$this->db->dbquery( 'DELETE FROM %pusers WHERE user_id=%d', $id );
			$this->settings['user_count']--;
			$this->save_settings();

			// Deleting a user is a big deal, but content should be preserved and disposed of at the administration's discretion.
			$this->db->dbquery( 'UPDATE %pspam SET spam_user=1 WHERE spam_user=%d', $id );
			$this->db->dbquery( 'UPDATE %pblogcomments SET comment_user=1 WHERE comment_user=%d', $id );
			$this->db->dbquery( 'UPDATE %pblogposts SET post_user=1, post_flags=%d WHERE post_user=%d', POST_CLOSED, $id );
			$this->db->dbquery( 'UPDATE %pfilelist SET file_user=1, file_flags=%d WHERE file_user=%d', POST_CLOSED, $id );
			$this->db->dbquery( 'UPDATE %pphotogallery SET photo_user=1, photo_flags=%d WHERE photo_user=%d', POST_CLOSED, $id );

			$admin = $this->user['user_id']; // The admin who deleted this user now owns his old folders and custom pages.
			$this->db->dbquery( 'UPDATE %pfilefolders SET folder_user=%d, folder_hidden=1 WHERE folder_user=%d', $admin, $id );
			$this->db->dbquery( 'UPDATE %pphotofolders SET folder_user=%d, folder_hidden=1 WHERE folder_user=%d', $admin, $id );
			$this->db->dbquery( 'UPDATE %ppages SET page_user=%d WHERE page_user=%d', $admin, $id );

			if( $user['user_icon'] != 'Anonymous.png' )
				@unlink( $this->icon_dir . $user['user_icon'] );
			return $this->message( 'Delete User', 'User deleted.', 'Continue', 'admin.php?a=users' );
		}
		return $this->list_users();
	}

	private function make_links( $count, $min, $num )
	{
		if( $num < 1 ) $num = 1; // No more division by zero please.

		$current = ceil( $min / $num );
		$string  = null;
		$pages   = ceil( $count / $num );
		$end     = ($pages - 1) * $num;
		$link = '';

		$link = "{$this->settings['site_address']}admin.php?a=users";

		// check if there's previous articles
		if($min == 0) {
			$startlink = '&lt;&lt;';
			$previouslink = '';
		} else {
			$startlink = "<a href=\"$link&amp;min=0&amp;num=$num\">&lt;&lt;</a>";
			$prev = $min - $num;
			$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num\">prev</a> ";
		}

		// check for next/end
		if(($min + $num) < $count) {
			$next = $min + $num;
  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num\">next</a>";
  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num\">&gt;&gt;</a>";
		} else {
 			$nextlink = '';
  			$endlink = '&gt;&gt;';
		}

		// setup references
		$b = $current - 2;
		$e = $current + 2;

		// set end and beginning of loop
		if ($b < 0) {
  			$e = $e - $b;
  			$b = 0;
		}

		// check that end coheres to the issues
		if ($e > $pages - 1) {
  			$b = $b - ($e - $pages + 1);
  			$e = ($pages - 1 < $current) ? $pages : $pages - 1;
  			// b may need adjusting again
  			if ($b < 0) {
				$b = 0;
			}
		}

 		// ellipses
		if ($b != 0) {
			$badd = '...';
		} else {
			$badd = '';
		}

		if (($e != $pages - 1) && $count) {
			$eadd = '...';
		} else {
			$eadd = '';
		}

		// run loop for numbers to the page
		for ($i = $b; $i < $current; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ($i + 1) . '</a>';
		}

		// add in page
		$string .= ', <strong>' . ($current + 1) . '</strong>';

		// run to the end
		for ($i = $current + 1; $i <= $e; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num\">" . ($i + 1) . '</a>';
		}

		// get rid of preliminary comma.
		if (substr($string, 0, 1) == ',') {
			$string = substr($string, 1);
		}

		if( $pages == 1 ) {
			$string = '';
			$startlink = '';
			$previouslink = '';
			$nextlink = '';
			$endlink = '';
		}

		$newmin = $min + 1;
		$newnum = $min + $num;

		if( $num > $count )
			$newnum = $count;

		if( $min + $num > $count )
			$newnum = $count;

		$showing = "Showing Users $newmin - $newnum of $count ";

		return "$showing $startlink $previouslink $badd $string $eadd $nextlink $endlink";
	}
}
?>