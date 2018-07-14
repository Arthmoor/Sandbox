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

class comments
{
	public function __construct(&$module)
	{
		$this->module = &$module;
		$this->user = &$module->user;
		$this->db = &$module->db;
		$this->settings = &$module->settings;
	}

	public function generate_comment_form( $author, $subject, $action_link, $closed, $message = null )
	{
		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_form.xtpl' );

		if( $closed ) {
			$xtpl->parse( 'CommentForm.Closed' );
			return $xtpl->text( 'CommentForm.Closed' );
		}
		$xtpl->assign( 'action_link', $action_link );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'author', $author );
		$xtpl->assign( 'subject', $subject );

		if( $this->user['user_level'] <= USER_MEMBER )
			$xtpl->parse( 'CommentForm.SpamControl' );

		if( $this->user['user_level'] == USER_GUEST )
			$xtpl->parse( 'CommentForm.GuestName' );

		$xtpl->assign( 'message', $message );
		$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
		$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );

		$xtpl->parse( 'CommentForm' );
		return $xtpl->text( 'CommentForm' );
	}

	public function list_comments( $ctype, $p, $subject, $u, $count, $min, $num, $link )
	{
		$result = $this->db->dbquery( '
			SELECT c.comment_id, c.comment_date, c.comment_editdate, c.comment_editedby, c.comment_author, c.comment_user, c.comment_message, c.comment_ip,
				u.user_id, u.user_name, u.user_url, u.user_icon
			  FROM %pblogcomments c
			  LEFT JOIN %pusers u ON u.user_id=c.comment_user
			 WHERE comment_post=%d AND comment_type=%d ORDER BY comment_date LIMIT %d, %d', $p, $ctype, $min, $num );

		if( !$result )
			return '';

		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_view.xtpl' );

		$page = '';
		if( $ctype == COMMENT_BLOG )
			$page = 'blog';
		elseif( $ctype == COMMENT_GALLERY )
			$page = 'gallery';
		elseif( $ctype == COMMENT_FILE )
			$page = 'downloads';

		$pos = $min + 1;
		while ( $comment = $this->db->assoc($result) )
		{
			$icon = $this->settings['site_address'] . $this->module->icon_dir . 'Anonymous.png';
			if( !empty($comment['user_icon']) )
				$icon = $this->module->display_icon( $comment['user_icon'] );
			$xtpl->assign( 'icon', $icon );

			$cid = $comment['comment_id'];
			$clink = $link . '&amp;c=' . $cid . '#comment-' . $cid;
			$xtpl->assign( 'link', $clink );
			$xtpl->assign( 'cid', $cid );

			$author = 'Anonymous [Anon]';
			if( $comment['user_id'] != USER_GUEST ) {
				$author = htmlspecialchars( $comment['user_name'] );
				if ( !empty($comment['user_url']) )
					$author = '<a href="' . htmlspecialchars($comment['user_url']) . '">' . $comment['user_name'] . '</a>';
			} else {
				if( !empty($comment['comment_author']) )
					$author = htmlspecialchars( $comment['comment_author'] );
			}
			$xtpl->assign( 'author', $author );

			$params = POST_BBCODE | POST_EMOTICONS;
			$xtpl->assign( 'message', $this->module->format( $comment['comment_message'], $params ) );

			$date = $this->module->t_date( $comment['comment_date'] );
			$date = 'Comment #' . $pos . ' ' . $date;
			$xtpl->assign( 'date', $date );

			$edited = null;
			if( $comment['comment_editdate'] > 0 ) {
				$xtpl->assign( 'editdate', $this->module->t_date( $comment['comment_editdate'] ) );
				$xtpl->assign( 'editedby', htmlspecialchars($comment['comment_editedby']) );

				$xtpl->parse( 'CList.Comment.EditedBy' );
			}

			$mod_controls = null;
			if( $this->user['user_level'] == USER_ADMIN || ($this->user['user_level'] == USER_CONTRIBUTOR && $u == $this->user['user_id']) )
				$mod_controls = '&nbsp;<div class="mod_controls">[ ' . $comment['comment_ip'] . ' ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=edit_comment&amp;c=' . $cid . '">Edit</a> ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=del_comment&amp;c=' . $cid . '">Delete</a> ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=del_comment&amp;t=spam&amp;c=' . $cid . '">Spam</a> ]</div>';
			elseif( $this->user['user_level'] == USER_PRIVILEGED && $this->user['user_id'] == $comment['comment_user'] && $this->module->time - $comment['comment_date'] <= 10800 )
				$mod_controls = '&nbsp;<div class="mod_controls">[ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=edit_comment&amp;c=' . $cid . '">Edit</a> ] | [ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=del_comment&amp;c=' . $comment['comment_id'] . '">Delete</a> ]</div>';
			elseif( $this->user['user_level'] == USER_MEMBER && $this->user['user_id'] == $comment['comment_user'] && $this->module->time - $comment['comment_date'] <= 1800 )
				$mod_controls = '&nbsp;<div class="mod_controls">[ <a href="' . $this->settings['site_address'] . 'index.php?a=' . $page . '&amp;s=edit_comment&amp;c=' . $cid . '">Edit</a> ]</div>';
			else
				$mod_controls = null;

			$xtpl->assign( 'mod_controls', $mod_controls );

			$xtpl->parse( 'CList.Comment' );
			$pos++;
		}

		$pagelinks = $this->make_links( $ctype, $p, $subject, $count, $min, $num );
		$xtpl->assign( 'pagelinks', $pagelinks );

		$xtpl->parse( 'CList' );
		return $xtpl->text( 'CList' );
	}

	public function post_comment( $ctype, $subject, $id )
	{
		$uid = $this->user['user_id'];
		$com_time = $this->module->time;
		$ip = $this->module->ip;
		$author = '';
		$return_data = array();

		if( isset($this->module->post['preview']) ) {
			$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_preview.xtpl' );

			$xtpl->assign( 'icon', $this->module->display_icon( $this->user['user_icon'] ) );

			$xtpl->assign( 'date', $this->module->t_date( $this->module->time ) );
			$xtpl->assign( 'subject', $subject );

			$text = null;
			$message = null;
			if( isset($this->module->post['comment_message']) ) {
				$params = POST_BBCODE | POST_EMOTICONS;
				$text = $this->module->format( $this->module->post['comment_message'], $params );
				$message = htmlspecialchars($this->module->post['comment_message']);
			}
			$xtpl->assign( 'text', $text );
			$xtpl->assign( 'message', $message );

			if( $this->user['user_level'] <= USER_MEMBER )
				$xtpl->parse( 'Comment.SpamControl' );

			if( $this->user['user_level'] == USER_GUEST ) {
				$author = isset($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) ? htmlspecialchars($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) : 'Anonymous';
				$xtpl->assign( 'author', $author );

				$xtpl->parse( 'Comment.GuestName' );
			} else {
				$xtpl->assign( 'author', htmlspecialchars($this->user['user_name']) );
			}

			if( $this->settings['friendly_urls'] ) {
				switch( $ctype )
				{
					case COMMENT_BLOG:
						$action_link = $this->settings['site_address'] . $this->module->clean_url( $subject ) . "-$id.html#newcomment";
						break;
					case COMMENT_GALLERY:
						$action_link = $this->settings['site_address'] . 'gallery/' . $this->module->clean_url( $subject ) . "-$id.html#newcomment";
						break;
					case COMMENT_FILE:
						$action_link = $this->settings['site_address'] . 'downloads/' . $this->module->clean_url( $subject ) . "-$id.html#newcomment";
				}
			} else {
				switch( $ctype )
				{
					case COMMENT_BLOG:
						$action_link = "{$this->settings['site_address']}index.php?a=blog&amp;p=$id#newcomment";
						break;
					case COMMENT_GALLERY:
						$action_link = "{$this->settings['site_address']}index.php?a=gallery&amp;p=$id#newcomment";
						break;
					case COMMENT_FILE:
						$action_link = "{$this->settings['site_address']}index.php?a=downloads&amp;p=$id#newcomment";
						break;
				}
			}
			$xtpl->assign( 'action_link', $action_link );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
			$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );

			$xtpl->parse( 'Comment' );
			return $xtpl->text( 'Comment' );
		}

		if ($this->user['user_level'] == USER_GUEST ) {
			if (isset($this->module->post['comment_author']) || !empty($this->module->post['comment_author']) )
				$author = $this->module->post['comment_author'] . ' [Anon]';
		} else {
			$author = $this->user['user_name'];
		}

		if (!isset($this->module->post['comment_message']) || empty($this->module->post['comment_message']) )
			return $this->module->error( 'You cannot post an empty comment!' );

		$message = $this->module->post['comment_message'];
		$type = intval($ctype);

		// I'm not sure if the anti-spam code needs to use the escaped strings or not, so I'll feed them whatever the spammer fed me.
		require_once( 'lib/akismet.php' );
		$spam_checked = false;
		$akismet = null;

		if( $this->user['user_level'] < USER_PRIVILEGED ) {
			try {
				$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->module->version);

				$akismet->setCommentAuthor($author);
				// $akismet->setCommentAuthorEmail($email);
				if( $this->user['user_level'] == USER_MEMBER && isset($this->user['user_url']) )
					$akismet->setCommentAuthorURL($this->user['user_url']);
				elseif( isset($this->module->post['url']) )
					$akismet->setCommentAuthorURL($this->module->post['url']);
				else
					$akismet->setCommentAuthorURL( '' );
				$akismet->setCommentContent($this->module->post['comment_message']);
				$akismet->setCommentType('comment');

				$link = $this->module->clean_url( $subject );
				$plink = $this->settings['site_address'] . "$link-$id.html";
				$akismet->setPermalink($plink);

				$spam_checked = true;
			}
			// Try and deal with it rather than say something.
			catch(Exception $e) { $this->error($e->getMessage()); }
		} else {
			$spam_checked = true;
		}

		if( $spam_checked && $akismet != null && $akismet->isCommentSpam() )
		{
			// Store the contents of the entire $_SERVER array.
			$svars = json_encode($_SERVER);

			$this->db->dbquery( "
			   INSERT INTO %pspam (spam_post, spam_user, spam_author, spam_message, spam_date, spam_type, spam_ip, spam_server)
			   VALUES (%d, %d, '%s', '%s', %d, %d, '%s', '%s')", $id, $uid, $author, $message, $com_time, $type, $ip, $svars );

			$this->settings['spam_count']++;
			$this->module->save_settings();
			$this->purge_old_spam();
			return $this->module->message( 'Akismet Warning', 'Your comment has been flagged as potential spam and must be evaluated by the site owner.' );
		}

		if( $this->user['user_level'] == USER_GUEST )
			setcookie( $this->settings['cookie_prefix'] . 'comment_author', $this->module->post['comment_author'], $this->module->time+31556926, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		$this->db->dbquery( "
			INSERT INTO %pblogcomments (comment_user, comment_author, comment_post, comment_date, comment_ip, comment_message, comment_referrer, comment_agent, comment_type)
			     VALUES ( %d, '%s', %d, %d, '%s', '%s', '%s', '%s', %d)",
				$uid, $author, $id, $com_time, $this->module->ip, $message, $this->module->referrer, $this->module->agent, $type );
		$cid = $this->db->insert_id();

		switch( $ctype )
		{
			case COMMENT_BLOG:
				$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=post_comment_count+1 WHERE post_id=%d', $id );
				break;
			case COMMENT_GALLERY:
				$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=photo_comment_count+1 WHERE photo_id=%d', $id );
				break;
			case COMMENT_FILE:
				$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=file_comment_count+1 WHERE file_id=%d', $id );
		}

		if ( $this->settings['blog_notifycomments'] && $this->user['user_level'] < USER_ADMIN ) {
			$error = null;
			if( !$spam_checked )
				$error = ' This comment has not been properly screened by Akismet due to a thrown exception.';

			if( $this->settings['friendly_urls'] )
			{
				switch( $ctype )
				{
					case COMMENT_BLOG:
						$link = $this->settings['site_address'] . $this->module->clean_url( $subject ) . "-$id.html&c=$cid#comment-$cid";
						break;
					case COMMENT_GALLERY:
						$link = $this->settings['site_address'] . 'gallery/' . $this->module->clean_url( $subject ) . "-$id.html&c=$cid#comment-$cid";
						break;
					case COMMENT_FILE:
						$link = $this->settings['site_address'] . 'downloads/' . $this->module->clean_url( $subject ) . "-$id.html&c=$cid#comment-$cid";
						break;
				}
			} else {
				switch( $ctype )
				{
					case COMMENT_BLOG:
						$link = "{$this->settings['site_address']}index.php?a=blog&p=$id&c=$cid#comment-$cid";
						break;
					case COMMENT_GALLERY:
						$link = "{$this->settings['site_address']}index.php?a=gallery&p=$id&c=$cid#comment-$cid";
						break;
					case COMMENT_FILE:
						$link = "{$this->settings['site_address']}index.php?a=downloads&p=$id&c=$cid#comment-$cid";
						break;
				}
			}

			$comment_author = htmlspecialchars($author);

			if ( $this->settings['html_email'] ) {
				$message_date = $this->module->t_date( $this->module->time );
				$params = POST_BBCODE | POST_EMOTICONS;
				$html_message = $this->module->format( $this->module->post['comment_message'], $params );
				$email_link = "
<html>
<body bgcolor=\"#ffffff\">
{$comment_author} has posted a comment to: 
<a href=\"$link\">$subject</a><br />
<br />
<h4>On $message_date, $comment_author said:</h4>
<p>$html_message</p><br />
$error
</body></html>";

				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n";
				$headers .= "MIME-Version: 1.0\r\n";
				$boundary = uniqid("HTMLBLOGCOMMENT");
				$headers .= "Content-Type: multipart/alternative" . "; boundary = $boundary\r\n\r\n";
				$headers .= "This is a MIME encoded message.\r\n\r\n";
				$headers .= "--$boundary\r\n" . "Content-Type: text/html; charset=UTF-8\r\n";
				$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n\r\n";
				mail( $this->settings['email_adm'], 'Comment posted.', $email_link, $headers );
			} else {
				$headers = "From: {$this->settings['site_name']} <{$this->settings['email_adm']}>\r\n" . "X-Mailer: PHP/" . phpversion();
				mail( $this->settings['email_adm'], 'Comment posted.', "$comment_author has posted a comment to: $subject $link\n\n$error", $headers );
			}
		}

		return $cid; // Returns the comment ID so the originating page can header to it immediately.
	}

	public function edit_comment()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_MEMBER )
			return $this->module->error( 'Access Denied: You do not have permission to perform that action.' );

		if( !isset($this->module->get['c']) )
			return $this->module->message( 'Edit Comment', 'No comment was specified for editing.' );

		$c = intval($this->module->get['c']);

		$comment = $this->db->quick_query( 'SELECT c.*, u.* FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user	WHERE comment_id=%d', $c );

		if( !$comment )
			return $this->module->message( 'Edit Comment', 'No such comment was found for editing.' );

		if( $this->user['user_id'] != $comment['comment_user'] && $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->module->error( 'Access Denied: You do not own the comment you are attempting to edit.' );

		// After 30 minutes, you're stuck with it if you're a regular member.
		if( $this->user['user_level'] < USER_PRIVILEGED && $this->module->time - $comment['comment_date'] > 1800 )
			return $this->module->error( 'Access Denied: You cannot edit your comments after 30 minutes have gone by.' );

		// After 3 hours, you're stuck with it if you're a regular member.
		if( $this->user['user_level'] == USER_PRIVILEGED && $this->module->time - $comment['comment_date'] > 10800 )
			return $this->module->error( 'Access Denied: You cannot edit your comments after 3 hours have gone by.' );

		$user = null;
		if( $comment['comment_type'] == COMMENT_BLOG )
			$user = $this->db->quick_query( 'SELECT post_user FROM %pblogposts WHERE post_id=%d', $comment['comment_post'] );
		elseif( $comment['comment_type'] == COMMENT_GALLERY )
			$user = $this->db->quick_query( 'SELECT photo_user FROM %pphotogallery WHERE photo_id=%d', $comment['comment_post'] );
		elseif( $comment['comment_type'] == COMMENT_FILE )
			$user = $this->db->quick_query( 'SELECT file_user FROM %pfilelist WHERE file_id=%d', $comment['comment_post'] );

		if( !$user )
			return $this->module->error( 'Access Denied: You do not own the entry you are trying to edit.' );

		if( $this->user['user_level'] == USER_CONTRIBUTOR ) {
			switch( $comment['comment_type'] )
			{
				case COMMENT_BLOG:
					if( $this->user['user_id'] != $user['post_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the blog entry you are trying to edit.' );
					break;
				case COMMENT_GALLERY:
					if( $this->user['user_id'] != $user['photo_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the image entry you are trying to edit.' );
					break;
				case COMMENT_FILE:
					if( $this->user['user_id'] != $user['file_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the download entry you are trying to edit.' );
				default: return $this->module->error( 'Unknown comment type selected for editing.' );
			}
		}

		$xtpl = new XTemplate( './skins/' . $this->module->skin . '/comment_edit.xtpl' );

		$page = '';
		if( $comment['comment_type'] == COMMENT_BLOG )
			$page = 'blog';
		elseif( $comment['comment_type'] == COMMENT_GALLERY )
			$page = 'gallery';
		elseif( $comment['comment_type'] == COMMENT_FILE )
			$page = 'downloads';

		if ( !isset($this->module->post['submit']) ) {
			$xtpl->assign( 'author', htmlspecialchars($comment['user_name']) );

			$message = null;
			$text = null;
			$params = POST_BBCODE | POST_EMOTICONS;
			if( isset($this->module->post['post_text']) ) {
				$text = $this->module->post['post_text'];
				$message = $this->module->format( $this->module->post['post_text'], $params );
			} else {
				$text = $comment['comment_message'];
				$message = $this->module->format( $comment['comment_message'], $params );
			}
			$xtpl->assign( 'text', htmlspecialchars($text) );

			$xtpl->assign( 'emoticons', $this->module->bbcode->generate_emote_links() );
			$xtpl->assign( 'bbcode_menu', $this->module->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=$page&amp;s=edit_comment&amp;c=$c" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );

			if( isset($this->module->post['preview']) ) {
				$xtpl->assign( 'icon', $this->module->icon_dir . $comment['user_icon'] );
				$xtpl->assign( 'date', $this->module->t_date( $comment['comment_date'] ) );
				$xtpl->assign( 'message', $message );

				$xtpl->parse( 'Comment.Preview' );
			}

			$xtpl->parse( 'Comment' );
			return $xtpl->text( 'Comment' );
		}

		if (!isset($this->module->post['post_text']) || empty($this->module->post['post_text']) )
			return $this->module->error( 'You cannot post an empty comment!' );

		$text = $this->module->post['post_text'];
		$editedby = $this->user['user_name'];

		$this->db->dbquery( "UPDATE %pblogcomments SET comment_editdate=%d, comment_editedby='%s', comment_message='%s' WHERE comment_id=%d", $this->module->time, $editedby, $text, $c );

		if( isset( $this->module->post['request_uri'] ) )
			header( 'Location: ' . $this->module->post['request_uri'] );

		$link = "{$this->settings['site_address']}index.php?a=$page&p={$comment['comment_post']}&c=$c#comment-$c";
		header( 'Location: ' . $link );
	}

	public function delete_comment()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_PRIVILEGED )
			return $this->module->error( 'Access Denied: You do not have permission to perform that action.' );

		if( !isset($this->module->get['c']) )
			return $this->module->message( 'Delete Comment', 'No comment was specified for editing.' );

		$c = intval($this->module->get['c']);

		$comment = $this->db->quick_query( 'SELECT c.*, u.* FROM %pblogcomments c
			LEFT JOIN %pusers u ON u.user_id=c.comment_user	WHERE comment_id=%d', $c );

		if( !$comment )
			return $this->module->message( 'Delete Comment', 'No such comment was found for deletion.' );

		if( $this->user['user_id'] != $comment['comment_user'] && $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->module->error( 'Access Denied: You do not own the comment you are attempting to delete.' );

		// After 3 hours, you're stuck with it if you're a regular member.
		if( $this->user['user_level'] == USER_PRIVILEGED && $this->module->time - $comment['comment_date'] > 10800 )
			return $this->module->error( 'Access Denied: You cannot delete your comments after 3 hours have gone by.' );

		$user = null;
		if( $comment['comment_type'] == COMMENT_BLOG )
			$user = $this->db->quick_query( 'SELECT post_user FROM %pblogposts WHERE post_id=%d', $comment['comment_post'] );
		elseif( $comment['comment_type'] == COMMENT_GALLERY )
			$user = $this->db->quick_query( 'SELECT photo_user FROM %pphotogallery WHERE photo_id=%d', $comment['comment_post'] );
		elseif( $comment['comment_type'] == COMMENT_FILE )
			$user = $this->db->quick_query( 'SELECT file_user FROM %pfilelist WHERE file_id=%d', $comment['comment_post'] );

		if( !$user )
			return $this->module->error( 'Access Denied: You do not own the entry you are trying to edit.' );

		if( $this->user['user_level'] == USER_CONTRIBUTOR ) {
			switch( $comment['comment_type'] )
			{
				case COMMENT_BLOG:
					if( $this->user['user_id'] != $user['post_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the blog entry you are trying to edit.' );
					break;
				case COMMENT_GALLERY:
					if( $this->user['user_id'] != $user['photo_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the image entry you are trying to edit.' );
					break;
				case COMMENT_FILE:
					if( $this->user['user_id'] != $user['file_user'] && $this->user['user_id'] != $comment['comment_user'] )
						return $this->module->error( 'Access Denied: You do not own the download entry you are trying to edit.' );
					break;
				default: return $this->module->error( 'Unknown comment type selected for editing.' );
			}
		}

		if( isset($this->module->get['t']) && $this->module->get['t'] == 'spam' ) {
			if( $this->user['user_level'] < USER_CONTRIBUTOR ) {
				return $this->module->error( 'Access Denied: You are not authorized to report spam.' );
			}
		}

		$page = '';
		if( $comment['comment_type'] == COMMENT_BLOG )
			$page = 'blog';
		elseif( $comment['comment_type'] == COMMENT_GALLERY )
			$page = 'gallery';
		elseif( $comment['comment_type'] == COMMENT_FILE )
			$page = 'downloads';

		if( !isset($this->module->get['confirm']) ) {
			$author = htmlspecialchars($comment['user_name']);
			$params = POST_BBCODE | POST_EMOTICONS;
			$text = $this->module->format( $comment['comment_message'], $params );
			$date = $this->module->t_date( $comment['comment_date'] );

			$msg = "<div class=\"title\">Comment by {$author} Posted on: {$date}</div><div class=\"article\">{$text}</div>";
			$link = "index.php?a=$page&amp;s=del_comment&amp;c=$c&amp;confirm=1";
			$sp = null;
			if( isset($this->module->get['t']) && $this->module->get['t'] == 'spam' ) {
				$link .= '&amp;t=spam';
				$sp = '<br />This comment will be reported as spam.';
			}
			$msg .= "<div class=\"title\" style=\"text-align:center\">Are you sure you want to delete this comment?$sp</div>";

			return $this->module->message( 'DELETE COMMENT', $msg, 'Delete', $link, 0 );
		}

		$out = null;

		if( isset($this->module->get['t']) && $this->module->get['t'] == 'spam' ) {
			// Time to report the spammer before we delete the comment. Hopefully this is enough info to strike back with.
			require_once( 'lib/akismet.php' );
			$akismet = new Akismet($this->settings['site_address'], $this->settings['wordpress_api_key'], $this->module->version);
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
			$this->module->save_settings();

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

		return $this->module->message( 'Delete Comment', $out, 'Continue', "index.php?a=$page&p={$comment['comment_post']}" );
	}

	private function make_links( $ctype, $p, $subject, $count, $min, $num )
	{
		if( $num < 1 ) $num = 1; // No more division by zero please.

		$current = ceil( $min / $num );
		$string  = null;
		$pages   = ceil( $count / $num );
		$end     = ($pages - 1) * $num;
		$link = '';

		if( $this->settings['friendly_urls'] ) {
			if( $ctype == COMMENT_BLOG )
				$link = $this->settings['site_address'] . $this->module->clean_url( $subject ) . "-$p.html";
			elseif( $ctype == COMMENT_GALLERY )
				$link = $this->settings['site_address'] . 'gallery/' . $this->module->clean_url( $subject ) . "-$p.html";
			elseif( $ctype == COMMENT_FILE )
				$link = $this->settings['site_address'] . 'downloads/' . $this->module->clean_url( $subject ) . "-$p.html";
		} else {
			if( $ctype == COMMENT_BLOG )
				$link = "{$this->settings['site_address']}index.php?a=blog&amp;p=$p";
			elseif( $ctype == COMMENT_GALLERY )
				$link = "{$this->settings['site_address']}index.php?a=gallery&amp;p=$p";
			elseif( $ctype == COMMENT_FILE )
				$link = "{$this->settings['site_address']}index.php?a=downloads&amp;p=$p";
		}

		// check if there's previous articles
		if($min == 0) {
			$startlink = '&lt;&lt;';
			$previouslink = 'prev';
		} else {
			$startlink = "<a href=\"$link&amp;min=0&amp;num=$num#comments\">&lt;&lt;</a>";
			$prev = $min - $num;
			$previouslink = "<a href=\"$link&amp;min=$prev&amp;num=$num#comments\">prev</a> ";
		}

		// check for next/end
		if(($min + $num) < $count) {
			$next = $min + $num;
  			$nextlink = "<a href=\"$link&amp;min=$next&amp;num=$num#comments\">next</a>";
  			$endlink = "<a href=\"$link&amp;min=$end&amp;num=$num#comments\">&gt;&gt;</a>";
		} else {
  			$nextlink = 'next';
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
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num#comments\">" . ($i + 1) . '</a>';
		}

		// add in page
		$string .= ', <strong>' . ($current + 1) . '</strong>';

		// run to the end
		for ($i = $current + 1; $i <= $e; $i++)
		{
			$where = $num * $i;
			$string .= ", <a href=\"$link&amp;min=$where&amp;num=$num#comments\">" . ($i + 1) . '</a>';
		}

		// get rid of preliminary comma.
		if (substr($string, 0, 1) == ',') {
			$string = substr($string, 1);
		}

		return "$startlink $previouslink $badd $string $eadd $nextlink $endlink";
	}

	// Automatically drop spam posts in the spam DB that are older than 30 days.
	private function purge_old_spam()
	{
		$diff = 2592000; // 30 days * 86400 secs
		$cut_off = $this->module->time - $diff;
		$this->db->dbquery( 'DELETE FROM %pspam WHERE spam_date <= %d', $cut_off );
	}
}
?>