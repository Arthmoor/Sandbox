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

define( 'POST_HTML', 1 );
define( 'POST_BBCODE', 2 );
define( 'POST_PUBLISHED', 4 );
define( 'POST_CLOSED', 8 );
define( 'POST_OVERRIDE', 16 );
define( 'POST_MEMBERSONLY', 32 );
define( 'POST_SIDEBAR', 64 );
define( 'POST_BREAKS', 128 );
define( 'POST_EMOTICONS', 256 );
define( 'POST_HAS_IMAGE', 512 );
define( 'POST_RESTRICTED_COMMENTS', 1024 );

define( 'COMMENT_BLOG', 0 );
define( 'COMMENT_GALLERY', 1 );
define( 'COMMENT_FILE', 2 );

define( 'USER_GUEST', 1 );
define( 'USER_VALIDATING', 2 );
define( 'USER_MEMBER', 3 );
define( 'USER_PRIVILEGED', 4 );
define( 'USER_CONTRIBUTOR', 5 );
define( 'USER_ADMIN', 6 );

define( 'PERM_URL', 1 );
define( 'PERM_SIG', 2 );
define( 'PERM_ICON', 4 );

define( 'SANDBOX_QUERY_ERROR', 6 ); // For SQL errors to be reported properly by the error handler.

class module
{
	var $version		= 2.41;
	var $title		= null;
	var $meta_description	= null;
	var $skin		= 'Default';
	var $skins		= array();
	var $nohtml		= false;
	var $settings		= array();
	var $time		= 0;
	var $db			= null;
	var $server		= array();
	var $cookie		= array();
	var $post		= array();
	var $get		= array();
	var $files		= array();
	var $templates		= array();
	var $emoticons		= array();	  // Array of emoticons used for processing post formatting
	var $ip			= '127.0.0.1';
	var $agent		= 'Unknown';
	var $referrer		= 'Unknown';
	var $user		= array();
	var $xtpl		= null;
	var $postimages_dir	= null;
	var $file_dir		= null;
	var $gallery_dir	= null;
	var $thumb_dir		= null;
	var $icon_dir		= null;

	public function __construct( $db = null )
	{
		$this->time	= time();
		$this->server	= $_SERVER;
		$this->cookie	= $_COOKIE;
		$this->post	= $_POST;
		$this->get	= $_GET;
		$this->files	= $_FILES;

		$this->db = $db;

		$this->ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

		$this->agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '-';
		$this->agent = substr($this->agent, 0, 254); // Cut off after 255 characters.

		$this->referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '-';
		$this->referrer = substr($this->agent, 0, 254); // Cut off after 255 characters.

		$this->postimages_dir = 'files/blogpostimages/';
		$this->file_dir = 'files/downloads/';
		$this->gallery_dir = 'files/gallery/';
		$this->thumb_dir = 'files/gallery/thumbnails/';
		$this->icon_dir = 'files/posticons/';
		$this->emote_dir = 'files/emoticons/';

		if( version_compare( PHP_VERSION, "5.3.0", "<" ) ) {
			// Undo any magic quote slashes!
			if (get_magic_quotes_gpc()) {
				$this->unset_magic_quotes_gpc($this->get);
				$this->unset_magic_quotes_gpc($this->post);
				$this->unset_magic_quotes_gpc($this->cookie);
			}
		}
	}

 	/**
	 * Sets magic_quotes_gpc to off
	 *
	 * @param array $array Array to stripslashes
	 **/
	function unset_magic_quotes_gpc(&$array)
	{
		$keys = array_keys($array);
		for($i = 0; $i < count($array); $i++)
		{
			if (is_array($array[$keys[$i]])) {
				$this->unset_magic_quotes_gpc($array[$keys[$i]]);
			} else {
				$array[$keys[$i]] = stripslashes($array[$keys[$i]]);
			}
		}
	}

	function clean_url( $link )
	{
		$link = preg_replace( "/[^a-zA-Z0-9\- ]/", "", $link );
		$link = str_replace( ' ', '-', $link );

		return $link;
	}

	function title( $title )
	{
		$this->title .= ' &raquo; ' . htmlspecialchars($title);
	}

	function meta_description( $desc )
	{
		if( $desc != null ) {
			$desc = htmlspecialchars( $desc );
			$this->meta_description = "<meta name=\"description\" content=\"$desc\" />";
		}
		else
			$this->meta_description = null;
	}

