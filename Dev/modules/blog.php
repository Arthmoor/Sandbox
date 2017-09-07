<?php
/* Sandbox v0.5-1.0b http://sandbox.kiasyn.com
 * Copyright (c) 2006-2007 Sam O'Connor (Kiasyn)
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
require_once './lib/comments.php';

class blog extends module
{
	function execute()
	{
		$this->comments = new comments($this);

		if ( isset($this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'create':		return $this->create_post();
				case 'edit':		return $this->edit_post();
				case 'del':		return $this->delete_post();
				case 'edit_comment':	return $this->comments->edit_comment();
				case 'del_comment':	return $this->comments->delete_comment();
			}
			return $this->error( 'Invalid option passed.' );
		}

		if ( isset($this->get['p']) )
			return $this->view_post(intval($this->get['p']));

		if( isset($this->get['time']) )
			return $this->view_archive_page();

		$where = null;
		if( $this->user['user_level'] <= USER_VALIDATING )
			$where .= ' WHERE (post_flags & ' . POST_PUBLISHED . ') AND !(post_flags & ' . POST_MEMBERSONLY . ')';
		elseif( $this->user['user_level'] >= USER_MEMBER && $this->user['user_level'] < USER_ADMIN )
			$where .= ' WHERE (post_flags & ' . POST_PUBLISHED . ')';

		$result = $this->db->dbquery( 'SELECT p.*, u.user_name, u.user_icon, u.user_signature FROM %pblogposts p
			LEFT JOIN %pusers u ON u.user_id=p.post_user' . $where . ' ORDER BY post_date DESC LIMIT %d', $this->settings['blog_postsperpage'] );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/blog.xtpl' );

		$SideBar = new sidebar($this);
		$xtpl->assign( 'sidebar', $SideBar->build_sidebar( $this->time ) );

		while ( $row = $this->db->assoc($result) )
		{
			$cat_array = $this->get_cat_list( $row['post_id'] );
			$xtpl->assign( 'cat_text', $this->generate_category_links( $cat_array ) );

			if( $this->settings['friendly_urls'] ) {
				$post_link = $this->settings['site_address'] . $this->clean_url( $row['post_subject'] ) . "-{$row['post_id']}.html";
			} else {
				$post_link = "{$this->settings['site_address']}index.php?a=blog&amp;p={$row['post_id']}";
			}

			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $row['post_date'] ) );
			$xtpl->assign( 'subject', htmlspecialchars( $row['post_subject'] ) );
			$xtpl->assign( 'unpublished', !($row['post_flags'] & POST_PUBLISHED) ? ' <span style="color:yellow">[UNPUBLISHED ENTRY]</span>' : null );
			$xtpl->assign( 'post_link', $post_link );
			$xtpl->assign( 'post_author', htmlspecialchars($row['user_name']) );
			$xtpl->assign( 'icon', $this->display_icon( $row['user_icon'] ) );
			$xtpl->assign( 'count', $row['post_comment_count'] );

			$post_url = urlencode( $post_link );
			$xtpl->assign( 'post_url', $post_url );

			$this->generate_social_links( $xtpl, $row['post_subject'], $post_link );

			if( !empty($row['post_image']) ) {
				$xtpl->assign( 'image', $this->postimages_dir . $row['post_image'] );
				$xtpl->parse( 'Blog.Post.Image' );
			}

			$text = $this->format( $row['post_text'], $row['post_flags'] );
			if( ($row['post_flags'] & POST_HTML) && ($row['post_flags'] & POST_BBCODE) )
				$text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');

			$pos = strrpos( $text, "[more]" );
			if( $pos !== false ) {
				$text = substr( $text, 0, $pos );
				$text .= '<span style="white-space:nowrap">( <a href="' . $post_link. '">Continued...</a> )</span>';
			}

			if( $this->settings['blog_signature_on'] && !empty($row['user_signature']) ) {
				$params = POST_BBCODE | POST_EMOTICONS;
				$sig = $this->format( $row['user_signature'], $params );
				$text .= '<br /><span class="signature">.........................<br />' . $sig . '</span>';
			}
			$xtpl->assign( 'text', $text );

			$xtpl->assign( 'closed', $this->closed_content( $row, COMMENT_BLOG ) ? ' [Closed]' : null );

			$mod_controls = null;
			if( $this->user['user_level'] == USER_CONTRIBUTOR && $row['post_user'] == $this->user['user_id'] ) {
				$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=blog&amp;s=edit&amp;p=' . $row['post_id'] . '">Edit</a> ] | [ <a href="index.php?a=blog&amp;s=del&amp;p=' . $row['post_id'] . '">Delete</a> ]</div>';
			} else if( $this->user['user_level'] == USER_ADMIN ) {
				$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=blog&amp;s=edit&amp;p=' . $row['post_id'] . '">Edit</a> ] | [ <a href="index.php?a=blog&amp;s=del&amp;p=' . $row['post_id'] . '">Delete</a> ]</div>';
			}
			$xtpl->assign( 'mod_controls', $mod_controls );

			$xtpl->parse( 'Blog.Post' );
		}

		$xtpl->parse( 'Blog' );
		return $xtpl->text( 'Blog' );
	}

	function view_archive_page()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_archive.xtpl' );

		$timestamp = null;

		if( $this->settings['friendly_urls'] ) {
			$time = str_replace( '-', ' ', $this->get['time'] );
			$time = str_replace( '/', '', $time );
			$timestamp = strtotime( $time );
		} else {
			$timestamp = intval( $this->get['time'] );
		}

		if( $timestamp === null )
			$timestamp = $this->time;

		$where = ' WHERE post_date >= ' . mktime(0,0,0,idate('m',$timestamp),1,idate('Y',$timestamp)) . ' AND post_date < ' . mktime(0,0,0,idate('m',$timestamp)+1,1,idate('Y',$timestamp));
		if( $this->user['user_level'] <= USER_VALIDATING )
			$where .= ' AND (post_flags & ' . POST_PUBLISHED . ') AND !(post_flags & ' . POST_MEMBERSONLY . ')';
		else
			$where .= ' AND (post_flags & ' . POST_PUBLISHED . ')';

		$result = $this->db->dbquery( 'SELECT p.post_id, p.post_subject, p.post_date, u.user_name FROM %pblogposts p
			LEFT JOIN %pusers u ON u.user_id=p.post_user' . $where . ' ORDER BY post_date DESC' );

		$SideBar = new sidebar($this);
		$xtpl->assign( 'sidebar', $SideBar->build_sidebar( $timestamp ) );

		$month = date( 'F Y', $timestamp );
		$xtpl->assign( 'month', $month );

		$this->title( "Archive: $month" );
		$this->meta_description( "Archive of all blog posts made in $month." );

		$m = array(
			'start' => mktime(0,0,0,$SideBar->month,1,$SideBar->year),	'end'	=> mktime(23,59,59,$SideBar->month+1,0,$SideBar->year),
			'next'	=> mktime(0,0,0,$SideBar->month+1,1,$SideBar->year),	'prev'	=> mktime(23,59,59,$SideBar->month,0,$SideBar->year)
		);

		$date = date( 'F Y', $m['prev'] );
		if( $this->settings['friendly_urls'] )
			$link = $this->settings['site_address'] . 'archive/' . $this->clean_url( $date ) . '/';
		else
			$link = $this->settings['site_address'] . 'index.php?a=blog&amp;time=' . $m['prev'];
		$prev_link = $SideBar->dates['min'] < $m['start'] ? "&laquo; <a href=\"$link\">$date</a>" : '';
		$xtpl->assign( 'prev_link', $prev_link );

		$date = date( 'F Y', $m['next'] );
		if( $this->settings['friendly_urls'] )
			$link = $this->settings['site_address'] . 'archive/' . $this->clean_url( $date ) . '/';
		else
			$link = $this->settings['site_address'] . 'index.php?a=blog&amp;time=' . $m['next'];
		$next_link = $SideBar->dates['max'] > $m['end'] ? "<a href=\"$link\">$date</a> &raquo;" : '';
		$xtpl->assign( 'next_link', $next_link );

		while( $row = $this->db->assoc($result) )
		{
			if( $this->settings['friendly_urls'] )
				$post_url = $this->settings['site_address'] . $this->clean_url( $row['post_subject'] ) . "-{$row['post_id']}.html";
			else
				$post_url = "{$this->settings['site_address']}index.php?a=blog&amp;p={$row['post_id']}";
			$xtpl->assign( 'post_url', $post_url );

			$xtpl->assign( 'subject', htmlspecialchars($row['post_subject']) );
			$xtpl->assign( 'author', htmlspecialchars($row['user_name']) );

			$cat_array = $this->get_cat_list( $row['post_id'] );
			$xtpl->assign( 'cat_text', $this->generate_category_links( $cat_array ) );

			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $row['post_date'] ) );

			$xtpl->parse( 'Archive.Post' );
		}
		$xtpl->parse( 'Archive' );
		return $xtpl->text( 'Archive' );
	}

	function view_post( $p )
	{
		$post = $this->db->quick_query( 'SELECT p.*, u.* FROM %pblogposts p
			LEFT JOIN %pusers u ON u.user_id=p.post_user WHERE post_id=%d', $p );
		if( isset($this->get['title']) ) {
			if( $this->clean_url( $post['post_subject'] ) != $this->get['title'] )
				$post = null;
		}

		if ( !$post || (($post['post_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] <= USER_VALIDATING) )
			return $this->error( 'The blog entry you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );

		if( !($post['post_flags'] & POST_PUBLISHED) ) {
			if( $this->user['user_level'] < USER_CONTRIBUTOR )
				return $this->error( 'The blog entry you are looking for is not available. It may have been deleted, is restricted from viewing, or the URL is incorrect.', 404 );
		}

		$this->title( $post['post_subject'] );
		$this->meta_description( $post['post_summary'] );

		if ( isset($this->post['submit']) || isset($this->post['preview']) )
		{
			if( $this->closed_content( $post, COMMENT_BLOG ) )
				return $this->error( 'Sorry, this blog entry is closed for commenting.', 403 );

			if( ($post['post_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] < USER_MEMBER )
				return $this->error( 'Sorry, comments are only available to registered users for this post.', 403 );

			if( ($post['post_flags'] & POST_RESTRICTED_COMMENTS) && $this->user['user_level'] < USER_MEMBER )
				return $this->error( 'Sorry, comments are only available to registered users for this post.', 403 );

			$result = $this->comments->post_comment( COMMENT_BLOG, $post['post_subject'], $p );
			if( is_string($result) )
				return $result;

			if( isset($this->post['request_uri']) )
				header( 'Location: ' . $this->post['request_uri'] );

			if( $this->settings['friendly_urls'] )
				$link = $this->settings['site_address'] . $this->clean_url( $post['post_subject'] ) . "-$p.html&c=$result#comment-$result";
			else
				$link = "{$this->settings['site_address']}index.php?a=blog&p=$p&c=$result#comment-$result";
			header( 'Location: ' . $link );
		}

		if( isset( $this->get['num'] ) ) {
			$num = intval( $this->get['num'] );
		} else {
			$num = $this->settings['blog_commentsperpage'];
		}
		if( $num > $this->settings['blog_commentsperpage'] )
			$num = $this->settings['blog_commentsperpage'];
		$min = isset( $this->get['min'] ) ? intval( $this->get['min'] ) : 0;

		if( isset($this->get['c'])) {
			$cmt = intval($this->get['c']);

			// We need to find what page the requested comment is on
			$coms = $this->db->quick_query( "SELECT COUNT(comment_id) count FROM %pblogcomments WHERE comment_post=%d AND comment_id < %d AND comment_type=%d", $p, $cmt, COMMENT_BLOG );

			if ($coms)
				$count = $coms['count'] + 1;
			else $count = 0;

			$min = 0; // Start at the first page regardless
			while ($count > ($min + $num)) {
				$min += $num;
			}
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_viewpost.xtpl' );

		$older = null;
		$newer = null;

		if( $this->user['user_level'] >= USER_CONTRIBUTOR ) {
			$next_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date > %d
				ORDER BY post_date ASC LIMIT 1', $post['post_date'] );
		} elseif( $this->user['user_level'] > USER_VALIDATING ) {
			$next_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date > %d AND (post_flags & %d)
				ORDER BY post_date ASC LIMIT 1', $post['post_date'], POST_PUBLISHED );
		} else {
			$next_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date > %d AND (post_flags & %d) AND !(post_flags & %d)
				ORDER BY post_date ASC LIMIT 1',
				$post['post_date'], POST_PUBLISHED, POST_MEMBERSONLY );
		}
		if( $next_post ) {
			if( $this->settings['friendly_urls'] )
				$new_sub_link = $this->settings['site_address'] . $this->clean_url( $next_post['post_subject'] ) . "-{$next_post['post_id']}.html";
			else
				$new_sub_link = "{$this->settings['site_address']}index.php?a=blog&amp;p={$next_post['post_id']}";
			$new_sub = htmlspecialchars($next_post['post_subject']);
			$newer = "<a href=\"$new_sub_link\">$new_sub</a> &raquo;";
		}

		if( $this->user['user_level'] >= USER_CONTRIBUTOR ) {
			$prev_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date < %d
				ORDER BY post_date DESC LIMIT 1', $post['post_date'] );
		} elseif( $this->user['user_level'] > USER_VALIDATING ) {
			$prev_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date < %d AND (post_flags & %d)
				ORDER BY post_date DESC LIMIT 1', $post['post_date'], POST_PUBLISHED );
		} else {
			$prev_post = $this->db->quick_query( 'SELECT post_id, post_subject FROM %pblogposts
				WHERE post_date < %d AND (post_flags & %d) AND !(post_flags & %d)
				ORDER BY post_date DESC LIMIT 1', $post['post_date'], POST_PUBLISHED, POST_MEMBERSONLY );
		}
		if( $prev_post ) {
			if( $this->settings['friendly_urls'] )
				$new_sub_link = $this->settings['site_address'] . $this->clean_url( $prev_post['post_subject'] ) . "-{$prev_post['post_id']}.html";
			else
				$new_sub_link = "{$this->settings['site_address']}index.php?a=blog&amp;p={$prev_post['post_id']}";
			$new_sub = htmlspecialchars($prev_post['post_subject']);
			$older = "&laquo; <a href=\"$new_sub_link\">$new_sub</a>";
		}

		if( $older || $newer ) {
			$xtpl->assign( 'older', $older );
			$xtpl->assign( 'newer', $newer );

			$xtpl->parse( 'BlogPost.NavLinks' );
		}

		$SideBar = new sidebar($this);
		$xtpl->assign( 'sidebar', $SideBar->build_sidebar( $post['post_date'] ) );

		$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $post['post_date'] ) );

		$subject = htmlspecialchars($post['post_subject']);
		$xtpl->assign( 'subject', $subject );
		$xtpl->assign( 'unpublished', !($post['post_flags'] & POST_PUBLISHED) ? ' <span style="color:yellow"> [UNPUBLISHED ENTRY]</span>' : null );

		if( !empty($post['post_image']) ) {
			$xtpl->assign( 'image', $this->postimages_dir . $post['post_image'] );
			$xtpl->parse( 'BlogPost.Image' );
		}

		$text = str_replace( "[more]", "", $post['post_text'] );
		$text = $this->format( $text, $post['post_flags'] );
		if( ($post['post_flags'] & POST_HTML) && ($post['post_flags'] & POST_BBCODE) )
			$text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');

		if( $this->settings['blog_signature_on'] && !empty($post['user_signature']) ) {
			$params = POST_BBCODE | POST_EMOTICONS;
			$sig = $this->format( $post['user_signature'], $params );
			$text .= '<br /><span class="signature">.........................<br />' . $sig . '</span>';
		}
		$xtpl->assign( 'text', $text );

		$xtpl->assign( 'post_author', htmlspecialchars($post['user_name']) );
		$xtpl->assign( 'icon', $this->display_icon( $post['user_icon'] ) );

		if( $this->settings['friendly_urls'] ) {
			$post_link = $this->settings['site_address'] . $this->clean_url( $post['post_subject'] ) . "-{$post['post_id']}.html";
		} else {
			$post_link = "{$this->settings['site_address']}index.php?a=blog&amp;p={$post['post_id']}";
		}

		$post_url = urlencode( $post_link );
		$xtpl->assign( 'post_url', $post_url );

		$this->generate_social_links( $xtpl, $post['post_subject'], $post_link );

		$cat_array = $this->get_cat_list( $post['post_id'] );
		$xtpl->assign( 'cat_text', $this->generate_category_links( $cat_array ) );

		$xtpl->assign( 'count', $post['post_comment_count'] );

		$closed = $this->closed_content( $post, COMMENT_BLOG );
		$xtpl->assign( 'closed', $closed ? ' [Closed]' : null );

		if( !($post['post_flags'] & POST_MEMBERSONLY) || (($post['post_flags'] & POST_MEMBERSONLY) && $this->user['user_level'] >= USER_MEMBER) ) {
			if ( $post['post_comment_count'] > 0 ) {
				$xtpl->assign( 'comments', $this->comments->list_comments( COMMENT_BLOG, $p, $post['post_subject'], $post['post_user'], $post['post_comment_count'], $min, $num, $post_link ) );

				$xtpl->parse( 'BlogPost.Comments' );
			}

			if( $this->user['user_level'] >= USER_MEMBER ) {
				$author = htmlspecialchars($this->user['user_name']);
			} else {
				$author = isset($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) ? htmlspecialchars($this->cookie[$this->settings['cookie_prefix'] . 'comment_author']) : 'Anonymous';
			}

			if( $this->settings['friendly_urls'] )
				$action_link = $this->settings['site_address'] . $this->clean_url( $post['post_subject'] ) . "-{$post['post_id']}.html#newcomment";
			else
				$action_link = "{$this->settings['site_address']}index.php?a=blog&p={$post['post_id']}#newcomment";

			$xtpl->assign( 'comment_form', $this->comments->generate_comment_form( $author, $subject, $action_link, $closed ) );
		}

		$mod_controls = null;
		if( $this->user['user_level'] == USER_CONTRIBUTOR && $post['post_user'] == $this->user['user_id'] ) {
			$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=blog&amp;s=edit&amp;p=' . $post['post_id'] . '">Edit</a> ] | [ <a href="index.php?a=blog&amp;s=del&amp;p=' . $post['post_id'] . '">Delete</a> ]</div>';
		} else if( $this->user['user_level'] == USER_ADMIN ) {
			$mod_controls = '<div class="mod_controls">[ <a href="index.php?a=blog&amp;s=edit&amp;p=' . $post['post_id'] . '">Edit</a> ] | [ <a href="index.php?a=blog&amp;s=del&amp;p=' . $post['post_id'] . '">Delete</a> ]</div>';
		}
		$xtpl->assign( 'mod_controls', $mod_controls );

		$xtpl->parse( 'BlogPost' );
		return $xtpl->text( 'BlogPost' );
	}

	function generate_category_links( $cat_list )
	{
		$links = array();

		foreach( $cat_list as $cat )
		{
			if( $this->settings['friendly_urls'] )
				$cat_name = $this->settings['site_address'] . 'category/' . $this->clean_url( $cat['cat_name'] ) . '/';
			else
				$cat_name = "{$this->settings['site_address']}index.php?a=cat&amp;cat={$cat['cat_id']}";

			$name = htmlspecialchars($cat['cat_name']);
			$links[] = "<a href=\"$cat_name\" title=\"View all posts in $name\" rel=\"tag\">$name</a>";
		}

		$link_list = implode($links, ', ');
		return $link_list;
	}

	function get_cat_list( $post )
	{
		$cats = array();
		$catresult = $this->db->dbquery( 'SELECT c.cat_id, c.cat_name
		  	  FROM %ppostcats pc
			  LEFT JOIN %pblogcats c ON c.cat_id=pc.pc_cat
		 	  WHERE pc.pc_post=%d', $post );
		while( $cat = $this->db->assoc($catresult) )
			$cats[] = array( 'cat_id' => $cat['cat_id'], 'cat_name' => $cat['cat_name'] );
		if ( empty($cats) )
			$cats[] = array( 'cat_id' => 0, 'cat_name' => 'Uncategorized' );

		return $cats;
	}

	function edit_post()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( !isset($this->get['p']) )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

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
			$ext = strtolower(end($system));

			if ( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
				array_push( $errors, 'Invalid image type ' . $ext . '. Valid file types are jpg, png and gif.' );
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
			$xtpl->assign( 'icon', $this->display_icon( $post['user_icon'] ) );

			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=blog&amp;s=edit&amp;p={$post['post_id']}" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
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
			$xtpl->assign( 'cmr', POST_RESTRICTED_COMMENTS );

			$xtpl->assign( 'htmlbox', $post['post_flags'] & POST_HTML ? " checked=\"checked\"" : null );
			$xtpl->assign( 'bbbox', $post['post_flags'] & POST_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'embox', $post['post_flags'] & POST_EMOTICONS ? " checked=\"checked\"" : null );
			$xtpl->assign( 'brbox', $post['post_flags'] & POST_BREAKS ? " checked=\"checked\"" : null );
			$xtpl->assign( 'pubbox', $post['post_flags'] & POST_PUBLISHED ? " checked=\"checked\"" : null );
			$xtpl->assign( 'clsbox', $post['post_flags'] & POST_CLOSED ? " checked=\"checked\"" : null );
			$xtpl->assign( 'ovrbox', $post['post_flags'] & POST_OVERRIDE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'mbobox', $post['post_flags'] & POST_MEMBERSONLY ? " checked=\"checked\"" : null );
			$xtpl->assign( 'cmrbox', $post['post_flags'] & POST_RESTRICTED_COMMENTS ? " checked=\"checked\"" : null );

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
					$this->db->dbquery( "INSERT INTO %dblogcats (cat_name) VALUES('%s')", $name );
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

		$link = 'index.php?a=blog&p=' . $p;
		header( 'Location: ' . $link );
	}

	function delete_post()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( !isset($this->get['p']) )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		$p = intval($this->get['p']);

		$user = $this->db->quick_query( 'SELECT post_user FROM %pblogposts WHERE post_id=%d', $p );
		if( $user ) {
			if( $this->user['user_id'] != $user['post_user'] && $this->user['user_level'] < USER_ADMIN )
				return $this->error( 'Access Denied: You do not own the blog entry you are attempting to delete.' );
		}

		if( !isset($this->post['confirm'])) {
			$post = $this->db->quick_query( 'SELECT p.*, u.*
				  FROM %pblogposts p
				  LEFT JOIN %pusers u ON u.user_id=p.post_user
				  WHERE post_id=%d', $p );
			if( !$post )
				return $this->message( 'Delete Blog Entry', 'No such blog entry.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/blog_deletepost.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );

			$user = $this->db->quick_query( 'SELECT user_name FROM %pusers WHERE user_id=%d', $post['post_user'] );

			$xtpl->assign( 'action_link', $this->settings['site_address'] . 'index.php?a=blog&amp;s=del&amp;p=' . $post['post_id'] . '&amp;confirm=1' );
			$xtpl->assign( 'author', htmlspecialchars($user['user_name']) );
			$xtpl->assign( 'subject', htmlspecialchars($post['post_subject']) );
			$xtpl->assign( 'text', $this->format( $post['post_text'], $post['post_flags'] ) );
			$xtpl->assign( 'icon', $this->display_icon( $post['user_icon'] ) );
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

		return $this->message( 'Delete Blog Entry', 'Blog entry and all attached comments have been deleted.', 'Continue', 'index.php' );
	}

	function create_post()
	{
		// Lock this shit down!!!
		if( $this->user['user_level'] < USER_CONTRIBUTOR )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

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
			$ext = strtolower(end($system));

			if ( !preg_match( '/jpg|jpeg|png|gif/', $ext ) ) {
				array_push( $errors, 'Invalid image type ' . $ext . '. Valid file types are jpg, png and gif.' );
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
			if( !$this->is_valid_token() && ! isset( $this->post['preview'] ) )
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
				$xtpl->assign( 'cmrbox', null );
				$xtpl->assign( 'bbbox', ' checked="checked"' );
				$xtpl->assign( 'pubbox', ' checked="checked"' );
				$xtpl->assign( 'embox', ' checked="checked"' );
			}

			$xtpl->assign( 'icon', $this->display_icon( $this->user['user_icon'] ) );
			$xtpl->assign( 'action_link', "{$this->settings['site_address']}index.php?a=blog&amp;s=create" );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
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
			$this->db->dbquery( 'INSERT INTO %ppostcats (pc_post, pc_cat) VALUES( %d, %d)', $id, $cid );

		$ping_errors = null;
		if( $flags & POST_PUBLISHED && !($flags & POST_MEMBERSONLY) )
			$ping_errors = '<br /><br />' . $this->ping_sites(false) . '<br /><br />';
		return $this->message( 'Post Blog Entry', "{$ping_errors}Blog entry posted.", 'Continue', 'index.php' );
	}

	function ping_sites($report = true)
	{
		if( extension_loaded('xmlrpc'))
		{
			if( !isset( $this->settings['site_pings'] ) )
			{
				if( $report )
					return $this->message( 'Ping Services', 'There are no ping services setup yet.<br />' );
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
	}
}
?>