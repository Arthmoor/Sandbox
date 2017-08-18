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

class settings extends module
{
	function select_input( $name, $value, $values = array() )
	{
		$out = null;
		foreach( $values as $key )
			$out .= '<option' . ($key == $value ? ' selected="selected"' : '') . ">$key</option>";
		return "<select name=\"$name\">$out</select>";
	}

	function add_setting()
	{
		if( !isset($this->post['submit']) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/settings.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'action_link', 'admin.php?a=settings&amp;s=add' );

			$xtpl->parse( 'Settings.AddForm' );
			return $xtpl->text( 'Settings.AddForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( !isset($this->post['new_setting']) || empty($this->post['new_setting'])) {
			return $this->message( 'Add Site Setting', 'An empty setting name is not allowed.' );
		}

		$new_setting = $this->post['new_setting'];
		$new_value = $this->post['new_value'];

		if( isset($this->settings[$new_setting]) ) {
			return $this->message( 'Add Site Setting', 'A setting called ' . $new_setting . ' already exists!' );
		}

		$this->settings[$new_setting] = $new_value;
		$this->save_settings();

		return $this->message( 'Add Site Setting', 'New settings saved.', 'Continue', 'admin.php' );
	}

	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if ( isset($this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'add':		return $this->add_setting();
			}
			return $this->error( 'Invalid option passed.' );
		}

		$int_fields = array( 'site_open', 'blog_postsperpage', 'blog_autoclose', 'blog_icon_width', 'blog_icon_height', 'cookie_logintime',
			'rss_items', 'rss_refresh', 'gallery_thumb_w', 'gallery_thumb_h', 'sidebar_images_count', 'sidebar_comments_count',
			'html_email', 'friendly_urls', 'validate_users', 'global_comments', 'anonymous_comments', 'blog_commentsperpage', 'download_size' );
		foreach( $int_fields as $key )
		{
			if ( !isset($this->settings[$key]) )
				$this->settings[$key] = 0;
		}

		$this->title( 'Site Settings' );
		$sets = &$this->settings;
		if ( isset($this->post['submit']) )
		{
			if( !$this->is_valid_token() ) {
				return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
			}

			$names = explode( "\n", $this->post['page_names'] );
			$links = explode( "\n", $this->post['page_links'] );
			if ( count($names) == count($links) )
				$sets['page_links'] = array_combine( $names, $links );

			foreach( $int_fields as $key )
			{
				if ( isset($this->post[$key]) )
					$this->settings[$key] = intval($this->post[$key]);
				else
					$this->settings[$key] = 0;
			}

			$sets['download_size'] = $this->post['download_size'] * 1048576;

			$sets['rss_enabled'] = isset($this->post['rss_enabled']);
			$sets['blog_notifycomments'] = isset($this->post['blog_notifycomments']);
			$sets['blog_signature_on'] = isset($this->post['blog_signature_on']);
			$sets['cookie_secure'] = isset($this->post['cookie_secure']);
			$sets['sidebar_avatar'] = isset($this->post['sidebar_avatar']);
			$sets['sidebar_search'] = isset($this->post['sidebar_search']);
			$sets['sidebar_calendar'] = isset($this->post['sidebar_calendar']);
			$sets['sidebar_comments'] = isset($this->post['sidebar_comments']);
			$sets['sidebar_images'] = isset($this->post['sidebar_images']);
			$sets['sidebar_blogroll'] = isset($this->post['sidebar_blogroll']);
			$sets['sidebar_categories'] = isset($this->post['sidebar_categories']);

			if( !empty($this->post['site_address']) && $this->post['site_address'][strlen($this->post['site_address'])-1] != '/' )
				$this->post['site_address'] = $this->post['site_address'] . '/';

			$valid_fields = array(
				'email_adm', 'email_sys', 'site_name', 'site_tagline', 'site_owner', 'site_address', 'site_analytics', 'site_closedmessage',
				'site_meta', 'site_keywords', 'mobile_icons', 'rss_name', 'rss_description', 'rss_image_url', 'blog_avatar', 'blog_dateformat',
				'wordpress_api_key', 'twitter_user', 'cookie_prefix', 'cookie_path', 'cookie_domain', 'global_announce', 'copyright_terms', 'footer_text' );
			foreach( $valid_fields as $key )
				$this->settings[$key] = $this->post[$key];
			if ( in_array( $this->post['site_defaultskin'], $this->get_skins() ) )
				$this->settings['site_defaultskin'] = $this->post['site_defaultskin'];

			if( $this->settings['cookie_path']{0} != '/' )
				$this->settings['cookie_path'] = '/' . $this->settings['cookie_path'];
			if( $this->settings['cookie_path']{strlen($this->settings['cookie_path'])-1} != '/' )
				$this->settings['cookie_path'] .= '/';

			$this->save_settings();

			return $this->message( 'Sandbox Settings', 'Settings saved.', 'Continue', 'admin.php' );
		}

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/settings.xtpl' );

		$xtpl->assign( 'token', $this->generate_token() );
		$xtpl->assign( 'site_name', htmlspecialchars($sets['site_name']) );
		$xtpl->assign( 'site_tagline', htmlspecialchars($sets['site_tagline']) );
		$xtpl->assign( 'site_owner', htmlspecialchars($sets['site_owner']) );
		$xtpl->assign( 'email_adm', htmlspecialchars($sets['email_adm']) );
		$xtpl->assign( 'email_sys', htmlspecialchars($sets['email_sys']) );
		$xtpl->assign( 'site_address', htmlspecialchars($sets['site_address']) );
		$xtpl->assign( 'site_meta', htmlspecialchars($sets['site_meta']) );
		$xtpl->assign( 'site_keywords', htmlspecialchars($sets['site_keywords']) );
		$xtpl->assign( 'mobile_icons', htmlspecialchars($sets['mobile_icons']) );
		$xtpl->assign( 'blog_dateformat', htmlspecialchars($sets['blog_dateformat']) );
		$xtpl->assign( 'site_defaultskin', $this->select_input( 'site_defaultskin', $sets['site_defaultskin'], $this->get_skins() ) );
		$xtpl->assign( 'site_analytics', htmlspecialchars($sets['site_analytics']) );
		$xtpl->assign( 'wordpress_api_key', htmlspecialchars($sets['wordpress_api_key']) );
		$xtpl->assign( 'twitter_user', htmlspecialchars($sets['twitter_user']) );

