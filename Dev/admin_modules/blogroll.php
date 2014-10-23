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

class blogroll extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'add':		return $this->add_blogroll();
				case 'edit':		return $this->edit_blogroll();
				case 'del':		return $this->delete_blogroll();
			}
		return $this->list_blogroll();
	}

	function list_blogroll()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/blogroll.xtpl' );

		$xtpl->assign( 'action_link', 'admin.php?a=blogroll&amp;s=add' );
		$xtpl->assign( 'heading', 'Add a link to your blogroll' );
		$xtpl->assign( 'token', $this->generate_token() );

		$links = $this->db->dbquery( 'SELECT * FROM %pblogroll' );

		while( $link = $this->db->assoc( $links ) )
		{
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=blogroll&amp;s=edit&amp;id=' . $link['link_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=blogroll&amp;s=del&amp;id=' . $link['link_id'] . '">Delete</a>' );
 			$xtpl->assign( 'entry_name', htmlspecialchars($link['link_name']) );
			$xtpl->assign( 'entry_url', '<a href="' . htmlspecialchars($link['link_url']) . '">' . htmlspecialchars($link['link_url']) . '</a>' );
			$xtpl->assign( 'entry_title', htmlspecialchars($link['link_title']) );

			$xtpl->parse( 'Blogroll.Entry' );
		}
		$xtpl->assign( 'link_name', null );
		$xtpl->assign( 'link_url', null );
		$xtpl->assign( 'link_title', null );

		$xtpl->parse( 'Blogroll.Form' );
		$xtpl->parse( 'Blogroll' );
		return $xtpl->text( 'Blogroll' );
	}

	function add_blogroll()
	{
		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['link_name']) || empty($this->post['link_name']) ) {
			return $this->message( 'Add to Blogroll', 'You must supply a name for the link to display.' );
		}

		if( !isset($this->post['link_url']) || empty($this->post['link_url']) ) {
			return $this->message( 'Add to Blogroll', 'You must supply a URL for the link.' );
		}

		if( !isset($this->post['link_title']) ) {
			$this->post['link_title'] = '';
		}

		$name = $this->post['link_name'];
		$url = $this->post['link_url'];
		$title = $this->post['link_title'];

		if( strpos( $url, 'http://' ) === false || strpos( $url, 'http://' ) != 0 )
			$url = 'http://' . $url;

		$exists = $this->db->quick_query( "SELECT link_name, link_url FROM %pblogroll WHERE link_name='%s' OR link_url='%s'", $name, $url );
		if( $exists )
		{
			if( $name == $exists['link_name'] )
				return $this->message( 'Add to Blogroll', 'A link with that name is already in your blogroll.' );
			return $this->message( 'Add to Blogroll', 'A link with that URL is already in your blogroll.' );
		}

		$this->db->dbquery( "INSERT INTO %pblogroll (link_name, link_url, link_title) VALUES('%s','%s', '%s')", $name, $url, $title );

		return $this->message( 'Add to Blogroll', "$name has been added to your blogroll.", 'Continue', 'admin.php?a=blogroll' );
	}

	function edit_blogroll()
	{
		if( !isset($this->get['id']) && !isset($this->post['id']) )
			return $this->message( 'Edit Blogroll Link', 'You must specify a link in the blogroll to edit.' );

		$id = isset($this->get['id']) ? intval($this->get['id']) : intval($this->post['id']);

		$link = $this->db->quick_query( 'SELECT * FROM %pblogroll WHERE link_id=%d', $id );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/blogroll.xtpl' );

			$xtpl->assign( 'action_link', 'admin.php?a=blogroll&amp;s=edit&amp;id=' . $id );
			$xtpl->assign( 'heading', 'Edit a link in your blogroll' );
			$xtpl->assign( 'token', $this->generate_token() );

			$xtpl->assign( 'link_name', htmlspecialchars($link['link_name']) );
			$xtpl->assign( 'link_url', htmlspecialchars($link['link_url']) );
   			$xtpl->assign( 'link_title', htmlspecialchars($link['link_title']) );

			$xtpl->parse( 'Blogroll.Form' );
			return $xtpl->text( 'Blogroll.Form' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['link_name']) || empty($this->post['link_name']) ) {
			return $this->message( 'Edit Blogroll Link', 'You must supply a name for the link to display.' );
		}

		if( !isset($this->post['link_url']) || empty($this->post['link_url']) ) {
			return $this->message( 'Edit Blogroll Link', 'You must supply a URL for the link.' );
		}

		if( !isset($this->post['link_title']) ) {
			$this->post['link_title'] = '';
		}

		$name = $this->post['link_name'];
		$url = $this->post['link_url'];
		$title = $this->post['link_title'];

		if( strpos( $url, 'http://' ) === false || strpos( $url, 'http://' ) != 0 )
			$url = 'http://' . $url;

		$exists = $this->db->quick_query( "SELECT link_id, link_name, link_url FROM %pblogroll WHERE link_name='%s' OR link_url='%s'", $name, $url );
		if( $exists && $exists['link_id'] != $id )
		{
			if( $name == $exists['link_name'] )
				return $this->message( 'Edit Blogroll Link', 'A link with that name is already in your blogroll.' );
			return $this->message( 'Edit Blogroll Link', 'A link with that URL is already in your blogroll.' );
		}

		$this->db->dbquery( "UPDATE %pblogroll SET link_name='%s', link_url='%s', link_title='%s' WHERE link_id=%d", $name, $url, $title, $id );

		return $this->message( 'Edit Blogroll Link', "The link for $name has been edited in your blogroll.", 'Continue', 'admin.php?a=blogroll' );
	}

	function delete_blogroll()
	{
		if( !isset($this->get['id']) && !isset($this->post['id']) )
			return $this->message( 'Delete From Blogroll', 'You must specify a link in the blogroll to delete.' );

		$id = isset($this->get['id']) ? intval($this->get['id']) : intval($this->post['id']);

		$link = $this->db->quick_query( 'SELECT link_id, link_name FROM %pblogroll WHERE link_id=%d', $id );
		if( !$link )
			return $this->message( 'Delete From Blogroll', 'No such link is in the blogroll.' );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/blogroll.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=blogroll&amp;s=del&amp;id=' . $id );
			$xtpl->assign( 'link_name', $link['link_name'] );
			$xtpl->assign( 'link_id', $link['link_id'] );

			$xtpl->parse( 'Blogroll.Delete' );
			return $xtpl->text( 'Blogroll.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['id']) )
			return $this->message( 'Delete From Blogroll', 'No blogroll link was specified.' );

		$id = intval($this->post['id']);
		$this->db->dbquery( 'DELETE FROM %pblogroll WHERE link_id=%d', $id );

		return $this->message( 'Delete From Blogroll', 'The entry has been deleted from your blogroll.', 'Continue', 'admin.php?a=blogroll' );
	}
}
?>