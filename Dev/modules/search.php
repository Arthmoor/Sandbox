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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once './lib/sidebar.php';

class search extends module
{
	function execute()
	{
		if( !isset($this->post['search_word']) || empty($this->post['search_word']) )
			return $this->message( 'Search', 'You must enter something to search for. I can\'t read your mind.' );

		if( strlen($this->post['search_word']) < 3 )
			return $this->message( 'Search', 'You cannot search on a word smaller than 3 letters.' );

		$search_query = $this->build_word_query( $this->post['search_word'] );

		if( $this->user['user_level'] >= USER_CONTRIBUTOR ) {
			$result = $this->db->dbquery( 'SELECT p.post_id, p.post_subject, p.post_summary, p.post_date, p.post_user, u.user_name FROM %pblogposts p
				LEFT JOIN %pusers u ON u.user_id=p.post_user
				WHERE ' . $search_query . ' ORDER BY p.post_date DESC' );
		} elseif( $this->user['user_level'] > USER_VALIDATING ) {
			$result = $this->db->dbquery( 'SELECT p.post_id, p.post_subject, p.post_summary, p.post_date, p.post_user, u.user_name FROM %pblogposts p
				LEFT JOIN %pusers u ON u.user_id=p.post_user
				WHERE ' . $search_query . ' AND (post_flags & %d) ORDER BY p.post_date DESC', POST_PUBLISHED );
		} else {
			$result = $this->db->dbquery( 'SELECT p.post_id, p.post_subject, p.post_summary, p.post_date, p.post_user, u.user_name FROM %pblogposts p
				LEFT JOIN %pusers u ON u.user_id=p.post_user
				WHERE ' . $search_query . ' AND (post_flags & %d) AND !(post_flags & %d) ORDER BY p.post_date DESC', POST_PUBLISHED, POST_MEMBERSONLY );
		}

		if( !$result )
			return $this->message( 'Search', "No results matching: {$this->post['search_word']}" );

		$content = null;
		$count = 0;

		$xtpl = new XTemplate( './skins/' . $this->skin . '/search.xtpl' );

		while( $item = $this->db->assoc( $result ) )
		{
			if( $this->settings['friendly_urls'] )
				$item_link = $this->clean_url( $item['post_subject'] ) . "-{$item['post_id']}.html";
			else
				$item_link = "index.php?a=blog&amp;p={$item['post_id']}";
			$xtpl->assign( 'item_link', $item_link );

			$xtpl->assign( 'date', date($this->settings['blog_dateformat'], $item['post_date']) );
			$count++;

			$xtpl->assign( 'subject', htmlspecialchars($item['post_subject']) );
			$xtpl->assign( 'summary', htmlspecialchars($item['post_summary']) );
			$xtpl->assign( 'user_name', htmlspecialchars($item['user_name']) );

			$xtpl->parse( 'Search.Result' );
		}

		if( $count == 0 )
			$xtpl->assign( 'content', "No results matching: {$this->post['search_word']}" );

		$xtpl->assign( 'search_word', htmlspecialchars($this->post['search_word']) );
		$xtpl->assign( 'count', $count );
		$xtpl->assign( 'posts', ($count > 1 ? 'blog entries' : 'blog entry') );

		$SideBar = new sidebar($this);
		$xtpl->assign( 'sidebar', $SideBar->build_sidebar() );

		$xtpl->parse( 'Search' );
		return $xtpl->text( 'Search' );
	}

	function build_word_query( $in )
	{
		$out = null;
		$in  = explode( ' ', $in );

		foreach ($in as $word)
		{
			$w = $this->db->escape($word);
			$out .= " OR (post_text LIKE '%%$w%%')";
		}

		return '(' . substr($out, 4) . ')';
	}
}
?>