		if( $sets['friendly_urls'] ) {
			$xtpl->assign( 'url1', ' checked="checked"' );
			$xtpl->assign( 'url0', null );
		} else {
			$xtpl->assign( 'url1', null );
			$xtpl->assign( 'url0', ' checked="checked"' );
		}

		if( $sets['global_comments'] ) {
			$xtpl->assign( 'glob1', ' checked="checked"' );
			$xtpl->assign( 'glob0', null );
		} else {
			$xtpl->assign( 'glob1', null );
			$xtpl->assign( 'glob0', ' checked="checked"' );
		}

		if( $sets['anonymous_comments'] ) {
			$xtpl->assign( 'anon1', ' checked="checked"' );
			$xtpl->assign( 'anon0', null );
		} else {
			$xtpl->assign( 'anon1', null );
			$xtpl->assign( 'anon0', ' checked="checked"' );
		}

		if( $sets['site_open'] ) {
			$xtpl->assign( 'site1', ' checked="checked"' );
			$xtpl->assign( 'site0', null );
		} else {
			$xtpl->assign( 'site1', null );
			$xtpl->assign( 'site0', ' checked="checked"' );
		}
		$xtpl->assign( 'site_closedmessage', htmlspecialchars($sets['site_closedmessage']) );

		$xtpl->assign( 'footer_text', htmlspecialchars($sets['footer_text']) );
		$xtpl->assign( 'copyright_terms', htmlspecialchars($sets['copyright_terms']) );

		$xtpl->assign( 'cookie_prefix', htmlspecialchars($sets['cookie_prefix']) );
		$xtpl->assign( 'cookie_path', htmlspecialchars($sets['cookie_path']) );
		$xtpl->assign( 'cookie_domain', htmlspecialchars($sets['cookie_domain']) );
		$xtpl->assign( 'cookie_logintime', htmlspecialchars($sets['cookie_logintime']) );
		$xtpl->assign( 'cookie_secure', $sets['cookie_secure'] ? ' checked="checked"' : null );

		$xtpl->assign( 'blog_avatar', htmlspecialchars($sets['blog_avatar']) );
		$xtpl->assign( 'blog_postsperpage', $sets['blog_postsperpage'] );
		$xtpl->assign( 'blog_autoclose', $sets['blog_autoclose'] );
		$xtpl->assign( 'blog_icon_width', $sets['blog_icon_width'] );
		$xtpl->assign( 'blog_icon_height', $sets['blog_icon_height'] );
		$xtpl->assign( 'sidebar_comments_count', $sets['sidebar_comments_count'] );
		$xtpl->assign( 'blog_commentsperpage', $sets['blog_commentsperpage'] );
		$xtpl->assign( 'blog_notifycomments', $sets['blog_notifycomments'] ? ' checked="checked"' : null );

		if( $sets['html_email'] ) {
			$xtpl->assign( 'email1', ' checked="checked"' );
			$xtpl->assign( 'email0', null );
		} else {
			$xtpl->assign( 'email1', null );
			$xtpl->assign( 'email0', ' checked="checked"' );
		}

		$xtpl->assign( 'blog_signature_on', $sets['blog_signature_on'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_avatar', $sets['sidebar_avatar'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_search', $sets['sidebar_search'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_calendar', $sets['sidebar_calendar'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_comments', $sets['sidebar_comments'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_images', $sets['sidebar_images'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_blogroll', $sets['sidebar_blogroll'] ? ' checked="checked"' : null );
		$xtpl->assign( 'sidebar_categories', $sets['sidebar_categories'] ? ' checked="checked"' : null );

		$xtpl->assign( 'gallery_thumb_w', $sets['gallery_thumb_w'] );
		$xtpl->assign( 'gallery_thumb_h', $sets['gallery_thumb_h'] );
		$xtpl->assign( 'sidebar_images_count', $sets['sidebar_images_count'] );

		$xtpl->assign( 'lg_download', $sets['download_size'] / 1048576 );

		$xtpl->assign( 'rss_enabled', $sets['rss_enabled'] ? ' checked="checked"' : null );
		$xtpl->assign( 'rss_items', $sets['rss_items'] );
		$xtpl->assign( 'rss_refresh', $sets['rss_refresh'] );
		$xtpl->assign( 'rss_name', htmlspecialchars($sets['rss_name']) );
		$xtpl->assign( 'rss_image_url', htmlspecialchars($sets['rss_image_url']) );
		$xtpl->assign( 'rss_description', htmlspecialchars($sets['rss_description']) );

		$xtpl->assign( 'page_names', htmlspecialchars( implode( "\n", array_keys( $sets['page_links'] ) ) ) );
		$xtpl->assign( 'page_links', htmlspecialchars( implode( "\n", array_values( $sets['page_links'] ) ) ) );

		$xtpl->assign( 'global_announce', htmlspecialchars($sets['global_announce']) );

		$xtpl->parse( 'Settings' );
		return $xtpl->text( 'Settings' );
	}
}
?>