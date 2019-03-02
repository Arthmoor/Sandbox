<?php
/* Sandbox v0.5-1.0b
 * Copyright (c) 2006-2007
 * Sam O'Connor (Kiasyn) https://kiasyn.com
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2019
 * Roger Libiez [Samson] https://www.afkmods.com/
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

class sidebar
{
	var $dates = array();

	public function __construct(&$module)
	{
		$this->module = &$module;
		$this->user = &$module->user;
		$this->db = &$module->db;
		$this->settings = &$module->settings;
	}

	public function build_sidebar( $timestamp = null )
	{
		$this->xtpl = new XTemplate( './skins/' . $this->module->skin . '/sidebar.xtpl' );
		$this->xtpl->assign( 'imgsrc', "{$this->settings['site_address']}skins/{$this->module->skin}" );

		if ( $timestamp === null )
			$timestamp = $this->module->time;
		$this->day = idate( 'd', $timestamp );
		$this->month = idate( 'm', $timestamp );
		$this->year = idate( 'Y', $timestamp );
		$sidebar = array();
		$sidebar_functions = array( 'avatar' => 'build_avatar', 'calendar' => 'build_calendar', 'categories' => 'build_catlist', 'comments' => 'build_recent_comments', 'images' => 'build_recent_images', 'search' => 'build_search_box', 'blogroll' => 'build_blogroll' );

		$this->build_userbox();

		foreach ( $sidebar_functions as $name => $function )
		{
			$setting = 'sidebar_' . $name;

			if( $this->settings[$setting] )
				$this->$function( $timestamp );
		}

		$this->xtpl->parse( 'Sidebar' );
		return $this->xtpl->text( 'Sidebar' );
	}

	private function build_userbox()
	{
		$level = $this->user['user_level'];

		if( $this->settings['friendly_urls'] ) {
			$profile_url = $this->settings['site_address'] . "profile";
			$register_url = $this->settings['site_address'] . "register";
		} else {
			$profile_url = "{$this->settings['site_address']}index.php?a=profile";
			$register_url = "{$this->settings['site_address']}index.php?a=register";
		}

		$password_url = "{$this->settings['site_address']}index.php?a=register&amp;s=forgotpassword";

		if( $level == USER_GUEST ) {
			$this->xtpl->assign( 'register_url', $register_url );
			$this->xtpl->assign( 'password_url', $password_url );
			$this->xtpl->assign( 'login_url', $this->settings['site_address'] . 'index.php' );

			$this->xtpl->parse( 'Sidebar.UserboxGuest' );
		} else {
			$this->xtpl->assign( 'user_name', htmlspecialchars($this->user['user_name']) );
			$this->xtpl->assign( 'profile_url', $profile_url );
			$this->xtpl->assign( 'logout_url', $this->settings['site_address'] . 'index.php?s=logout' );

			$post_create_link = null;
			if( $level >= USER_CONTRIBUTOR )
				$post_create_link = "<a href=\"{$this->settings['site_address']}index.php?a=blog&amp;s=create\">Post New Blog Entry</a><br /><br />";
			$this->xtpl->assign( 'post_create_link', $post_create_link );

			$admin_cp_link = null;
			if( $level >= USER_CONTRIBUTOR )
				$admin_cp_link = "<a href=\"{$this->settings['site_address']}admin.php\" target=\"_blank\">AdminCP</a>&nbsp;";
			$this->xtpl->assign( 'admin_cp_link', $admin_cp_link );

			$this->xtpl->parse( 'Sidebar.UserboxMember' );
		}
	}

	private function build_blogroll( $timestamp )
	{
		$result = $this->db->dbquery( 'SELECT link_name, link_url, link_title FROM %pblogroll' );
		if( !$result )
			return;

		while( $link = $this->db->assoc( $result ) )
		{
			$this->xtpl->assign( 'blogroll_name', htmlspecialchars( $link['link_name'] ) );
			$this->xtpl->assign( 'blogroll_title', htmlspecialchars( $link['link_title'] ) );
			$this->xtpl->assign( 'blogroll_url', htmlspecialchars( $link['link_url'] ) );

			$this->xtpl->parse( 'Sidebar.Blogroll.Link' );
		}
		$this->xtpl->parse( 'Sidebar.Blogroll' );
	}

	private function build_search_box( $timestamp )
	{
		$this->xtpl->assign( 'search_url', $this->settings['site_address'] . 'index.php?a=search' );
		$this->xtpl->parse( 'Sidebar.Search' );
	}

	private function build_recent_comments( $timestamp )
	{
		$where = null;
		if( $this->user['user_level'] > USER_VALIDATING )
			$where = "(c.comment_type = " . COMMENT_BLOG . " AND (p.post_flags & " . POST_PUBLISHED . ")) OR (c.comment_type = " . COMMENT_GALLERY . " AND (i.photo_flags & " . POST_PUBLISHED . ")) OR (c.comment_type = " . COMMENT_FILE . " AND (f.file_flags & " . POST_PUBLISHED . "))";
		else
			$where = "(c.comment_type = " . COMMENT_BLOG . " AND (p.post_flags & " . POST_PUBLISHED . ") AND !(p.post_flags & " . POST_MEMBERSONLY . ")) OR (c.comment_type = " . COMMENT_GALLERY . " AND (i.photo_flags & " . POST_PUBLISHED . ") AND !(i.photo_flags & " . POST_MEMBERSONLY . ")) OR (c.comment_type = " . COMMENT_FILE . " AND (f.file_flags & " . POST_PUBLISHED . ") AND !(f.file_flags & " . POST_MEMBERSONLY . "))";

  		$result = $this->db->dbquery(
			'SELECT c.comment_id, c.comment_date, c.comment_type, u.user_name, p.post_id, p.post_subject, i.photo_id, i.photo_caption, f.file_id, f.file_name
			   FROM %pblogcomments c
		  LEFT JOIN %pblogposts p ON p.post_id=c.comment_post
		  LEFT JOIN %pphotogallery i ON i.photo_id=c.comment_post
		  LEFT JOIN %pfilelist f ON f.file_id=c.comment_post
		  LEFT JOIN %pusers u ON u.user_id=c.comment_user
			WHERE ' . $where . ' ORDER BY c.comment_date DESC LIMIT %d', $this->settings['sidebar_comments_count'] );

		if( !$result )
			return;

		while ( $comment = $this->db->assoc($result) )
		{
			if( isset($comment['post_subject']) && $comment['comment_type'] == COMMENT_BLOG ) {
				$subject = htmlspecialchars($comment['post_subject']);

				if( $this->settings['friendly_urls'] )
					$post_url = $this->settings['site_address'] . $this->module->clean_url( $subject ) . "-{$comment['post_id']}.html&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
				else
					$post_url = "{$this->settings['site_address']}index.php?a=blog&amp;p={$comment['post_id']}&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
			}

			elseif( isset($comment['photo_caption']) && $comment['comment_type'] == COMMENT_GALLERY ) {
				$subject = htmlspecialchars($comment['photo_caption']);

				if( $this->settings['friendly_urls'] )
					$post_url = $this->settings['site_address'] . 'gallery/' . $this->module->clean_url( $subject ) . "-{$comment['photo_id']}.html&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
				else
					$post_url = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$comment['photo_id']}&amp;c={$comment['comment_id']}&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
			}

			elseif( isset($comment['file_name']) && $comment['comment_type'] == COMMENT_FILE ) {
				$subject = htmlspecialchars($comment['file_name']);

				if( $this->settings['friendly_urls'] )
					$post_url = $this->settings['site_address'] . 'downloads/' . $this->module->clean_url( $subject ) . "-{$comment['file_id']}.html&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
				else
					$post_url = "{$this->settings['site_address']}index.php?a=downloads&amp;p={$comment['file_id']}&amp;c={$comment['comment_id']}#comment-{$comment['comment_id']}";
			}

			else
				continue;

			$this->xtpl->assign( 'comment_post_url', $post_url );
			$this->xtpl->assign( 'comment_user_name', htmlspecialchars($comment['user_name']) );
			$this->xtpl->assign( 'comment_date', $this->module->t_date( $comment['comment_date'] ) );
			$this->xtpl->assign( 'comment_subject', $subject );

			$this->xtpl->parse( 'Sidebar.Comments.Link' );
		}

		$this->xtpl->parse( 'Sidebar.Comments' );
	}

	private function build_recent_images( $timestamp )
	{
  		$result = $this->db->dbquery(
			'SELECT c.photo_id, c.photo_caption, c.photo_date
			   FROM %pphotogallery c
		  	   LEFT JOIN %pphotofolders p ON p.folder_id=c.photo_folder
			   WHERE p.folder_hidden = 0
			   ORDER BY c.photo_date DESC LIMIT %d', $this->settings['sidebar_images_count'] );

		if( !$result )
			return;

		while ( $image = $this->db->assoc($result) )
		{
			if( $this->settings['friendly_urls'] )
				$post_url = $this->settings['site_address'] . 'gallery/' . $this->module->clean_url( $image['photo_caption'] ) . "-{$image['photo_id']}.html";
			else
				$post_url = "{$this->settings['site_address']}index.php?a=gallery&amp;p={$image['photo_id']}";

			$this->xtpl->assign( 'image_post_url', $post_url );
			$this->xtpl->assign( 'image_date', $this->module->t_date( $image['photo_date'] ) );
			$this->xtpl->assign( 'image_subject', htmlspecialchars($image['photo_caption']) );

			$this->xtpl->parse( 'Sidebar.Images.Link' );
		}

		if( $this->settings['friendly_urls'] )
			$recent_link = $this->settings['site_address'] . 'gallery/Recent/';
		else
			$recent_link = "{$this->settings['site_address']}index.php?a=gallery&amp;recent";
		$this->xtpl->assign( 'recent_link', $recent_link );

		$this->xtpl->parse( 'Sidebar.Images' );
	}

	private function build_catlist( $timestamp )
	{
		$cats = $this->db->dbquery( 'SELECT cat_id, cat_name FROM %pblogcats ORDER BY cat_name ASC' );

		if( !$cats )
			return;

		while( $cat = $this->db->assoc( $cats ) )
		{
			if( $cat['cat_id'] == 1 )
				continue;

			if( $this->settings['friendly_urls'] )
				$cat_link = "{$this->settings['site_address']}category/" . $this->module->clean_url( $cat['cat_name'] ) . '/';
			else
				$cat_link = "{$this->settings['site_address']}index.php?a=cat&amp;cat={$cat['cat_id']}";

			$this->xtpl->assign( 'cat_link', $cat_link );
			$this->xtpl->assign( 'cat_name', htmlspecialchars($cat['cat_name']) );

			$this->xtpl->parse( 'Sidebar.Categories.Link' );
		}

		$this->xtpl->parse( 'Sidebar.Categories' );
	}

	private function build_avatar( $timestamp )
	{
		$this->xtpl->assign( 'avatar', $this->settings['site_address'] . $this->settings['blog_avatar'] );

		$this->xtpl->parse( 'Sidebar.Avatar' );
	}

	private function build_monthlinks( $timestamp )
	{
		$months = $this->db->dbquery( "SELECT DISTINCT(FROM_UNIXTIME(post_date,'%%M %%Y')) as archive_when FROM %pblogposts
			GROUP BY archive_when ORDER BY post_date DESC" );

		if( !$months )
			return '';

		$out = '';
		$date = date( 'F Y', $timestamp );
		$this_month_added = false;

		$min = $max = -1;
		while( $m = $this->db->assoc( $months ) )
		{
			$time = strtotime( $m['archive_when'] );

			if ( $min <= 0 || $time < $min )	$min = $time;
			if ( $max <= 0 || $time > $max )	$max = $time;

			if( $this->settings['friendly_urls'] )
				$archive_url = $this->settings['site_address'] . 'archive/'. $this->module->clean_url( $m['archive_when'] ) . '/';
			else
				$archive_url = "{$this->settings['site_address']}index.php?a=blog&amp;time=$date&amp;t=m";

			$selected = null;
			if( $date == $m['archive_when'] )
				$selected = ' selected="selected" ';
			if( $m['archive_when'] == date( 'F Y', $this->module->time ) )
				$this_month_added = true;
			$out .= "<option value=\"$archive_url\"$selected>{$m['archive_when']}</option>";
		}

		if( !$this_month_added ) {
			$date = date( 'F Y', $this->module->time );
			$max = strtotime( $date );

			if( $this->settings['friendly_urls'] )
				$archive_url = $this->settings['site_address'] . 'archive/'. $this->module->clean_url( $date ) . '/';
			else
				$archive_url = "{$this->settings['site_address']}index.php?a=blog&amp;time=$date&amp;t=m";

			$out = "<option value=\"$archive_url\">$date</option>" . $out;
		}

		$this->dates['min'] = $min;
		$this->dates['max'] = $max;
		return $out;
	}

	private function get_used_dates( $timestamp )
	{
		$m = idate( 'm', $timestamp );
		$y = idate( 'Y', $timestamp );

		$min_range = mktime( 0, 0, 0, $m, 1, $y );
		$max_range = mktime( 23, 59, 59, $m+1, 0, $y );

		if( $this->user['user_level'] <= USER_VALIDATING )
			$where = '(post_flags & ' . POST_PUBLISHED . ') AND !(post_flags & ' . POST_MEMBERSONLY . ')';
		else
			$where = '(post_flags & ' . POST_PUBLISHED . ')';
		$where .= " AND post_date >= $min_range AND post_date <= $max_range";

		$result = $this->db->dbquery( 'SELECT post_id, post_date, post_subject
			  FROM %pblogposts WHERE ' . $where );
		$min = $max = -1;
		while ( $row = $this->db->assoc($result) )
		{
			$time = $row['post_date'];

			$date = getdate($time);
			if ( $time >= $min_range && $time <= $max_range ) {
				$this->dates[$date['mday']]['id'] = $row['post_id'];
				$this->dates[$date['mday']]['subject'] = $row['post_subject'];
			}
		}
	}

	private function build_calendar( $timestamp )
	{
		$m = array( 'start' => mktime(0,0,0,$this->month,1,$this->year), 'end' => mktime(23,59,59,$this->month+1,0,$this->year) );

		$this->get_used_dates( $timestamp );

		$this->xtpl->assign( 'month_options', $this->build_monthlinks( $timestamp ) );

		$out = "
<table style=\"width:100%; text-align:right\">
 <tr>
  <td><strong>Su</strong></td><td><strong>Mo</strong></td><td><strong>Tu</strong></td><td><strong>We</strong></td><td><strong>Th</strong></td><td><strong>Fr</strong></td><td><strong>Sa</strong></td>
 </tr>
 <tr>";
 		$i = idate( 'w', mktime( 0, 0, 0, $this->month, 1, $this->year ) );
		if( $i > 0 )
			$out .= "<td colspan=\"$i\">&nbsp;</td>";
		else
			$out .= "<td colspan=\"7\">&nbsp;</td>";
 		for ( $j = 1, $d = 1; $j <= idate( 't', $timestamp ); $i++, $j++, $d = $j )
		{
			if ( $i % 7 == 0 )
				$out .= "</tr>\n<tr>\n";

			$time = mktime( 0, 0, 0, $this->month, $j, $this->year );
			$color = null;
			if ( isset( $this->dates[$j] ) )
			{
				if( $this->settings['friendly_urls'] )
					$post_url = $this->settings['site_address'] . $this->module->clean_url( $this->dates[$j]['subject'] ) . "-{$this->dates[$j]['id']}.html";
				else
					$post_url = "{$this->settings['site_address']}index.php?a=blog&amp;p={$this->dates[$j]['id']}";

				if ( $j == idate( 'd', $timestamp ) )
					$color = ' class="calendar_date"';
				$d = "<a href=\"$post_url\" title=\"" . htmlspecialchars($this->dates[$j]['subject']) . "\"$color>$j</a>";
			}
			if( !$color && $j == idate( 'd', $timestamp ) )
				$d = "<span class=\"calendar_date\">$d</span>";
			$out .= "<td>$d</td>\n";
		}
		if ( $i % 7 != 0 )
			$out .= "<td colspan=\"" . (7-($i%7)) . "\">&nbsp;</td>\n";
		$out .= "
 </tr>
</table>";
		$this->xtpl->assign( 'calendar_table', $out );
		$this->xtpl->parse( 'Sidebar.Calendar' );
	}
}
?>