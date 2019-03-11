<?php
/**
 * Sandbox v0.5-1.0b
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
 *
 * Implements the Akismet API found at https://akismet.com/development/api/#detailed-docs
 */

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

// Akismet anti-spam library for Sandbox
class Akismet
{
	private $site_url;
	private $api_key;
	private $akismet_domain = 'rest.akismet.com';
	private $akismet_server_port = 443;
	private $akismet_version = '1.1';
	private $akismet_api_server;
	private $akismet_request_path;
	private $akismet_useragent;
	private $comment_data;

	// This list of server variables often contain sensitive information. Even though this uses SSL there's no good reason to transmit it.
	private $server_vars_ignored = array( 'HTTP_COOKIE', 'PATH', 'SERVER_SIGNATURE', 'SERVER_SOFTWARE', 'DOCUMENT_ROOT', 'CONTEXT_PREFIX', 'SERVER_ADMIN', 'SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF', 'PATH_TRANSLATED', 'PATH_INFO', 'ORIG_PATH_INFO' );

	// Constructor takes the Sandbox global instance which has all of the needed data to initalize with.
	public function __construct( $sandbox )
	{
		$this->site_url = $sandbox->settings['site_address'];
		$this->api_key = $sandbox->settings['wordpress_api_key'];
		$this->akismet_useragent = 'Sandbox/' . $sandbox->version . ' | Akismet/' . $this->akismet_version;

		$this->akismet_api_server = $this->api_key . '.' . $this->akismet_domain;
		$this->akismet_request_path = '/' . $this->akismet_version;

		$this->comment_data['blog'] = $this->site_url;
		$this->comment_data['user_ip'] = $sandbox->ip;
		$this->comment_data['user_agent'] = $sandbox->agent;
		$this->comment_data['referrer'] = $sandbox->referrer;
		$this->comment_data['blog_lang'] = 'en';
		$this->comment_data['blog_charset'] = 'UTF-8';
	}

	private function send_request( $request, $path, $server )
	{
		$http_request  = "POST $path HTTP/1.0\r\n";
		$http_request .= "Host: $server\r\n";
		$http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
		$http_request .= "Content-Length: " . strlen( $request ) . "\r\n";
		$http_request .= "User-Agent: {$this->akismet_useragent}\r\n";
		$http_request .= "\r\n";
		$http_request .= $request;

		$response = '';
		if( false != ( $fs = @fsockopen( 'ssl://' . $server, $this->akismet_server_port, $errno, $errstr, 10 ) ) ) {
 			fwrite( $fs, $http_request );

			while( !feof( $fs ) )
				$response .= fgets( $fs, 1160 );
			fclose( $fs );

			$response = explode( "\r\n\r\n", $response, 2 );
		}
		return $response;
	}

	private function create_query_string( $query )
	{
		foreach( $_SERVER as $key => $value ) {
			if( !in_array( $key, $this->server_vars_ignored ) ) {
				$this->comment_data[$key] = $value;
			}
		}

		$query_string = '';

		foreach( $this->comment_data as $key => $data ) {
			if( !is_array( $data ) ) {
				$query_string .= $key . '=' . urlencode( stripslashes( $data ) ) . '&';
			}
		}
		return $query_string;
	}

	// Used to check if your Akismet API Key is valid.
	public function is_key_valid()
	{
		$request = 'key=' . $this->api_key . '&blog=' . urlencode( stripslashes( $this->site_url ) );

		$response = $this->send_request( $request, $this->akismet_request_path . '/verify-key', $this->akismet_domain );

		return $response[1] == 'valid';
	}

	// Check if a submitted issue or comment might be spam.
	public function is_this_spam()
	{
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/comment-check', $this->akismet_api_server );

		if( $response[1] == 'invalid' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}

		return( $response[1] == 'true' );
	}

	// Used to report something that did not get marked as spam, but really is. [False Negative]
	public function submit_spam()
	{
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/submit-spam', $this->akismet_api_server );

		if( $response[1] != 'Thanks for making the web a better place.' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}
	}

	// Used to report something that isn't spam, but got marked as such. [False Positive]
	public function submit_ham()
	{
		$query = $this->create_query_string();

		$response = $this->send_request( $query, $this->akismet_request_path . '/submit-ham', $this->akismet_api_server );

		if( $response[1] != 'Thanks for making the web a better place.' && !$this->is_key_valid() ) {
			throw new exception( 'The Akismet API Key for this site is not valid. If this issue persists, notify the site administrators.' );
		}
	}

	// Override the reported IP Address. Useful for submitting spam/ham after the fact.
	public function set_comment_ip( $ip )
	{
		$this->comment_data['user_ip'] = $ip;
	}

	// Override the useragent. Useful for submitting spam/ham after the fact.
	public function set_comment_useragent( $agent )
	{
		$this->comment_data['user_agent'] = $agent;
	}

	// Override the referrer. Useful for submitting spam/ham after the fact.
	public function set_comment_referrer( $referrer )
	{
		$this->comment_data['referrer'] = $referrer;
	}

	// The full permanent URL of the entry the comment was submitted to.
	public function set_permalink( $permalink )
	{
		$this->comment_data['permalink'] = $permalink;
	}

	// A string that describes the type of content being sent.
	public function set_comment_type( $type )
	{
		$this->comment_data['comment_type'] = $type;
	}

	// Name submitted with the comment.
	public function set_comment_author( $author )
	{
		$this->comment_data['comment_author'] = $author;
	}

	// Email address submitted with the comment.
	public function set_comment_author_email( $email )
	{
		$this->comment_data['comment_author_email'] = $email;
	}

	// URL submitted with comment.
	public function set_comment_author_url( $url )
	{
		$this->comment_data['comment_author_url'] = $url;
	}

	// The content that was submitted.
	public function set_comment_content( $comment )
	{
		$this->comment_data['comment_content'] = $comment;
	}

	// The time the original content was posted. Converts to ISO 8601 format. The 2 time values will be the same for Sandbox.
	public function set_comment_time( $time )
	{
		$date = new DateTime();
		$date->setTimestamp( $time );

		$iso_time = $date->format( 'c' );

		$this->comment_data['comment_date_gmt'] = $iso_time;
		$this->comment_data['comment_post_modified_gmt'] = $iso_time;
	}
}
?>