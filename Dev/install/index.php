<?php
/* Sandbox v0.5-1.0b http://sandbox.kiasyn.com
 * Copyright (c) 2006-2007 Sam O'Connor (Kiasyn)
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2015
 * Roger Libiez [Samson] http://www.iguanadons.net
 *
 * Sandbox installer module. Based on QSF Portal installer module.
 * QSF Portal Copyright (c)2006-2015 The QSF Portal Team
 * https://github.com/Arthmoor/QSF-Portal
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
define( 'SANDBOX_INSTALLER', true );

error_reporting(E_ALL);

require_once( '../settings.php' );

$mode = null;
if( isset($_GET['mode']) ) {
	$mode = $_GET['mode'];
}

if ( isset( $_POST['db_type'] ) )
	$settings['db_type'] = $_POST['db_type'];
elseif( $mode != 'upgrade' )
	$settings['db_type'] = 'database';

$settings['include_path'] = '..';
require $settings['include_path'] . '/lib/' . $settings['db_type'] . '.php';
require $settings['include_path'] . '/global.php';

function execute_queries($queries, $db)
{
	foreach ($queries as $query)
	{
		$db->dbquery($query);
	}
}

function check_writeable_files()
{
	// Need to check to see if the necessary directories are writeable.
	$writeable = true;
	$fixme = '';

	if(!is_writeable('../files')) {
		$fixme .= "../files/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/blogpostimages')) {
		$fixme .= "../files/blogpostimages/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/downloads')) {
		$fixme .= "../files/downloads/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/emoticons')) {
		$fixme .= "../files/emoticons/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/gallery')) {
		$fixme .= "../files/gallery/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/gallery/thumbnails')) {
		$fixme .= "../files/gallery/thumbnails/<br />";
		$writeable = false;
	}
	if(!is_writeable('../files/posticons')) {
		$fixme .= "../files/posticons/<br />";
		$writeable = false;
	}

	if( !$writeable ) {
		echo "The following directories are missing or not writeable. Some functions will be impaired unless these are changed to 0777 permission.<br /><br />";
                echo "<span style='color:red'>" . $fixme . "</span>";
	} else {
		echo "<span style='color:green'>Directory permissions are all OK.</span>";
	}
}

if (!isset($_GET['step'])) {
	$step = 1;
} else {
	$step = $_GET['step'];
}

if ($mode) {
	require './' . $mode . '.php';
	$sandbox = new $mode;
} else {
	$sandbox = new module;
}
	$sandbox->settings = $settings;
	$sandbox->self = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'index.php';
	$failed = false;

	$php_version = PHP_VERSION;
	$os = defined('PHP_OS') ? PHP_OS : 'unknown';
	$safe_mode = get_cfg_var('safe_mode') ? 'on' : 'off';
	$register_globals = get_cfg_var('register_globals') ? 'on' : 'off';
	$server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown';

	if( version_compare( PHP_VERSION, "5.3.0", "<" ) ) {
		echo 'Your PHP version is ' . PHP_VERSION . '.<br />Currently only PHP 5.3.0 and higher are supported.';
		$failed = true;
	}

	$db_fail = 0;
	$mysqli = false;
	$pgsql = false;

	if (!extension_loaded('mysqli')) {
		$db_fail++;
	} else {
		$mysqli = true;
	}

	if( !extension_loaded('pgsql')) {
		$db_fail++;
	} else {
		$pgsql = true;
	}

	if ( $db_fail > 1 )
	{
		if ($failed) { // If we have already shown a message, show the next one two lines down
			echo '<br /><br />';
		}

		echo 'Your PHP installation does not support MySQLi, or pgSQL.';
		$failed = true;
	}

	if ($failed) {
		echo "<br /><br /><strong>To run Sandbox and other advanced PHP software, the above error(s) must be fixed by you or your web host.</strong>";
		exit;
	}

	if ($mysqli) {
		$mysqli_client = '<li>MySQLi Client: (' . mysqli_get_client_info() . ')</li><hr />';
	} else {
		$mysqli_client = '';
	}

	if($pgsql) {
		$pgsql_client = '<li>pgSQL Client: Available</li><hr />';
	} else {
		$pgsql_client = '';
	}

	echo "<!DOCTYPE html>
<html lang=\"en-US\">
<head>
 <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
 <title>Sandbox Installer</title>
 <link rel=\"stylesheet\" type=\"text/css\" href=\"../skins/Default/styles.css\" />
</head>

<body>
 <div id='container'>
  <div id='header'>
   <div id='company'>
    <div class='logo'></div>
    <div class='title'><h1>Sandbox Installer {$sandbox->version}</h1></div>
   </div>
  </div>

  <div id='blocks'>
   <div class='block'>
    <ul>
     <li>PHP Version: $php_version</li><hr />
     <li>Operating System: $os</li><hr />
     <li>Safe mode: $safe_mode</li><hr />
     <li>Register globals: $register_globals</li><hr />
     <li>Server Software: $server</li><hr />
     $mysqli_client
     $pgsql_client
    </ul>
   </div>
  </div>

  <div id='main'>";

	switch( $mode )
	{
		default:
			include "choose_install.php";
			break;
		case 'new_install':
			$sandbox->install( $step, $mysqli, $pgsql );
			break;
		case 'upgrade':
			$sandbox->upgrade_site( $step );
			break;
	}

	echo "   <div id='bottom'>&nbsp;</div>
  </div>
  <div id='footer'>
   <a href='http://www.iguanadons.net/'>Sandbox</a> {$sandbox->version} &copy; 2006-2015 Sam O'Connor [<a href='http://www.kiasyn.com'>Kiasyn</a>] and Roger Libiez [<a href='http://www.iguanadons.net'>Samson</a>]
  </div>
 </body>
</html>";
?>