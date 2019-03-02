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

if ( !defined('SANDBOX') || !defined('SANDBOX_ADM') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class page extends module
{
	function execute()
	{
		$this->title( 'Pages' );

		if ( isset($this->get['s']) )
			switch( $this->get['s'] )
			{
				case 'create':	return $this->create_page();
				case 'edit':	return $this->edit_page();
				case 'delete':	return $this->delete_page();
			}
		return $this->list_pages();
	}

	function list_pages()
	{
		$result = $this->db->dbquery( 'SELECT p.page_id, p.page_title, p.page_user, p.page_createdate, p.page_editdate, u.user_name
			  FROM %ppages p LEFT JOIN %pusers u ON u.user_id=p.page_user ORDER BY page_title' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/page.xtpl' );

		while ( $page = $this->db->assoc($result) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=page&amp;s=edit&amp;p='. $page['page_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=page&amp;s=delete&amp;p='. $page['page_id'] . '">Delete</a>' );
			$xtpl->assign( 'page_title', htmlspecialchars($page['page_title']) );
			$xtpl->assign( 'created', $this->t_date( $page['page_createdate'] ) );
			$xtpl->assign( 'edited', ($page['page_editdate'] > 0) ? $this->t_date( $page['page_editdate'] ) : 'Never' );

			$xtpl->parse( 'Pages.Entry' );
		}

		$xtpl->parse( 'Pages' );
		return $xtpl->text( 'Pages' );
	}

	function create_page()
	{
		$this->title( 'Page Creation' );

		if ( isset( $this->post['submit'] ) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			if ( empty($this->post['page_title']) || empty($this->post['page_content']) )
				return $this->message( 'Page Creation', 'You must specify both a title and some content to create a page.' );

			$title = $this->post['page_title'];
			$meta = $this->post['page_meta'];
			$content = $this->post['page_content'];

			$flags = 0;
			if ( isset($this->post['page_flags']) )
				foreach( $this->post['page_flags'] as $flag )
					$flags |= intval($flag);

			$this->db->dbquery( "INSERT INTO %ppages (page_title, page_meta, page_content, page_user, page_createdate, page_flags)
				 VALUES( '%s', '%s', '%s', %d, %d, %d )",
				  $title, $meta, $content, $this->user['user_id'], $this->time, $flags );

			return $this->message( 'Page Creation', 'The page has been created.', 'Continue', 'admin.php?a=page' );
		}
		return $this->page_form( 'Create Page', 'admin.php?a=page&amp;s=create' );
	}

	function page_form( $header, $action_link, $page = array( 'page_flags' => 0, 'page_title' => null, 'page_meta' => null, 'page_content' => null ) )
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/page.xtpl' );

		$flags = $page['page_flags'];

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'header', $header );
		$xtpl->assign( 'action_link', $action_link );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'page_title', htmlspecialchars($page['page_title']) );
		$xtpl->assign( 'page_meta', htmlspecialchars($page['page_meta']) );
		$xtpl->assign( 'page_content', htmlspecialchars($page['page_content']) );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

		$xtpl->assign( 'html', POST_HTML );
		$xtpl->assign( 'bb', POST_BBCODE );
		$xtpl->assign( 'sb', POST_SIDEBAR );

		if( isset($page['page_flags']) ) {
			$xtpl->assign( 'htmlbox', $flags & POST_HTML ? " checked=\"checked\"" : null );
			$xtpl->assign( 'bbbox', $flags & POST_BBCODE ? " checked=\"checked\"" : null );
			$xtpl->assign( 'sbbox', $flags & POST_SIDEBAR ? " checked=\"checked\"" : null );
		} else {
			$xtpl->assign( 'htmlbox', null );
			$xtpl->assign( 'bbbox', null );
			$xtpl->assign( 'sbbox', null );
		}

		$xtpl->parse( 'Pages.EditForm' );
		return $xtpl->text( 'Pages.EditForm' );
	}

	function edit_page()
	{
		$this->title( 'Edit Page' );

		if( !isset($this->get['p']) )
			return $this->message( 'Edit Page', 'Unknown page selected.', 'Page List', 'admin.php?a=page' );

		$p = intval($this->get['p']);

		$page = $this->db->quick_query( 'SELECT * FROM %ppages WHERE page_id=%d', $p );

		if ( !$page )
			return $this->message( 'Edit Page', 'That page does not exist.', 'Page List', 'admin.php?a=page' );

		if ( !isset($this->post['submit']) )
			return $this->page_form( 'Edit Page', "admin.php?a=page&amp;s=edit&amp;p=$p", $page );

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$title = $this->post['page_title'];
		$meta = $this->post['page_meta'];
		$content = $this->post['page_content'];

		$flags = 0;
		foreach( $this->post['page_flags'] as $flag)
			$flags |= intval($flag);

		$this->db->dbquery( "UPDATE %ppages SET page_title='%s', page_meta='%s', page_content='%s', page_editdate=%d, page_flags=%d WHERE page_id=%d",
					$title, $meta, $content, $this->time, $flags, $p );

		return $this->message( 'Page Editing', 'Page updated.', 'Continue', 'admin.php?a=page' );
	}

	function delete_page()
	{
		$this->title( 'Delete Page' );

		if( !isset($this->get['p']) && !isset($this->post['p']) )
			return $this->message( 'Delete Page', 'Unknown page selected.', 'Page List', 'admin.php?a=page' );

		$p = isset($this->get['p']) ? intval($this->get['p']) : intval($this->post['p']);

		$page = $this->db->quick_query( 'SELECT * FROM %ppages WHERE page_id=%d', $p );
		if ( !$page )
			return $this->message( 'Delete Page', 'That page does not exist.', 'Page List', 'admin.php?a=page' );

		if ( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/page.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=page&amp;s=delete&amp;p=' . $p );
			$xtpl->assign( 'page_title', $page['page_title'] );
			$xtpl->assign( 'page_meta', $page['page_meta'] );
			$xtpl->assign( 'page_id', $p );

			$xtpl->parse( 'Pages.PageDelete' );
			return $xtpl->text( 'Pages.PageDelete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$this->db->dbquery( 'DELETE FROM %ppages WHERE page_id=%d', $p );

		return $this->message( 'Delete Page', 'Page deleted.', 'Continue', 'admin.php?a=page' );
	}
}
?>