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

require_once './lib/comments.php';

class spam_control extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		$svars = array();
		$this->title( 'Spam Control' );

		if( !isset($this->get['c']) ) {
			return $this->display_spam_comments();
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'The security validation token used to verify you are authorized to perform this action is either invalid or expired. Please try again.' );
		}

		$c = intval($this->get['c']);

		if( $c == 0 ) {
			if( $this->user['user_level'] < USER_ADMIN )
				return $this->error( 'Access Denied: You do not have permission to perform that action.' );

			$this->db->dbquery( 'TRUNCATE TABLE %pspam' );
			return $this->message( 'Spam Control', 'All comment spam has been deleted.', 'Continue', '/index.php' );
		}

		if( isset( $this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'delete_spam':	return $this->delete_spam($c);
				case 'report_ham':	return $this->report_ham($c);
			}
		}
		return $this->error( 'Invalid option passed.' );
	}

	function delete_spam( $c )
	{
		$spam = $this->db->quick_query( 'SELECT spam_post, spam_type FROM %pspam WHERE spam_id=%d', $c );

		if( !$spam )
			return $this->message( 'Spam Control', 'There is no such spam comment.', 'Continue', '/index.php?a=spam_control' );

		if( $this->user['user_level'] == USER_CONTRIBUTOR ) {
			$user = null;
			if( $spam['spam_type'] == COMMENT_BLOG )
				$user = $this->db->quick_query( 'SELECT post_user FROM %blogposts WHERE post_id=%d', $spam['spam_post'] );
			else if( $spam['spam_type'] == COMMENT_GALLERY )
				$user = $this->db->quick_query( 'SELECT photo_user FROM %pphotogallery WHERE photo_id=%d', $spam['spam_post'] );
			else if( $spam['spam_type'] == COMMENT_FILE )
				$user = $this->db->quick_query( 'SELECT file_user FROM %pfilelist WHERE file_id=%d', $spam['spam_post'] );

			if( !$user )
				return $this->error( 'Access Denied: You do not own the entry you are trying to delete.' );
		}

		$this->db->dbquery( 'DELETE FROM %pspam WHERE spam_id=%d', $c );

		return $this->message( 'Spam Control', 'Spam Deleted.', 'Continue', $this->settings['site_address'] . 'index.php?a=spam_control' );
	}

	function report_ham( $c )
	{
		$spam = $this->db->quick_query( 'SELECT * FROM %pspam WHERE spam_id=%d', $c );
		if( !$spam )
			return $this->message( 'Spam Control', 'There is no such spam comment.', 'Continue', '/index.php?a=spam_control' );

		if( $this->user['user_level'] == USER_CONTRIBUTOR ) {
			$user = null;
			if( $spam['spam_type'] == COMMENT_BLOG )
				$user = $this->db->quick_query( 'SELECT post_user FROM %blogposts WHERE post_id=%d', $spam['spam_post'] );
			else if( $spam['spam_type'] == COMMENT_GALLERY )
				$user = $this->db->quick_query( 'SELECT photo_user FROM %pphotogallery WHERE photo_id=%d', $spam['spam_post'] );
			else if( $spam['spam_type'] == COMMENT_FILE )
				$user = $this->db->quick_query( 'SELECT file_user FROM %pfilelist WHERE file_id=%d', $spam['spam_post'] );

			if( !$user )
				return $this->error( 'Access Denied: You do not own the entry you are trying to report.' );
		}

		$svars = json_decode($spam['spam_server'], true);

		// Setup and deliver the information to flag this comment as legit with Akismet.
		require_once( 'lib/akismet.php' );
		$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->version);
		$akismet->setCommentAuthor($spam['spam_author']);
		$akismet->setCommentAuthorURL($spam['spam_url']);
		$akismet->setCommentContent($spam['spam_message']);
		$akismet->setUserIP($spam['spam_ip']);
		$akismet->setReferrer($svars['HTTP_REFERER']);
		$akismet->setCommentUserAgent($svars['HTTP_USER_AGENT']);
		$akismet->setCommentType('comment');

		$akismet->submitHam();

		$q = $spam['spam_post'];
		$author = $spam['spam_user'];
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
		   VALUES ( %d, %d, '%s', '%s', %d, '%s', %d)", $q, $author, $author_name, $message, $time, $ip, $type );

		if( $type == COMMENT_BLOG )
			$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=post_comment_count+1 WHERE post_id=%d', $q );
		elseif( $type == COMMENT_GALLERY )
			$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=photo_comment_count+1 WHERE photo_id=%d', $q );
		elseif( $type == COMMENT_FILE )
			$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=file_comment_count+1 WHERE file_id=%d', $q );

		$this->db->dbquery( 'DELETE FROM %pspam WHERE spam_id=%d', $c );

		return $this->message( 'Spam Control', 'Comment has been posted and Akismet notified of a false positive.', 'Continue', $this->settings['site_address'] . 'index.php?a=spam_control' );
	}

	function display_spam_comments()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/spam_control.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );

		$result = $this->db->dbquery( 'SELECT * FROM %pspam' );

		while( $spam = $this->db->assoc($result) )
		{
			$post = $this->db->quick_query( 'SELECT post_subject, post_user FROM %pblogposts WHERE post_id=%d', $spam['spam_post'] );
			$image = $this->db->quick_query( 'SELECT photo_caption, photo_user FROM %pphotogallery WHERE photo_id=%d', $spam['spam_post'] );
			$file = $this->db->quick_query( 'SELECT file_name, file_user FROM %pfilelist WHERE file_id=%d', $spam['spam_post'] );

			if( $this->user['user_level'] == USER_CONTRIBUTOR ) {
				if( !$post || ($post && $post['post_user'] != $this->user['user_id']) )
					continue;
				if( !$image || ($image && $image['photo_user'] != $this->user['user_id']) )
					continue;
				if( !$file || ($file && $file['file_user'] != $this->user['user_id']) )
					continue;
			}

			$xtpl->assign( 'spam_id', $spam['spam_id'] );
			$xtpl->assign( 'spam_author', htmlspecialchars($spam['spam_author']) );
			$xtpl->assign( 'spam_url', htmlspecialchars($spam['spam_url']) );
			$xtpl->assign( 'spam_ip', $spam['spam_ip'] );
			$xtpl->assign( 'ham_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;s=report_ham&amp;c=' . $spam['spam_id'] );
			$xtpl->assign( 'delete_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;s=delete_spam&amp;c=' . $spam['spam_id'] );

			$xtpl->assign( 'spam_text', htmlspecialchars($spam['spam_message']) );
			$xtpl->assign( 'spam_date', $this->t_date( $spam['spam_date'] ) );

			$type = 'Unknown';
			$subject = null;
			switch( $spam['spam_type'] )
			{
				case COMMENT_BLOG:
					$type = 'Blog';
					$subject = $post['post_subject'];
					break;
				case COMMENT_GALLERY:
					$type = 'Gallery';
					$subject = $image['photo_caption'];
					break;
				case COMMENT_FILE:
					$type = 'File';
					$subject = $file['file_name'];
					break;
				default:              $type = 'Unknown'; break;
			}

			$xtpl->assign( 'spam_type', $type );
			$xtpl->assign( 'spam_subject', htmlspecialchars($subject) );

			$xtpl->parse( 'SpamControl.Entry' );
		}

		if( $this->user['user_level'] == USER_ADMIN ) {
			$xtpl->assign( 'clear_all_link', $this->settings['site_address'] . 'index.php?a=spam_control&amp;c=0' );

			$xtpl->parse( 'SpamControl.ClearAll' );
		}

		$xtpl->parse( 'SpamControl' );
		return $xtpl->text( 'SpamControl' );
	}
}
?>