<?php
/* Sandbox v0.5-1.0b http://sandbox.kiasyn.com
 * Copyright (c) 2006-2007 Sam O'Connor (Kiasyn)
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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

class social extends module
{
	function execute()
	{
		if ( isset($this->get['s'] ) ) {
			switch( $this->get['s'] )
			{
				case 'twitter':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = base64_decode( $encoded[0] );
						$url = urlencode( base64_decode( $encoded[1] ) );

						if( isset( $this->settings['twitter_user'] ) && !empty( $this->settings['twitter_user'] ) )
							$link = 'https://twitter.com/share?text='. $subject . '&url=' . $url . '&via=' . $this->settings['twitter_user'];
						else
							$link = 'https://twitter.com/share?text='. $subject . '&url=' . $url;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Twitter Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;

				case 'facebook':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = base64_decode( $encoded[0] );
						$url = urlencode( base64_decode( $encoded[1] ) );

						$link = 'http://www.facebook.com/sharer.php?u=' . $url . '&t=' . $subject;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Facebook Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;

				case 'delicious':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = urlencode( base64_decode( $encoded[0] ) );
						$url = urlencode( base64_decode( $encoded[1] ) );

						$link = 'http://del.icio.us/post?url=' . $url . '&title=' . $subject;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Delicious Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;

				case 'reddit':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = urlencode( base64_decode( $encoded[0] ) );
						$url = urlencode( base64_decode( $encoded[1] ) );

						$link = 'https://www.reddit.com/submit?url=' . $url . '&title=' . $subject;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Reddit Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;

				case 'stumbleupon':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = urlencode( base64_decode( $encoded[0] ) );
						$url = urlencode( base64_decode( $encoded[1] ) );

						$link = 'http://www.stumbleupon.com/submit?url=' . $url . '&title=' . $subject;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Stumbleupon Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;

				case 'gplus':
					if( isset( $this->get['data'] ) ) {
						$encoded = explode( ';', $this->get['data'] );

						$subject = urlencode( base64_decode( $encoded[0] ) );
						$url = urlencode( base64_decode( $encoded[1] ) );

						$link = 'https://plus.google.com/share?url=' . $url;

						$link = trim($link);
						$link = str_replace( "\n", "", $link );
						header( 'Location: ' . $link );
						exit();
					} else {
						return $this->error( 'Google+ Submission Error: The URL you have attempted to submit is invalid.', 500 );
					}
				break;
			}
		}
		return $this->error( 'Social Media Submission Error: The URL you have attempted to submit is invalid.', 500 );
	}
}
?>