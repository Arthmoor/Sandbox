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

class database
{
	var $name = null;
	var $user = null;
	var $host = null;
	var $pass = null;
	var $pre = null;
	var $queries = 0;
	var $queries_exec = 0;
	var $query_time = 0;

	function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		$this->name = $db_name;
		$this->user = $db_user;
		$this->pass = $db_pass;
		$this->host = $db_host;
		$this->pre  = $db_pre;
	}

	function dbquery( $query )
	{
		return null;
	}

	function row( $query )
	{
		return null;
	}

	function assoc( $result )
	{
		return array();
	}

	function quick_query( $query )
	{
		return $this->assoc( $this->dbquery( $query ) );
	}

	function num_rows( $result )
	{
		return 0;
	}

	function insert_id()
	{
		return 0;
	}

	function escape( $str )
	{
		return addslashes( $str );
	}

	function error()
	{
		return 'Yep, busted!';
	}

	protected function format_query($query)
	{
		// Format the query string
		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = array_shift($args);
		$query = str_replace('%p', $this->pre, $query);
		
		for( $i = 0; $i < count($args); $i++) {
			$args[$i] = $this->escape($args[$i]);
		}
		array_unshift($args, $query);

		return call_user_func_array('sprintf', $args);
	}
}
?>