	function set_skin( $skin = null )
	{
		$this->skins = $this->get_skins();
		if ( !$skin )
			$skin = $this->settings['site_defaultskin'];

		$skin = isset( $this->cookie['skin'] ) ? $this->cookie['skin'] : $skin;

		if ( !$skin || !in_array($skin,$this->skins) )
			return;
		$this->skin = $skin;

		setcookie($this->settings['cookie_prefix'] . 'skin', $skin, $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
	}

	function get_skins()
	{
		$skins = array();
		if ( $dh = opendir('./skins/') )
		{
			while( ( $item = readdir($dh) ) !== false )
				if ( $item[0] != '.' && is_dir('./skins/' . $item) )
					$skins[] = $item;
			closedir( $dh );
		}
		return $skins;
	}

	function load_emoticons()
	{
		$emotes = array();
		$dbemotes = $this->db->dbquery('SELECT * FROM %pemoticons');
		while( $e = $this->db->assoc($dbemotes) )
		{
			if( $e['emote_clickable'] == 1 )
				$emotes['click_replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
			else
				$emotes['replacement'][$e['emote_string']] = '<img src="' . $this->settings['site_address'] . 'files/emoticons/' . $e['emote_image'] . '" alt="' . $e['emote_string'] . '" />';
		}
		return $emotes;
	}

	function load_settings($settings)
	{
		// Converts old serialized array into a json encoded array due to potential exploits in the PHP serialize/unserialize functions.
		$settings_array = array();

		$sets = $this->db->quick_query( "SELECT settings_version, settings_value FROM %psettings LIMIT 1" );

		if( !is_array( $sets ) )
			return $settings;

		if( $sets['settings_version'] == 1 ) {
			$settings_array = array_merge( $settings, unserialize($sets['settings_value']) );
			$this->db->dbquery( "UPDATE %psettings SET settings_version=2" );
			$this->settings = $settings_array;
			$this->save_settings();
		} else {
			$settings_array = array_merge( $settings, json_decode($sets['settings_value'], true) );
		}
		return $settings_array;
	}

	function save_settings()
	{
		$default_settings = array( 'db_name', 'db_user', 'db_pass', 'db_host', 'db_pre', 'db_type', 'error_email' );

		$settings = array();

		foreach( $this->settings as $set => $val )
			if ( !in_array( $set, $default_settings ) )
				$settings[$set] = $val;

		$this->db->dbquery( "UPDATE %psettings SET settings_value='%s'", json_encode($settings) );
	}

	function logout()
	{
		setcookie($this->settings['cookie_prefix'] . 'user', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
		setcookie($this->settings['cookie_prefix'] . 'pass', '', $this->time - 9000, $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

		unset($_SESSION['user']);
		unset($_SESSION['pass']);

		$_SESSION = array();
		header( 'Location: index.php' );
	}

	function login( $page )
	{
		if( isset($this->post['username']) && isset($this->post['password']) ) {
			$username = $this->post['username'];
			$password = $this->post['password'];

			$user = $this->db->quick_query( "SELECT * FROM %pusers WHERE user_name='%s' LIMIT 1", $username );
			if( !$user )
				return false;

			if( !isset($user['user_id']) )
				return false;

			if( !password_verify( $password, $user['user_password'] ) )
				return false;

			$hashcheck = $this->check_hash_update( $password, $user['user_password'] );
			if( $hashcheck != $user['user_password'] ) {
				$user['user_password'] = $hashcheck;

				$this->db->dbquery( "UPDATE %pusers SET user_password='%s' WHERE user_id=%d", $user['user_password'], $user['user_id'] );
			}

			setcookie($this->settings['cookie_prefix'] . 'user', $user['user_id'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );
			setcookie($this->settings['cookie_prefix'] . 'pass', $user['user_password'], $this->time + $this->settings['cookie_logintime'], $this->settings['cookie_path'], $this->settings['cookie_domain'], $this->settings['cookie_secure'], true );

			$this->user = $user;
			header( 'Location: ' . $page );
		} else if(isset($this->cookie[$this->settings['cookie_prefix'] . 'user']) && isset($this->cookie[$this->settings['cookie_prefix'] . 'pass'])) {
			$cookie_user = intval($this->cookie[$this->settings['cookie_prefix'] . 'user']);
			$cookie_pass = $this->cookie[$this->settings['cookie_prefix'] . 'pass'];

			$user = $this->db->quick_query( "SELECT * FROM %pusers WHERE user_id=%d AND user_password='%s'", $cookie_user, $cookie_pass );

			if( !isset($user['user_id']) )
				return false;
		} else {
			return false;
		}

		$this->user = $user;
		return true;
	}

	function t_date( $time = 0, $rssfeed = false )
	{
		if (!$time) {
			$time = $this->time;
		}

		$timezone = $this->user['user_timezone'];

		if( $this->user['user_level'] < USER_VALIDATING )
			$timezone = $this->settings['blog_timezone'];

		$dt = new DateTime();
		$dt->setTimezone( new DateTimeZone( $timezone ) );
		$dt->setTimestamp( $time );

		if( $rssfeed == false )
			return $dt->format( $this->settings['blog_dateformat'] );

		// ISO822 format is standard for XML feeds
		return $dt->format( 'D, j M Y H:i:s T' );
	}

	function select_timezones( $zone, $variable_name )
	{
		$out = null;

		$zones = array(
			'-12'			=> 'GMT-12    - Baker Island/International Dateline West',
			'Pacific/Pago_Pago'	=> 'GMT-11    - Pacific: Midway Islands',
			'America/Adak'		=> 'GMT-10    - USA: Alaska (Aleutian Islands)',
			'Pacific/Honolulu'	=> 'GMT-10    - USA: Hawaii Time Zone',
			'America/Anchorage'	=> 'GMT-9     - USA: Alaska Time Zone',
			'America/Los_Angeles'	=> 'GMT-8     - US/Canada: Pacific Time Zone',
			'America/Denver'	=> 'GMT-7     - US/Canada: Mountain Time Zone',
			'America/Phoenix'	=> 'GMT-7     - USA: Mountain Time Zone (Arizona)',
			'America/Chicago'	=> 'GMT-6     - US/Canada: Central Time Zone',
			'America/New_York'	=> 'GMT-5     - US/Canada: Eastern Time Zone',
			'America/Halifax'	=> 'GMT-4     - US/Canada: Atlantic Time Zone',
			'America/St_Johns'	=> 'GMT-3.5   - Canada: Newfoundland',
			'America/Argentina/Buenos_Aires'	=> 'GMT-3     - Argentina',
			'America/Sao_Paulo'	=> 'GMT-3     - Brazil: Sao Paulo',
			'America/Noronha'	=> 'GMT-2     - Brazil: Atlantic islands/Noronha',
			'Atlantic/Azores'	=> 'GMT-1     - Europe: Portugal/Azores',
			'Europe/London'		=> 'GMT       - Europe: Greenwich Mean Time (UK/Ireland)',
			'Atlantic/Reykjavik'	=> 'GMT       - Europe: Greenwich Mean Time (Iceland)',
			'Europe/Berlin'		=> 'GMT+1     - Europe: France/Germany/Spain',
			'Europe/Athens'		=> 'GMT+2     - Europe: Greece (Athens)',
			'Europe/Moscow'		=> 'GMT+3     - Europe: Russia (Moscow)',
			'Asia/Tehran'		=> 'GMT+3.5   - Asia: Iran',
			'Asia/Dubai'		=> 'GMT+4     - Asia: Oman/United Arab Emerites',
			'Asia/Kabul'		=> 'GMT+4.5   - Asia: Afghanistan',
			'Asia/Karachi'		=> 'GMT+5     - Asia: Pakistan',
			'Asia/Kolkata'		=> 'GMT+5.5   - Asia: India',
			'Asia/Almaty'		=> 'GMT+6     - Asia: Kazakhstan',
			'Asia/Yangon'		=> 'GMT+6.5   - Asia: Myanmar',
			'Asia/Bangkok'  	=> 'GMT+7     - Asia: Thailand/Cambodia/Laos',
			'Asia/Shanghai'		=> 'GMT+8     - Asia: China/Mongolia/Phillipines',
			'Australia/Perth'	=> 'GMT+8     - Australia: Western (Perth)',
			'Australia/Eucla'	=> 'GMT+8.75  - Australia: Western (Eucla)',
			'Asia/Tokyo'		=> 'GMT+9     - Asia: Japan/Korea/New Guinea',
			'Australia/Broken_Hill'	=> 'GMT+9.5   - Australia: New South Wales (Yancowinna)',
			'Australia/Darwin'	=> 'GMT+9.5   - Australia: Northern Territory (Darwin)',
			'Australia/Brisbane'    => 'GMT+10    - Australia: Queensland',
			'Australia/Hobart'	=> 'GMT+10    - Australia: Tasmania',
			'Australia/Melbourne'	=> 'GMT+10    - Australia: Victoria/New South Wales',
			'Australia/Lord_Howe'	=> 'GMT+10.5  - Australia: Lord Howe Island',
			'Pacific/Bougainville'	=> 'GMT+11    - Pacific: Solomon Islands/Vanuatu/New Caledonia',
			'Asia/Kamchatka'	=> 'GMT+12    - Asia: Kamchatka',
			'Pacific/Auckland'	=> 'GMT+12    - Pacific: New Zealand/Fiji',
			'Pacific/Funafuti'	=> 'GMT+12    - Pacific: Tuvalu/Marshall Islands',
			'Pacific/Chatham'	=> 'GMT+12.75 - Pacific: Chatham Islands',
			'Pacific/Tongatapu'	=> 'GMT+13    - Pacific: Tonga/Phoenix Islands',
			'Pacific/Kiritimati'	=> 'GMT+14    - Pacific: Line Islands'
		);

		foreach ($zones as $offset => $zone_name)
		{
			$out .= "<option value='$offset'" . (($offset == $zone) ? ' selected=\'selected\'' : null) . ">$zone_name</option>\n";
		}

		return "<select name=\"$variable_name\">$out</select>";
	}

	function format( $in, $options = POST_BBCODE )
	{
		return $this->bbcode->format( $in, $options );
	}

	function closed_content( $content, $type )
	{
		// All comments disabled
		if( $this->settings['global_comments'] == 0 )
			return true;

		// Global Anonymous comment restriction
		if( $this->settings['anonymous_comments'] == 0 && $this->user['user_level'] < USER_MEMBER )
			return true;

		if( $type == COMMENT_BLOG ) {
			// Manual close. Always return true regardless of other settings.
			if( ( $content['post_flags'] & POST_CLOSED ) )
				return true;

			// Registered members can reply to posts regardless of age.
			if( $this->user['user_level'] >= USER_MEMBER )
				return false;

			// Autoclose override - if it's not set, and the time has passed, returns true.
			if( !( $content['post_flags'] & POST_OVERRIDE ) && $this->settings['blog_autoclose'] != 0 ) {
				if( $this->time - $content['post_date'] > 86400 * $this->settings['blog_autoclose'] )
					return true;
			}
		} elseif( $type == COMMENT_GALLERY ) {
			// Manual close. Always return true regardless of other settings.
			if( ( $content['photo_flags'] & POST_CLOSED ) )
				return true;

			// Registered members can reply to posts regardless of age.
			if( $this->user['user_level'] >= USER_MEMBER )
				return false;

			// Autoclose override - if it's not set, and the time has passed, returns true.
			if( !( $content['photo_flags'] & POST_OVERRIDE ) && $this->settings['blog_autoclose'] != 0 ) {
				if( $this->time - $content['photo_date'] > 86400 * $this->settings['blog_autoclose'] )
					return true;
			}
		} elseif( $type == COMMENT_FILE ) {
			// Manual close. Always return true regardless of other settings.
			if( ( $content['file_flags'] & POST_CLOSED ) )
				return true;

			// Registered members can reply to posts regardless of age.
			if( $this->user['user_level'] >= USER_MEMBER )
				return false;

			// Autoclose override - if it's not set, and the time has passed, returns true.
			if( !( $content['file_flags'] & POST_OVERRIDE ) && $this->settings['blog_autoclose'] != 0 ) {
				if( $this->time - $content['file_date'] > 86400 * $this->settings['blog_autoclose'] )
					return true;
			}
		}

		// Not manually closed but set to override, so just return false now.
		return false;
	}

	function message( $title, $message, $link_name = null, $link = null, $delay = 4 )
	{
		if( $link && $delay > 0 )
			@header('Refresh: '.$delay.';url=' . $link);

		if( $link_name )
			$link_name = '<div style="text-align:center"><a href="'. $link . '">' . $link_name . '</a></div>';

		$this->xtpl->assign( 'title', $title );
		$this->xtpl->assign( 'message', $message );
		$this->xtpl->assign( 'link_name', $link_name );
		$this->xtpl->parse( 'Index.Message' );

		return '';
	}

	function generate_files_list($file = null)
	{
		$upload_dir = realpath($this->postimages_dir);

		$dp  = opendir($upload_dir);
		while (false !== ($filename = readdir($dp)))
			if ( !is_dir($upload_dir . $filename) )
			   $files[] = $filename;

		sort($files);
		closedir($dp);

		$list = '<option>No Image</option>';
		foreach( $files as $f )
		{
			if ( $f == '.' || $f == '..' )
				continue;
			$f2 = htmlspecialchars($f);

			$selected = null;
			if( $file == $f )
				$selected = ' selected="selected"';
			$list .= "<option value=\"$f\"$selected>$f2</option>\n";
		}
		return $list;
	}

	/**
	 * Generate social media links. (Twitter, Facebook, etc)
	 *
	 * @param XTemplate $template Template object to assign the values to.
	 * @param string $data Data for the links to operate on.
	 * 
	 * @author Samson
	 * @since 2.11
	 **/
	function generate_social_links($template, $subject, $url)
	{
		$reddit_subject = str_replace( " ", "+", $subject );
		$encoded_url = urlencode( $url );

		$template->assign( 'delicious', "<a href=\"javascript:void(0);\" title=\"Share on Delicious\" target=\"sandbox_social\" onclick=\"CenterPopUp('https://del.icio.us/post?url={$url}&amp;title={$subject}','sandbox_social',900,600)\"><img src=\"{$this->settings['site_address']}skins/{$this->skin}/images/delicious.png\" alt=\"\" /></a>" );
		$template->assign( 'reddit', "<a href=\"javascript:void(0);\" title=\"Share on Reddit\" target=\"sandbox_social\" onclick=\"CenterPopUp('https://www.reddit.com/submit?url={$url}&amp;title={$reddit_subject}','sandbox_social',865,950)\"><img src=\"{$this->settings['site_address']}skins/{$this->skin}/images/reddit.png\" alt=\"\" /></a>" );
		$template->assign( 'facebook', "<a href=\"javascript:void(0);\" title=\"Share on Facebook\" target=\"sandbox_social\" onclick=\"CenterPopUp('http://www.facebook.com/sharer.php?u={$url}','sandbox_social',950,600)\"><img src=\"{$this->settings['site_address']}skins/{$this->skin}/images/facebook-logo.png\" alt=\"\" /></a>" );
		$template->assign( 'twitter', "<a href=\"javascript:void(0);\" title=\"Share on Twitter\" target=\"sandbox_social\" onclick=\"CenterPopUp('https://twitter.com/share?text={$subject}&amp;url={$url}','sandbox_social',550,420)\"><img src=\"{$this->settings['site_address']}skins/{$this->skin}/images/twitter-logo.png\" alt=\"\" /></a>" );
	}

	/**
	 * Deliver an error message.
	 *
	 * @param string $message The error message to be delivered.
	 * @param bool $send404 Should this message result in a "Page not found" error?
	 * 
	 * @author Kiasyn, Samson
	 * @since 0.3
	 **/
	function error( $message, $errorcode = 0 )
	{
		$error_text = 'Unknown Error';

		switch( $errorcode )
		{
			case 403:
				$error_text = '403 Forbidden';
				header('HTTP/1.0 403 Forbidden');
				break;
			case 404:
				$error_text = '404 Not Found';
				header('HTTP/1.0 404 Not Found');
				$message .= '<br />If you followed a link from an external resource, you should notify the webmaster there that the link may be broken.';
				break;
			default: break;
		}
		return $this->message( 'Error: ' . $error_text, $message );
	}

	function createthumb( $name, $filename, $ext, $new_w, $new_h )
	{
		$system = explode( '.', $name );
		$src_img = null;

		if( preg_match( '/jpg|jpeg/', $ext ) )
			$src_img = imagecreatefromjpeg($name);
		else if ( preg_match( '/png/', $ext ) )
			$src_img = imagecreatefrompng($name);
		else if ( preg_match( '/gif/', $ext ) )
			$src_img = imagecreatefromgif($name);
		$old_x = imageSX( $src_img );
		$old_y = imageSY( $src_img );

		if ($old_x > $old_y)
		{
			$thumb_w = $new_w;
			$thumb_h = $old_y * ( $new_h / $old_x );
		}
		if ($old_x < $old_y)
		{
			$thumb_w = $old_x * ( $new_w / $old_y );
			$thumb_h = $new_h;
		}
		if ($old_x == $old_y)
		{
			$thumb_w = $new_w;
			$thumb_h = $new_h;
		}

		$dst_img = ImageCreateTrueColor( $thumb_w, $thumb_h );
		imagecopyresampled( $dst_img, $src_img, 0, 0, 0, 0, $thumb_w, $thumb_h, $old_x, $old_y );
		if (preg_match( '/png/', $ext ) )
			imagepng( $dst_img, $filename );
		else if ( preg_match( '/jpg|jpeg/', $ext ) )
			imagejpeg( $dst_img, $filename );
		else
			imagegif( $dst_img, $filename );
		imagedestroy( $dst_img );
		imagedestroy( $src_img );
		return array( 'width' => $old_x, 'height' => $old_y );
	}

	function valid_user( $name )
	{
		return !preg_match( '/[^a-zA-Z0-9_\\@]/', $name );
	}

	function is_email( $addr )
	{
		return filter_var( $addr, FILTER_VALIDATE_EMAIL );
	}

	function display_icon($icon)
	{
		$url = $this->settings['site_address'] . $this->icon_dir . $icon;

		if( $this->is_email($icon) ) {
			$url = 'https://secure.gravatar.com/avatar/';
			$url .= md5( strtolower( trim($icon) ) );
			$url .= "?s={$this->settings['blog_icon_width']}&amp;r=pg";
		}

		return $url;
	}

	/**
	 * Hash a given string into a password suitable for database use
	 *
	 * @param string $pass The supplied password to hash
	 * @author Samson
	 * @since 2.3.1
	 */
	function sandbox_password_hash($pass)
	{
		$options = [ 'cost' => 12, ];
		$newpass = password_hash( $pass, PASSWORD_DEFAULT, $options );

		return $newpass;
	}

	/**
	 * Check to see if a given password has needs to be updated to a new hash algorithm
	 *
	 * @param string $password The unencrypted password to rehash
	 * @param string $hash The hashed password to check
	 * @author Samson
	 * @since 2.4.0
	 */
	function check_hash_update( $password, $hash )
	{
		$options = [ 'cost' => 12, ];

		if( password_needs_rehash( $hash, PASSWORD_DEFAULT, $options ) ) {
			$newhash = password_hash( $password, PASSWORD_DEFAULT, $options );

			$hash = $newhash;
		}
		return $hash;
	}

	/**
	 * Generates a random pronounceable password
	 *
	 * @param int $length Length of password
	 * @author http://www.zend.com/codex.php?id=215&single=1
	 * @since 1.1.0
	 */
	function generate_pass($length)
	{
		$vowels = array('a', 'e', 'i', 'o', 'u');
		$cons = array('b', 'c', 'd', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'u', 'v', 'w', 'tr',
		'cr', 'br', 'fr', 'th', 'dr', 'ch', 'ph', 'wr', 'st', 'sp', 'sw', 'pr', 'sl', 'cl');

		$num_vowels = count($vowels);
		$num_cons = count($cons);

		$password = '';

		for ($i = 0; $i < $length; $i++)
		{
			$password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
		}

		return substr($password, 0, $length);
	}

	function cidrmatch( $cidr )
	{
		$ip = decbin( ip2long($this->ip) );
		list( $cidr1, $cidr2, $cidr3, $cidr4, $bits ) = sscanf( $cidr, '%d.%d.%d.%d/%d' );
		$cidr = decbin( ip2long( "$cidr1.$cidr2.$cidr3.$cidr4" ) );
		for( $i = strlen($ip); $i < 32; $i++ )
			$ip = "0$ip";
		for( $i = strlen($cidr); $i < 32; $i++ )
			$cidr = "0$cidr";
		return !strcmp( substr($ip, 0, $bits), substr($cidr, 0, $bits) );
	}

	function is_ipv6( $ip )
	{
		return( substr_count( $ip, ":" ) > 0 && substr_count( $ip, "." ) == 0 );
	}

	function ip_banned( )
	{
		if ( isset($this->settings['banned_ips']) )
		{
			foreach ($this->settings['banned_ips'] as $ip)
			{
				if( $this->is_ipv6( $this->ip ) ) {
					if( !strcasecmp( $ip, $this->ip ) )
						return true;
				}

				if ( ( strstr($ip, '/') && $this->cidrmatch($ip) ) || !strcasecmp( $ip, $this->ip ) )
					return true;
			}
		}
		return false;
	}

	function ReverseIPOctets($inputip)
	{
		$ipoc = explode( ".", $inputip );
		return $ipoc[3] . "." . $ipoc[2] . "." . $ipoc[1] . "." . $ipoc[0];
	}

	function IsTorExitPoint( $ip )
	{
		if( gethostbyname( $this->ReverseIPOctets($ip) . "." . $_SERVER['SERVER_PORT'] . "." . $this->ReverseIPOctets($_SERVER['SERVER_ADDR']) . ".ip-port.exitlist.torproject.org" ) == "127.0.0.2" )
		{
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Generates a random security token for forms.
	 *
	 * @author Roger Libiez
	 * @return string Generated security token.
	 * @since 1.1.9
	 */
	function generate_token()
	{
		$token = md5(uniqid(mt_rand(), true));
		$_SESSION['token'] = $token;
		$_SESSION['token_time'] = $this->time + 7200; // Token is valid for 2 hours.

		return $token;
	}

	/**
	 * Checks to be sure a submitted security token matches the one the form is expecting.
	 *
	 * @author Roger Libiez
	 * @return false if invalid, true if valid
	 * @since 1.1.9
	 */
	function is_valid_token()
	{
		if( !isset($_SESSION['token']) || !isset($_SESSION['token_time']) || !isset($this->post['token']) ) {
			return false;
		}

		if( $_SESSION['token'] != $this->post['token'] ) {
			return false;
		}

		$age = $this->time - $_SESSION['token_time'];

		if( $age > 7200 ) // Token is valid for 2 hours.
			return false;

		return true;
	}
}

function get_backtrace()
{
	$backtrace = debug_backtrace();
	$out = "Backtrace:\n\n";

	foreach( $backtrace as $trace => $frame )
	{
		// 2 is the file that actually died. We don't need to list the error handlers in the trace.
		if( $trace < 2 ) {
			continue;
		}
		$args = array();

		if( $trace > 2 ) { // The call in the error handler is irrelevent anyway, so don't bother with the arg list
			if ( isset( $frame['args'] ) )
			{
				foreach( $frame['args'] as $arg )
				{
					if ( is_array( $arg ) && array_key_exists( 0, $arg ) && is_string( $arg[0] ) ) {
						$argument = htmlspecialchars( $arg[0] );
					} elseif( is_string( $arg ) ) {
						$argument = htmlspecialchars( $arg );
					} else {
						$argument = NULL;
					}
					$args[] = "'{$argument}'";
				}
			}
		}

		$frame['class'] = (isset($frame['class'])) ? $frame['class'] : '';
		$frame['type'] = (isset($frame['type'])) ? $frame['type'] : '';
		$frame['file'] = (isset($frame['file'])) ? $frame['file'] : '';
		$frame['line'] = (isset($frame['line'])) ? $frame['line'] : '';

		$func = '';
		$arg_list = implode(", ", $args);
		if( $trace == 2 ) {
			$func = 'See above for details.';
		} else {
			$func = htmlspecialchars($frame['class'] . $frame['type'] . $frame['function']) . "(" . $arg_list . ")";
		}

		$out .= 'File: ' . $frame['file'] . "\n";
		$out .= 'Line: ' . $frame['line'] . "\n";
		$out .= 'Call: ' . $func . "\n\n";
	}
	return $out;
}

function error($type, $message, $file, $line = 0)
{
	global $settings;

	if( !(error_reporting() & $type) )
		return;

	switch($type)
	{
	case E_USER_ERROR:
		$type_str = 'Error';
		break;

	case E_WARNING:
	case E_USER_WARNING:
		$type_str = 'Warning';
		break;

	case E_NOTICE:
	case E_USER_NOTICE:
		$type_str = 'Notice';
		break;

	case E_STRICT:
		$type_str = 'Strict Standards';
		break;

	case SANDBOX_QUERY_ERROR:
		$type_str = 'Query Error';
		break;

	default:
		$type = -1;
		$type_str = 'Unknown Error';
	}

	$details = null;

	$backtrace = get_backtrace();

	if ($type != SANDBOX_QUERY_ERROR) {
		if (strpos($message, 'mysql_fetch_array(): supplied argument') === false) {
			$lines = null;
			$details2 = null;

			if (file_exists($file)) {
				$lines = file($file);
			}

			if ($lines) {
				$details2 = "Code:\n" . error_getlines($lines, $line);
			}
		} else {
			$details2 = "MySQL Said:\n" . mysql_error() . "\n";
		}

		$details .= "$type_str [$type]:\n
		The error was reported on line $line of $file\n\n
		$details2";
	} else {
		$details .= "$type_str [$line]:\n
		This type of error is reported by MySQL.\n\n
		Query:\n$file\n";
	}

	// IIS does not use $_SERVER['QUERY_STRING'] in the same way as Apache and might not set it
	if (isset($_SERVER['QUERY_STRING'])) {
		$querystring = str_replace( '&', '&amp;', $_SERVER['QUERY_STRING'] );
	} else {
		$querystring = '';
	}

	// DO NOT allow this information into the error reports!!!
	$details = str_replace( $settings['db_name'], '****', $details );
	$details = str_replace( $settings['db_pass'], '****', $details );
	$details = str_replace( $settings['db_user'], '****', $details );
	$details = str_replace( $settings['db_host'], '****', $details );
	$backtrace = str_replace( $settings['db_name'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_pass'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_user'], '****', $backtrace );
	$backtrace = str_replace( $settings['db_host'], '****', $backtrace );

	// Don't send it if this isn't available. Spamming mail servers is a bad bad thing.
	// This will also email the user agent string, in case errors are being generated by evil bots.
	if( isset($settings['error_email']) ) {
		$headers = "From: Your Sandbox Site <{$settings['error_email']}>\r\n" . "X-Mailer: PHP/" . phpversion();

		$agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A';
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

		$error_report = "Sandbox has exited with an error!\n";
		$error_report .= "The error details are as follows:\n\nURL: http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "?" . $querystring . "\n";
		$error_report .= "Querying user agent: " . $agent . "\n";
		$error_report .= "Querying IP: " . $ip . "\n\n";
		$error_report .= $message . "\n\n" . $details . "\n\n" . $backtrace;
		$error_report = str_replace( "&nbsp;", " ", html_entity_decode($error_report) );

		@mail( $settings['error_email'], "Sandbox Error Report", $error_report, $headers );
	}

	header('HTTP/1.0 500 Internal Server Error');
	exit( "
<!DOCTYPE html>
<html lang=\"en-US\">
 <head>
  <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
  <meta name=\"robots\" content=\"noodp\" />
  <meta name=\"generator\" content=\"Sandbox\" />
  <title>Fatal Error</title>
  <link rel=\"stylesheet\" type=\"text/css\" href=\"./skins/Default/styles.css\" />
 </head>
 <body>
 <div id=\"container\">
  <div id=\"header\">
   <div id=\"company\">
    <div class=\"logo\"></div>
    <div class=\"title\">
     <h1>Sandbox: Fatal Error</h1>
     <p style=\"font-size:11px\">Klingon: ... There has been an incident on Praxis ...<br />Captain Sulu: An \"incident\"?<br />Commander Rand: Do we report this, sir?<br />Captain Sulu: Are you kidding?</p>
    </div>
   </div>
   <ul id=\"navigation\">
    <li><a href=\"/\">Home</a></li>
   </ul>
  </div>

  <div id=\"fullscreen\">
   <div class=\"article\">
    <div class=\"title\" style=\"color:yellow\">Fatal Error</div>
    The Sandbox software has experienced a fatal error and is unable to process your request at this time. Unfortunately any data you may have sent has been lost, and we apologize for the inconvenience.<br /><br />
    A detailed report on exactly what went wrong has been sent to the site owner and will be investigated and resolved as quickly as possible.
   </div>
  </div>

  <div id=\"bottom\">&nbsp;</div>
 </div>
 <div id=\"footer\">Powered by Sandbox &copy; 2006-2015 Sam O'Connor [<a href=\"http://www.kiasyn.com\">Kiasyn</a>] and Roger Libiez [<a href=\"https://www.afkmods.com/\">Samson</a>]</div>
</body>
</html>" );
}

function error_getlines($lines, $line)
{
	$code    = null;
	$padding = ' ';
	$previ   = $line-3;
	$total_lines = count($lines);

	for ($i = $line - 3; $i <= $line + 3; $i++)
	{
		if ((strlen($previ) < strlen($i)) && ($padding == ' ')) {
			$padding = null;
		}

		if (($i < 1) || ($i > $total_lines)) {
			continue;
		}

		$codeline = rtrim(htmlentities($lines[$i-1]));
		$codeline = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $codeline);
		$codeline = str_replace(' ', '&nbsp;', $codeline);

		$code .= $i . $padding . $codeline . "\n";

		$previ = $i;
	}
	return $code;
}
?>