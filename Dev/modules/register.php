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

require_once './lib/sidebar.php';

class register extends module
{
	function execute()
	{
		if (!isset($this->get['s'])) {
			$this->get['s'] = null;
		}

		switch( $this->get['s'] )
		{
			case 'validateaccount':
				return $this->validate_user();
				break;

			case 'forgotpassword':
				return $this->forgot_password();
				break;

			case 'resetpassword':
				return $this->reset_password();
				break;
		}

		if (!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/register.xtpl' );

			$SideBar = new sidebar($this);
			$xtpl->assign( 'sidebar', $SideBar->build_sidebar() );
			$xtpl->assign( 'token', $this->generate_token() );

			if( !empty($this->settings['wordpress_api_key']) ) {
				$xtpl->parse( 'Registration.Akismet' );
			}

			$type = mt_rand(1,3);
			$num1 = mt_rand();
			$num2 = mt_rand();
			$answer = 0;

			switch( $type )
			{
				case 1: $answer = $num1 + $num2; $op = '+'; break;
				case 2: $answer = $num1 - $num2; $op = '-'; break;
				case 3: $answer = $num1 * $num2; $op = '*'; break;
			}
			$_SESSION['answer'] = $answer;

			$xtpl->assign( 'prompt', "What is $num1 $op $num2 ?" );

			$xtpl->parse( 'Registration' );
			return $xtpl->text( 'Registration' );
		}

		if( !$this->is_valid_token() ) {
			return $this->message( 'New User Registration', 'Cookies are not being accepted by your browser. Please adjust your privacy settings, then go back and try again.' );
		}

		if ( !isset( $this->post['user_name'] ) || !$this->valid_user( $this->post['user_name'] ) )
			return $this->message( 'New User Registration', 'User name contains illegal characters.' );

		if ( !isset( $this->post['user_email'] ) || !$this->is_email( $this->post['user_email'] ) )
			return $this->message( 'New User Registration', 'User email contains illegal characters.' );

		if ( !isset( $this->post['user_math'] ) )
			return $this->message( 'New User Registration', 'You failed to correctly answer the math question. Please try again.' );

		$name = $this->post['user_name'];
		$email = $this->post['user_email'];
		$url = $this->post['user_url'];
		$math = $this->post['user_math'];

		if( $math != $_SESSION['answer'] )
			return $this->message( 'New User Registration', 'You failed to correctly answer the math question. Please try again.' );

		$prev_user = $this->db->quick_query( "SELECT user_id FROM %pusers WHERE user_name='%s'", $name );
		if( $prev_user )
			return $this->message( 'New User Registration', 'A user by that name has already registered here.' );

		$prev_email = $this->db->quick_query( "SELECT user_id FROM %pusers WHERE user_email='%s'", $email );
		if( $prev_email )
			return $this->message( 'New User Registration', 'A user with that email address has already registered here.' );

		require_once( 'lib/akismet.php' );
		$spam_checked = false;

		try {
			$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->version);

			$akismet->setCommentAuthor($this->post['user_name']);
			$akismet->setCommentAuthorEmail($this->post['user_email']);
			$akismet->setCommentAuthorURL($this->post['user_url']);
			$akismet->setCommentContent($this->post['user_regcomment']);
			$akismet->setCommentType('signup');

			$spam_checked = true;
		}
		// Try and deal with it rather than say something.
		catch(Exception $e) {}

		if( $spam_checked && $akismet->isCommentSpam() ) {
			$this->settings['register_spam_count']++;
			$this->save_settings();
			return $this->message( 'Registration Failure', 'Information provided during registration has been flagged by Akismet as a spam source. You will need to find another means of contacting the administration if you wish to register.' );
		}

		if( $this->post['user_pass'] != $this->post['user_passconfirm'] )
			return $this->message( 'Registration Failure', 'Your password does not match the confirmation field. Please go back and try again.' );

		$dbpass = $this->sandbox_password_hash( $this->post['user_pass'] );

		$this->settings['user_count']++;
		$this->save_settings();

		$jointime = $this->time;

		if( isset( $this->settings['validate_users'] ) && $this->settings['validate_users'] == 1 ) {
			$level = USER_VALIDATING;
		} else {
			$level = USER_MEMBER;
		}

		$perms = PERM_URL | PERM_SIG | PERM_ICON;

