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

class cat extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] ) {
				case 'create':		return $this->create_category();
				case 'edit':		return $this->edit_category();
				case 'delete':		return $this->delete_category();
			}
		}
		return $this->list_categories();
	}

	function list_categories()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/cat.xtpl' );

		$cats = $this->db->dbquery( 'SELECT * FROM %pblogcats' );
		while( $cat = $this->db->assoc( $cats ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=cat&amp;s=edit&amp;c=' . $cat['cat_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=cat&amp;s=delete&amp;c=' . $cat['cat_id'] . '">Delete</a>' );
			$xtpl->assign( 'name', htmlspecialchars($cat['cat_name']) );
			$xtpl->assign( 'desc', $this->format( $cat['cat_description'], POST_BBCODE ) );

			$xtpl->parse( 'Categories.Entry' );
		}

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'heading', 'Create Blog Category' );
		$xtpl->assign( 'action_link', 'admin.php?a=cat&amp;s=create' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'cat_name', null );
		$xtpl->assign( 'cat_desc', null );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

		$xtpl->parse( 'Categories.EditForm' );
		$xtpl->parse( 'Categories' );
		return $xtpl->text( 'Categories' );
	}

	function create_category()
	{
		if( isset($this->post['category']) ) {
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$name = $this->post['category'];
			$desc = isset( $this->post['cat_desc'] ) ? $this->post['cat_desc'] : '';

			$cat = $this->db->quick_query( "SELECT cat_name FROM %pblogcats WHERE cat_name='%s'", $name );

			if( $cat ) {
				return $this->message( 'Create Category', 'A category called ' . $this->post['category'] . ' already exists.' );
			}

			$this->db->dbquery( "INSERT INTO %pblogcats (cat_name, cat_description) VALUES( '%s', '%s' )", $name, $desc );
			return $this->message( 'Create Category', 'Blog category added.', 'Continue', 'admin.php?a=cat' );
		}

		return $this->list_categories();
	}

	function edit_category()
	{
		if( !isset($this->get['c']) && !isset($this->post['submit']) )
			return $this->message( 'Edit Category', 'Invalid category specified.', 'Category List', 'admin.php?a=cat' );

		$catid = intval($this->get['c']);

		if(!isset($this->post['submit'])) {
			$cat = $this->db->quick_query( 'SELECT * FROM %pblogcats WHERE cat_id=%d', $catid );

			if( !$cat )
				return $this->message( 'Edit Category', 'Invalid category selected.' );

			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/cat.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'heading', 'Edit Blog Category' );
			$xtpl->assign( 'action_link', 'admin.php?a=cat&amp;s=edit&c=' . $catid );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'cat_name', htmlspecialchars($cat['cat_name']) );
			$xtpl->assign( 'cat_desc', htmlspecialchars($cat['cat_description']) );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );

			$xtpl->parse( 'Categories.EditForm' );
			return $xtpl->text( 'Categories.EditForm' );
		}

		$name = $this->post['category'];
		$desc = isset( $this->post['cat_desc'] ) ? $this->post['cat_desc'] : '';

		$this->db->dbquery( "UPDATE %pblogcats SET cat_name='%s', cat_description='%s' WHERE cat_id=%d", $name, $desc, $catid );
		return $this->message( 'Edit Category', 'Category data updated.', 'Continue', 'admin.php?a=cat' );
	}

	function delete_category()
	{
		if( !isset($this->get['c']) && !isset($this->post['c']) )
			return $this->message( 'Delete Blog Category', 'Invalid category specified.', 'Category List', 'admin.php?a=cat' );

		$catid = isset($this->get['c']) ? intval($this->get['c']) : intval($this->post['c']);
		$cat = $this->db->quick_query( 'SELECT * FROM %pblogcats WHERE cat_id=%d', $catid );

		if( !$cat )
			return $this->message( 'Delete Blog Category', 'Invalid category specified.', 'Category List', 'admin.php?a=cat' );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/cat.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=cat&amp;s=delete&amp;c=' . $catid );
			$xtpl->assign( 'cat_name', $cat['cat_name'] );
			$xtpl->assign( 'cat_id', $catid );

			$xtpl->parse( 'Categories.Delete' );
			return $xtpl->text( 'Categories.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( $catid == 1 )
			return $this->error( 'You may not delete the default category.' );

		$this->db->dbquery( 'UPDATE %ppostcats SET pc_cat=1 WHERE pc_cat=%d', $catid );
		$this->db->dbquery( 'DELETE FROM %pblogcats WHERE cat_id=%d', $catid );

		return $this->message( 'Delete Blog Category', 'Category deleted. All posts within it have been flagged as uncategorized.', 'Continue', 'admin.php?a=cat' );
	}
}
?>