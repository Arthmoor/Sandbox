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

class quotes extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['s'] ) )
			switch( $this->get['s'] )
			{
				case 'add':		return $this->add_quote();
				case 'edit':		return $this->edit_quote();
				case 'del':		return $this->delete_quote();
			}
		return $this->list_quotes();
	}

	function list_quotes()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/quotes.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'action_link', 'admin.php?a=quotes&amp;s=add' );
		$xtpl->assign( 'heading', 'Add a new random quote:' );
		$xtpl->assign( 'site_root', $this->settings['site_address'] );
		$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
		$xtpl->assign( 'text', 'Something witty goes here.' );

		$quotes = $this->db->dbquery( 'SELECT * FROM %prandom_quotes ORDER BY quote_id DESC' );

		while( $quote = $this->db->assoc( $quotes ) )
		{
			$xtpl->assign( 'entry_text', $this->format( $quote['quote_text'], POST_BBCODE ) );
			$xtpl->assign( 'edit_link', '<a href="admin.php?a=quotes&amp;s=edit&amp;id=' . $quote['quote_id'] . '">Edit</a>' );
			$xtpl->assign( 'delete_link', '<a href="admin.php?a=quotes&amp;s=del&amp;id=' . $quote['quote_id'] . '">Delete</a>' );

			$xtpl->parse( 'RandomQuotes.List.Entry' );
		}

		$xtpl->parse( 'RandomQuotes.List' );
		$xtpl->parse( 'RandomQuotes' );
		return $xtpl->text( 'RandomQuotes' );
	}

	function add_quote()
	{
		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['text']) || empty($this->post['text']) ) {
			return $this->message( 'Add Random Quote', 'You need to supply text for the quote.' );
		}

		$text = $this->post['text'];

		$this->db->dbquery( "INSERT INTO %prandom_quotes (quote_text) VALUES('%s')", $text );

		return $this->message( 'Add Random Quote', "The new quote has been added to your random quotes.", 'Continue', 'admin.php?a=quotes' );
	}

	function edit_quote()
	{
		if( !isset($this->get['id']) )
			return $this->message( 'Edit Random Quote', 'You must specify a quote to edit.' );
		$id = intval($this->get['id']);

		$quote = $this->db->quick_query( 'SELECT * FROM %prandom_quotes WHERE quote_id=%d', $id );

		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/quotes.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=quotes&amp;s=edit&amp;id=' . $id );
			$xtpl->assign( 'heading', 'Edit a Random Quote' );
			$xtpl->assign( 'site_root', $this->settings['site_address'] );
			$xtpl->assign( 'bbcode_menu', $this->bbcode->get_bbcode_menu() );
			$xtpl->assign( 'text', htmlspecialchars($quote['quote_text']) );

			$xtpl->parse( 'RandomQuotes' );
			return $xtpl->text( 'RandomQuotes' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['text']) || empty($this->post['text']) ) {
			return $this->message( 'Edit Random Quote', 'You must supply text for the quote.' );
		}

		$text = $this->post['text'];

		$this->db->dbquery( "UPDATE %prandom_quotes SET quote_text='%s' WHERE quote_id=%d", $text, $id );

		return $this->message( 'Edit Random Quote', "The random quote has been edited.", 'Continue', 'admin.php?a=quotes' );
	}

	function delete_quote()
	{
		if( !isset($this->get['id']) )
			return $this->message( 'Delete Random Quote', 'No quote ID was specified.' );

		$id = intval($this->get['id']);

		$quote = $this->db->quick_query( 'SELECT quote_id, quote_text FROM %prandom_quotes WHERE quote_id=%d', $id );
		if( !$quote )
			return $this->message( 'Delete Random Quote', 'No such quote is in the list.' );

		if( !isset($this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/quotes.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=quotes&amp;s=del&amp;id=' . $id );
			$xtpl->assign( 'quote_text', $this->format( $quote['quote_text'], POST_BBCODE ) );

			$xtpl->parse( 'RandomQuotes.Delete' );
			return $xtpl->text( 'RandomQuotes.Delete' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		$this->db->dbquery( 'DELETE FROM %prandom_quotes WHERE quote_id=%d', $id );

		return $this->message( 'Delete Random Quote', 'The quote has been deleted from the database.', 'Continue', 'admin.php?a=quotes' );
	}
}
?>