		$this->db->dbquery( "INSERT INTO %pusers (user_name, user_password, user_email, user_url, user_level, user_perms, user_joined)
				   VALUES( '%s', '%s', '%s', '%s', %d, %d, %d )", $name, $dbpass, $email, $url, $level, $perms, $jointime );
		$id = $this->db->insert_id();

		setcookie($this->settings['cookie_prefix'] . 'user', $id, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
		setcookie($this->settings['cookie_prefix'] . 'pass', $dbpass, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		if( isset( $this->settings['validate_users'] ) && $this->settings['validate_users'] == 1 ) {
			$this->send_user_validation_email( $email, $name, $dbpass, $jointime, true );

			return $this->message( 'New User Registration', 'Your account has been created. Email validation is required. A link has been sent to your email address to validate your account.', 'Continue', '/' );
		}
		return $this->message( 'New User Registration', 'Your account has been created.', 'Continue', '/' );
	}

	function send_user_validation_email( $email, $name, $dbpass, $jointime, $newaccount )
	{
		$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
		$subject = 'User Account Validation';
		$message = "An email validation has been initiated for your user account at {$this->settings['site_name']}: {$this->settings['site_address']}\n\n";
		$message .= "Your user name is: {$this->post['user_name']}\n";
		$message .= "Click on the following link to validate your account: {$this->settings['site_address']}index.php?a=register&s=validateaccount&e=" . md5($email . $name . $dbpass . $jointime) . "\n\n";

		mail( $this->post['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

		if( $newaccount ) {
			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = 'New user signup';
			$message = "A new user has signed up at {$this->settings['site_name']} named {$this->post['user_name']}\n";

			mail( $this->settings['email_adm'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
		}
	}

	function validate_user()
	{
		if (isset($this->get['e'])) {
			$user = $this->db->quick_query( "SELECT user_id, user_level FROM %pusers WHERE MD5(CONCAT(user_email, user_name, user_password, user_joined))='%s' LIMIT 1", $this->get['e'] );

			if( $user && $user['user_id'] != USER_GUEST && $user['user_level'] == USER_VALIDATING ) {
				$this->db->dbquery( 'UPDATE %pusers SET user_level=%d WHERE user_id=%d', USER_MEMBER, $user['user_id'] );
				return $this->message( 'User Account Validation', 'Your account has been validated.', 'Continue', '/' );
			}
		}

		return $this->message( 'User Account Validation', 'There was an error during validation. Please make sure you have used the correct validation link that was sent to you.', 'Continue', '/' );
	}

	function forgot_password()
	{
		if (!isset($this->post['submit'])) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/register.xtpl' );

			$SideBar = new sidebar($this);
			$xtpl->assign( 'sidebar', $SideBar->build_sidebar() );
			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->assign( 'action_url', "{$this->settings['site_address']}index.php?a=register&amp;s=forgotpassword" );

			$xtpl->parse( 'LostPassword' );
			return $xtpl->text( 'LostPassword' );
		} else {
			if( !$this->is_valid_token() ) {
				return $this->message( 'Lost Password Recovery', 'Session security token has expired. Please return to the homepage and try again.' );
			}

			$target = $this->db->quick_query( "SELECT user_id, user_name, user_password, user_joined, user_email
				FROM %pusers WHERE user_name='%s' AND user_id != %d LIMIT 1", $this->post['user_name'], USER_GUEST );

			if (!isset($target['user_id'])) {
				return $this->message( 'Lost Password Recovery', 'No such user exists at this site.' );
			}

			$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
			$subject = 'Lost Password Recovery';

			$message  = "{$target['user_name']}:\n\n";
			$message .= "Someone has requested a password recovery for your account at {$this->settings['site_name']}.\n";
			$message .= "If you do not want to recover a lost password, please ignore or delete this email.\n\n";
			$message .= "Go to the below URL to continue with the password recovery:\n";
			$message .= "{$this->settings['site_address']}index.php?a=register&s=resetpassword&e=" . md5($target['user_email'] . $target['user_name'] . $target['user_password'] . $target['user_joined']) . "\n\n";
			$message .= "Requested from IP: {$this->ip}";

			mail( $target['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

			return $this->message( 'Lost Password Recovery', "Lost password recovery request for user {$this->post['user_name']} has been emailed to the registered address with instructions." );
		}
	}

	function reset_password()
	{
		if (!isset($this->get['e'])) {
			$this->get['e'] = null;
		}

		$target = $this->db->quick_query( "SELECT user_id, user_name, user_email FROM %pusers
			WHERE MD5(CONCAT(user_email, user_name, user_password, user_joined))='%s' AND user_id != %d LIMIT 1",
			 preg_replace('/[^a-z0-9]/', '', $this->get['e']), USER_GUEST);
		if (!isset($target['user_id'])) {
			return $this->message( 'Lost Password Recovery', 'No such user exists at this site.' );
		}

		$newpass = $this->generate_pass(8);
		$dbpass = $this->sandbox_password_hash( $newpass );

		$headers = "From: {$this->settings['site_name']} <{$this->settings['email_sys']}>\r\n" . "X-Mailer: PHP/" . phpversion();
		$subject = 'Lost Password Recovery - New Password';

		$message  = "{$target['user_name']}:\n\n";
		$message .= "You have completed a password recovery for your account at {$this->settings['site_name']}.\n";
		$message .= "Your new password is: {$newpass}\n\n";
		$message .= "It is strongly advised to log on and change this to something more secure via your profile management screen.\n\n";
		$message .= "If you are receiving this message but did NOT request a password recovery, please contact the site administrator immediately to report a security issue.";

		mail( $target['user_email'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );

		$this->db->dbquery( "UPDATE %pusers SET user_password='%s' WHERE user_id=%d", $dbpass, $target['user_id'] );

		return $this->message( 'Lost Password Recovery' , 'Password recovery complete. A new password has been sent to your email address.' );
	}
}
?>