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

define( 'SANDBOX', true );

$time_now   = explode(' ', microtime());
$time_start = $time_now[1] + $time_now[0];

date_default_timezone_set('UTC');

session_start();

// Override session cache control
header( 'Cache-Control: private, max-age=1800, pre-check=1800, must-revalidate' );

$_REQUEST = array();

require './settings.php';
$settings['include_path'] = '.';
require_once $settings['include_path'] . '/global.php';
require_once $settings['include_path'] . '/lib/' . $settings['db_type'] . '.php';
require_once $settings['include_path'] . '/lib/zTemplate.php';
require_once $settings['include_path'] . '/lib/bbcode.php';

set_error_handler('error');
error_reporting(E_ALL);
if( version_compare( PHP_VERSION, "5.3.0", "<" ) ) {
	set_magic_quotes_runtime(0);
}

$dbt = 'db_' . $settings['db_type'];
$db = new $dbt( $settings['db_name'], $settings['db_user'], $settings['db_pass'], $settings['db_host'], $settings['db_pre'] );
if (!$db->db) {
    error(E_USER_ERROR, 'A connection to the database could not be established and/or the specified database could not be found.', __FILE__, __LINE__);
}

/*
 * Logic here:
 * If 'a' is not set, but some other query is, it's a bogus request for this software.
 * If 'a' is set, but the module doesn't exist, it's either a malformed URL or a bogus request.
 * Otherwise $missing remains false and no error is generated later.
 */
