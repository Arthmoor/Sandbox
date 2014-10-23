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

class spam extends module
{
	function execute()
	{
		$svars = array();
		$this->title( 'Spam Control' );

		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'keytest':		return $this->test_akismet_key();
			}

		if( !isset($this->get['p'])) {
			return $this->display_spam_comments();
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$p = intval($this->get['p']);

		if( $p == 0 ) {
			$this->db->dbquery( 'TRUNCATE TABLE %pspam' );
			return $this->message( 'Spam Control', 'All entries in the spam table have been cleared.', 'Continue', 'admin.php?a=spam' );
		}

		$spam = $this->db->quick_query( 'SELECT s.*, u.user_name, u.user_url, u.user_id FROM %pspam s
					LEFT JOIN %pusers u ON u.user_id=s.spam_user WHERE spam_id=%d', $p );
		if( !$spam )
			return $this->message( 'Spam Control', 'There is no such spam comment.', 'Continue', 'admin.php?a=spam' );

		$out = '';
		if( !isset($this->get['s']) || $this->get['s'] != 'delete_spam' ) {
			$svars = json_decode($spam['spam_server'], true);

			// Setup and deliver the information to flag this comment as legit with Akismet.
			require_once( 'lib/akismet.php' );
			$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->version);
			$akismet->setCommentAuthor($spam['spam_author']);
			$akismet->setCommentAuthorURL($spam['user_url']);
			$akismet->setCommentContent($spam['spam_message']);
			$akismet->setUserIP($spam['spam_ip']);
			$akismet->setReferrer($svars['HTTP_REFERER']);
			$akismet->setCommentUserAgent($svars['HTTP_USER_AGENT']);
			$akismet->setCommentType('Sandbox');

			$akismet->submitHam();

			$q = $spam['spam_post'];
			$author = $spam['user_id'];
			$author_name = $spam['spam_author'];
			$message = $spam['spam_message'];
			$url = $spam['spam_url'];
			$time = $spam['spam_date'];
			$ip = $spam['spam_ip'];
			$type = $spam['spam_type'];

			$this->settings['spam_count']--;
			$this->settings['ham_count']++;
			$this->save_settings();

			$this->db->dbquery( "INSERT INTO %pblogcomments
			   (comment_post, comment_user, comment_author, comment_message, comment_date, comment_ip, comment_type)
			   VALUES (%d, %d, '%s', '%s', %d, '%s', %d)", $q, $author, $author_name, $message, $time, $ip, $type );

			if( $type == COMMENT_BLOG )
				$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=post_comment_count+1 WHERE post_id=%d', $q );
			elseif( $type == COMMENT_GALLERY )
				$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=photo_comment_count+1 WHERE photo_id=%d', $q );
			elseif( $type == COMMENT_FILE )
				$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=file_comment_count+1 WHERE file_id=%d', $q );

			$out .= 'Comment has been posted and Akismet notified of false positive.<br />';
		}
		$this->db->dbquery( 'DELETE FROM %pspam WHERE spam_id=%d', $p );

		$out .= 'Message deleted from spam table.';
		return $this->message( 'Spam Control', $out, 'Continue', 'admin.php?a=spam' );
	}

	function test_akismet_key()
	{
		require_once( 'lib/akismet.php' );
		$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key']);

		$response = $akismet->isKeyValid() ? 'Key is Valid!' : 'Key is Invalid!';
		return $this->message( 'Test Akismet Key', $response, 'Continue', 'admin.php', 0 );
	}

	function display_spam_comments()
	{
		$result = $this->db->dbquery( 'SELECT spam_id, spam_post, spam_author, spam_ip, spam_url, spam_message, spam_date, spam_type FROM %pspam' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/spam.xtpl' );

		$xtpl->assign( 'clear_link', 'admin.php?a=spam&amp;p=0' );
		$xtpl->assign( 'token', $this->generate_token() );

		while( $spam = $this->db->assoc($result) )
		{
			$xtpl->assign( 'ham_link', 'admin.php?a=spam&amp;s=report_ham&amp;p=' . $spam['spam_id'] );
			$xtpl->assign( 'delete_link', 'admin.php?a=spam&amp;s=delete_spam&amp;p=' . $spam['spam_id'] );
			$xtpl->assign( 'spam_id', $spam['spam_id'] );
			$xtpl->assign( 'spam_author', htmlspecialchars($spam['spam_author']) );
			$xtpl->assign( 'spam_url', htmlspecialchars($spam['spam_url']) );
			$xtpl->assign( 'spam_ip', $spam['spam_ip'] );

			$xtpl->assign( 'spam_text', htmlspecialchars($spam['spam_message']) );
			$xtpl->assign( 'spam_date', date( $this->settings['blog_dateformat'], $spam['spam_date'] ) );

			$type = 'Unknown';
			$subject = null;
			switch( $spam['spam_type'] )
			{
				case COMMENT_BLOG:
					$post = $this->db->quick_query( 'SELECT post_subject FROM %pblogposts WHERE post_id=%d', $spam['spam_post'] );
					$type = 'Blog';
					$subject = $post['post_subject'];
					break;
				case COMMENT_GALLERY:
					$image = $this->db->quick_query( 'SELECT photo_caption FROM %pphotogallery WHERE photo_id=%d', $spam['spam_post'] );
					$type = 'Gallery';
					$subject = $image['photo_caption'];
					break;
				case COMMENT_FILE:
					$file = $this->db->quick_query( 'SELECT file_name FROM %pfilelist WHERE file_id=%d', $spam['spam_post'] );
					$type = 'File';
					$subject = $file['file_name'];
					break;
				default:              $type = 'Unknown'; break;
			}

			$xtpl->assign( 'spam_type', $type );
			$xtpl->assign( 'spam_subject', htmlspecialchars($subject) );

			$xtpl->parse( 'SpamControl.Entry' );
		}
		$xtpl->parse( 'SpamControl' );
		return $xtpl->text( 'SpamControl' );
	}
}
?>