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

if( version_compare( PHP_VERSION, "7.0.0", "<" ) ) {
	die( 'PHP version does not meet minimum requirements. Contact your system administrator.' );
}

define( 'SANDBOX', true );
define( 'SANDBOX_ADM', true );

$time_now   = explode(' ', microtime());
$time_start = $time_now[1] + $time_now[0];

date_default_timezone_set('UTC');

session_start();

$_REQUEST = array();

require './settings.php';
$settings['include_path'] = '.';
require_once $settings['include_path'] . '/global.php';
require_once $settings['include_path'] . '/lib/' . $settings['db_type'] . '.php';
require_once $settings['include_path'] . '/lib/zTemplate.php';
require_once $settings['include_path'] . '/lib/bbcode.php';

set_error_handler('error');
error_reporting(E_ALL);

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
$qstring = null;
$module = null;

if( !isset( $_GET['a'] ) ) {
	$module = 'home';

	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}
} elseif( !empty( $_GET['a'] ) ) {
	$a = trim( $_GET['a'] );

	// Should restrict us to only valid alphabetic characters, which are all that's valid for this software.
	if( !preg_match( '/^[a-zA-Z]*$/', $a ) ) {
		if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
			$qstring = $_SERVER['QUERY_STRING'];
		}

		$missing = true;

		$_SESSION = array();

		session_destroy();

		header( 'Clear-Site-Data: "*"' );
	} elseif( !file_exists( 'admin_modules/' . $a . '.php' ) ) {
		$missing = true;
		$qstring = $_SERVER['REQUEST_URI'];
	} else {
		$module = $a;
	}
} else {
	if( isset( $_SERVER['QUERY_STRING'] ) && !empty( $_SERVER['QUERY_STRING'] ) ) {
		$qstring = $_SERVER['QUERY_STRING'];

		$missing = true;
	}
}

require 'admin_modules/' . $module . '.php';

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

$xtpl = new XTemplate( 'skins/' . $mod->skin . '/AdminCP/index.xtpl' );
$mod->xtpl = $xtpl;

$mod->title = 'Sandbox: Administration Control Panel';

if ( !$mod->login('admin.php') ) {
	header( 'HTTP/1.0 403 Forbidden' );

	setcookie($mod->settings['cookie_prefix'] . 'user', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );
	setcookie($mod->settings['cookie_prefix'] . 'pass', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );

	unset($_SESSION['user']);
	unset($_SESSION['pass']);

	$_SESSION = array();

	$mod->db->close();
	exit( '<h1>Access Denied. Proper authentication was not provided.</h1>' );
} elseif( $mod->user['user_level'] < USER_ADMIN ) {
	header( 'HTTP/1.0 403 Forbidden' );

	setcookie($mod->settings['cookie_prefix'] . 'user', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );
	setcookie($mod->settings['cookie_prefix'] . 'pass', '', $mod->time - 9000, $mod->settings['cookie_path'], $mod->settings['cookie_domain'], $mod->settings['cookie_secure'], true );

	unset($_SESSION['user']);
	unset($_SESSION['pass']);

	$_SESSION = array();

	$mod->db->close();
	exit( '<h1>Access Denied. Proper authentication was not provided.</h1>' );
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

		$xtpl->assign( 'page_title', $mod->title );
		$xtpl->assign( 'style_link', "{$mod->settings['site_address']}skins/{$mod->skin}/admincp.css" );
		$xtpl->assign( 'site_name', htmlspecialchars($mod->settings['site_name']) );
		$xtpl->assign( 'imgsrc', "{$mod->settings['site_address']}skins/{$mod->skin}" );

		$open = $mod->settings['site_open'];
		if( !$open ) {
			$xtpl->assign( 'closed_message', $mod->settings['site_closedmessage'] );
			$xtpl->parse( 'Index.Closed' );
		}

		$spamstored = $mod->db->quick_query( 'SELECT COUNT(spam_id) count FROM %pspam' );
		if( $spamstored['count'] > 0 ) {
			$t = 'are';
			$s = 's';
			if( $spamstored['count'] == 1 ) {
				$t = 'is';
				$s = '';
			}
			$spam_message = 'There ' . $t . ' ' . $spamstored['count'] . ' comment' . $s . ' currently flagged as spam.';

			$xtpl->assign( 'spam_message', $spam_message );
			$xtpl->parse( 'Index.Spam' );
		}

		$xtpl->assign( 'module_output', $module_output );

		$year = date( 'Y', $mod->time );
		$copyright_terms = str_replace( '{year}', $year, $mod->settings['copyright_terms'] );
		$xtpl->assign( 'copyright_terms', $copyright_terms );
		$xtpl->assign( 'version', $mod->version );

		$time_now  = explode(' ', microtime());
		$time_exec = round($time_now[1] + $time_now[0] - $time_start, 4);
		$queries = $mod->db->queries;
		$queries_exec = $mod->db->queries_exec;
		$xtpl->assign( 'page_generated', "Page generated in $time_exec seconds. $queries queries made in $queries_exec seconds." );

		$xtpl->parse( 'Index' );
		$xtpl->out( 'Index' );

		@ob_end_flush();
		@flush();
	}
}
$mod->db->close();
?>