$missing = false;
if (!isset($_GET['a']) ) {
	$module = 'blog';
	if( isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']) )
		$missing = true;
} elseif ( !file_exists( 'modules/' . $_GET['a'] . '.php' ) ) {
	$module = 'blog';
	$missing = true;
} else {
	$module = $_GET['a'];
}

if ( strstr($module, '/') || strstr($module, '\\') ) {
	header('HTTP/1.0 403 Forbidden');
	exit( 'You have been banned from this site.' );
}

require 'modules/'  . $module . '.php';

$mod = new $module( $db );
$mod->settings = $mod->load_settings( $settings );
$mod->emoticons = $mod->load_emoticons();
$mod->set_skin();
$mod->bbcode = new bbcode($mod);

if( $mod->ip_banned( $mod->ip ) )
{
	header('HTTP/1.0 403 Forbidden');
	exit( 'You have been banned from this site.' );
}

if( isset($mod->get['s']) && $mod->get['s'] == 'logout' ) {
	$mod->logout();
	exit;
}

if( !$mod->login('index.php') ) {
	$mod->user['user_name'] = 'Anonymous';
	$mod->user['user_level'] = USER_GUEST;
	$mod->user['user_id'] = 1;
	$mod->user['user_timezone'] = 'UTC';
}

$xtpl = new XTemplate( './skins/' . $mod->skin . '/index.xtpl' );
$mod->xtpl = $xtpl;

$xtpl->assign( 'site_link', $mod->settings['site_address'] );
$xtpl->assign( 'site_name', htmlspecialchars($mod->settings['site_name']) );
$xtpl->assign( 'site_tagline', htmlspecialchars($mod->settings['site_tagline']) );
$xtpl->assign( 'mobile_icons', $mod->settings['mobile_icons'] );

$mod->title = 'Site Title Not Set';
if( isset($mod->settings['site_name']) && !empty($mod->settings['site_name']) )
	$mod->title = $mod->settings['site_name'];

$site_keywords = null;
if( isset($mod->settings['site_keywords']) )
	$site_keywords = "<meta name=\"keywords\" content=\"{$mod->settings['site_keywords']}\" />";
$xtpl->assign( 'site_keywords', $site_keywords );

// Set the defaults specified by the site owners, or leave out if not supplied.
$mod->meta_description( null );
if( isset($mod->settings['site_meta']) && !empty($mod->settings['site_meta']) )
	$mod->meta_description( $mod->settings['site_meta'] );

$style_link = "{$mod->settings['site_address']}skins/{$mod->skin}/styles.css";

$date = $mod->t_date( $mod->time );
$year = date( 'Y', $mod->time );

$spam = 0;
if( isset($mod->settings['spam_count']))
	$spam = $mod->settings['spam_count'];

$footer_text = str_replace( '{date}', $date, $mod->settings['footer_text'] );
$footer_text = str_replace( '{spam}', $spam, $footer_text );
$xtpl->assign( 'footer_text', $footer_text );

$copyright_terms = str_replace( '{year}', $year, $mod->settings['copyright_terms'] );
$xtpl->assign( 'copyright_terms', $copyright_terms );

$open = $mod->settings['site_open'];
if ( !$open && $mod->user['user_level'] < USER_ADMIN ) {
	$xtpl->assign( 'page_title', $mod->title );
	$xtpl->assign( 'meta_desc', $mod->meta_description );
	$xtpl->assign( 'style_link', $style_link );
	$xtpl->assign( 'random_quote', 'Greetings Professor Falken' );
	$xtpl->assign( 'closed_message', $mod->settings['site_closedmessage'] );

	$xtpl->parse( 'Index.RandomQuote' );
	$xtpl->parse( 'Index.Closed' );
	$xtpl->parse( 'Index' );
	$xtpl->out( 'Index' );
} else {
	if( $missing ) {
		$module_output = $mod->error( 'The page you requested does not exist.', 404 );
	} else {
		$module_output = $mod->execute();
	}

	if ( $mod->nohtml ) {
		echo $module_output;
	} else {
		ob_start('ob_gzhandler');

		$xtpl->assign( 'meta_desc', $mod->meta_description );
		$xtpl->assign( 'page_title', $mod->title );

		// Users in privledged class or above can use their own style sheet for the skin.
		if( $mod->user['user_level'] > USER_MEMBER && !empty( $mod->user['user_stylesheet'] ) )
			$style_link = $mod->user['user_stylesheet'];
		$xtpl->assign( 'style_link', $style_link );

		$nav_links = null;
		foreach( $mod->settings['page_links'] as $name => $link )
		{
			$name = trim($name);
			$link = trim($link);

			$selected = null;
			if ( $link == '/' && $module == 'blog' )
				$selected = ' class="selected"';
			else {
				if( strstr( $link, $module ) !== FALSE )
					$selected = ' class="selected"';
			}

			if( $link == '/' )
				$link = '';
			if( strpos( $link, '://' ) === false )
				$nav_links .= "<li$selected><a href=\"{$mod->settings['site_address']}$link\">$name</a></li>\n";
			else
				$nav_links .= "<li$selected><a href=\"$link\">$name</a></li>\n";
		}

		if( $nav_links ) {
			$xtpl->assign( 'nav_links', $nav_links );
			$xtpl->parse( 'Index.NavLinks' );
		}

		$quote = $mod->db->quick_query( 'SELECT quote_text FROM %prandom_quotes ORDER BY RAND() LIMIT 1' );
		if( $quote ) {
			$random_quote = $mod->format( $quote['quote_text'], POST_BBCODE );

			$xtpl->assign( 'random_quote', $random_quote );
			$xtpl->parse( 'Index.RandomQuote' );
		}

		if( $mod->settings['rss_enabled'] ) {
			if( $mod->settings['friendly_urls'] ) {
				$rss = $mod->settings['site_address'] . 'rss';
				$rss_gallery = $mod->settings['site_address'] . 'rss-gallery';
				$rss_downloads = $mod->settings['site_address'] . 'rss-downloads';
				$rss_comments = $mod->settings['site_address'] . 'rss-comments';
			} else {
				$rss = 'index.php?a=rss';
				$rss_gallery = 'index.php?a=rss&amp;type=gallery';
				$rss_downloads = 'index.php?a=rss&amp;type=downloads';
				$rss_comments = 'index.php?a=rss&amp;type=comments';
			}

			$rss_feeds = "<link rel=\"alternate\" title=\"{$mod->settings['site_name']} blog entries\" href=\"$rss\" type=\"application/rss+xml\" />
  <link rel=\"alternate\" title=\"{$mod->settings['site_name']} blog comments\" href=\"$rss_comments\" type=\"application/rss+xml\" />
  <link rel=\"alternate\" title=\"{$mod->settings['site_name']} image gallery\" href=\"$rss_gallery\" type=\"application/rss+xml\" />
  <link rel=\"alternate\" title=\"{$mod->settings['site_name']} downloads\" href=\"$rss_downloads\" type=\"application/rss+xml\" />";

			$xtpl->assign( 'rss_feeds', $rss_feeds );
			$xtpl->parse( 'Index.RSS' );
		}

		$xtpl->assign( 'module_output', $module_output );

		$google = null;
  		if( $mod->settings['site_analytics'] ) {
			$google = $mod->settings['site_analytics'];
		}

		// Google +1 button
		$google .= "<script type=\"text/javascript\">
		  (function() {
		    var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
		    po.src = 'https://apis.google.com/js/plusone.js';
		    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
		  })();
		</script>";

		$xtpl->assign( 'google', $google );

		if( !$open ) {
			$xtpl->assign( 'closed_message', htmlspecialchars($mod->settings['site_closedmessage']) );
			$xtpl->parse( 'Index.Closed' );
		}

		if( $mod->user['user_level'] > USER_PRIVILEGED ) {
			$spamstored = $mod->db->quick_query( 'SELECT COUNT(spam_id) count FROM %pspam' );
			if( $spamstored['count'] > 0 ) {
				$t = 'are';
				$s = 's';
				if( $spamstored['count'] == 1 ) {
					$t = 'is';
					$s = '';
				}
				$spam_message = 'There ' . $t . ' ' . $spamstored['count'] . ' comment' . $s . ' currently flagged as spam.';
				$spam_link = $mod->settings['site_address'] . 'index.php?a=spam_control';

				$xtpl->assign( 'spam_link', $spam_link );
				$xtpl->assign( 'spam_message', $spam_message );
				$xtpl->parse( 'Index.Spam' );
			}
		}

		if( !empty($mod->settings['global_announce']) ) {
			$announcement = $mod->format( $mod->settings['global_announce'], POST_BBCODE );

			$xtpl->assign( 'global_announcement', $announcement );
			$xtpl->parse( 'Index.GlobalAnnouncement' );
		}

		// No need for guests to see this.
		if( $mod->user['user_level'] > USER_VALIDATING ) {
			$time_now  = explode(' ', microtime());
			$time_exec = round($time_now[1] + $time_now[0] - $time_start, 4);
			$queries = $mod->db->queries;
			$queries_exec = $mod->db->queries_exec;
			$xtpl->assign( 'page_generated', "Page generated in $time_exec seconds. $queries queries made in $queries_exec seconds." );
			$xtpl->parse( 'Index.PageStats' );
		}

		$xtpl->parse( 'Index' );
		$xtpl->out( 'Index' );

		@ob_end_flush();
		@flush();
	}
	error_reporting(0); // The active users info isn't important enough to care about errors with it.
	require_once( 'modules/active_users.php' );
	do_active($mod, $module);
}
$mod->db->close();
?>