<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) http://www.kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2011
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

class contact extends module
{
	function execute()
	{
		$owner = 'Administrator';
		if( isset($this->settings['site_owner']) )
			$owner = $this->settings['site_owner'];

		$this->title( 'Contact ' . $owner );
		$errors = array();

		$name = '';
		$email = '';
		$subject = '';
		$message = '';

		if( isset( $this->post['name'] ) )
			$name = $this->post['name'];
		if( isset( $this->post['email'] ) )
			$email = $this->post['email'];
		if( isset( $this->post['subject'] ) )
			$subject = $this->post['subject'];
		if( isset( $this->post['comments'] ) )
			$message = $this->post['comments'];

		if ( isset($this->post['submit']) )
		{
			if ( !isset( $this->post['name'] ) || empty($this->post['name']) )
				array_push( $errors, 'You did not enter your name.' );
			if ( !isset( $this->post['email'] ) || !$this->is_email($this->post['email']) )
				array_push( $errors, 'You did not enter a valid email address.' );
			if ( !isset( $this->post['subject'] ) || empty($this->post['subject']))
				array_push( $errors, 'You did not enter a subject.' );
			if ( !isset( $this->post['comments'] ) || empty($this->post['comments']))
				array_push( $errors, 'You did not enter a message.' );
		}

		if ( !isset( $this->post['submit'] ) || count($errors) != 0 )
		{
			$xtpl = new XTemplate( './skins/' . $this->skin . '/contact.xtpl' );

			if( count($errors) > 0 ) {
				$xtpl->assign( 'errors', implode($errors,"<br />\n") );
				$xtpl->parse( 'Contact.Errors' );
			}

			$xtpl->assign( 'owner', $owner );
			$xtpl->assign( 'name', $name );
			$xtpl->assign( 'email', $email );
			$xtpl->assign( 'subject', $subject );
			$xtpl->assign( 'message', $message );

			$xtpl->parse( 'Contact' );
			return $xtpl->text( 'Contact' );
		}

		// I'm not sure if the anti-spam code needs to use the escaped strings or not, so I'll feed them whatever the spammer fed me.
		require_once( './lib/akismet.php' );
		$spam_checked = false;
		$error_state = false;
		$akismet = null;

		if( $this->user['user_level'] < USER_PRIVILEGED ) {
			try {
				$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key']);
				$akismet->setCommentAuthor($this->post['name']);
				$akismet->setCommentAuthorEmail($this->post['email']);
				$akismet->setCommentContent($this->post['comments']);
				$akismet->setCommentType('contact-form');

				$spam_checked = true;
			}
			// Try and deal with it rather than say something.
			catch(Exception $e) {
				$error_state = true;
			}
		} else {
			$spam_checked = true;
		}

		if( $spam_checked && $akismet != null && $akismet->isCommentSpam() )
		{
			$this->settings['email_spam_count']++;
			$this->save_settings();
			return $this->message( 'Akismet Warning', 'Your email has been rejected as spam. If you believe this to be an error, you\'ll need to find some other way to let me know.' );
		}

		if( $error_state ) {
			return $this->message( 'Delivery Failed', 'Email delivery failed. Please try again later.' );
		}

		$headers = "From: {$name} <{$this->settings['email_sys']}>\r\n" .
				   "Reply-To: " . str_replace( "\n", "\\n", $email ) . "\r\n" .
				   "User-IP: " . $this->ip . "\r\n" .
				   "X-Mailer: PHP/" . phpversion();
		mail( $this->settings['email_adm'], '[' . $this->settings['site_name'] . '] ' . str_replace( '\n', '\\n', $subject ), $message, $headers );
		return $this->message( 'Send Email', 'Your message has been sent. You will recieve a reply to ' . htmlspecialchars($email) . ' if the message warrants it.', 'Return to Homepage', 'index.php' );
	}
}
?>