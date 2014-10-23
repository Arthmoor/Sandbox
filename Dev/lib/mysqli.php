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

if ( !defined('SANDBOX') ) {
	header('HTTP/1.0 403 Forbidden');
	die;
}

require_once $settings['include_path'] . '/lib/database.php';

class db_mysqli extends database
{
	function __construct( $db_name, $db_user, $db_pass, $db_host, $db_pre )
	{
		parent::__construct( $db_name, $db_user, $db_pass, $db_host, $db_pre );

		$this->db = new mysqli( $db_host, $db_user, $db_pass, $db_name );

		if (!$this->db->select_db( $db_name ))
			$this->db = false;
	}

	function close()
	{
		if( $this->db )
			$this->db->close();
	}

	function dbquery( $query )
	{
		$time_now   = explode(' ', microtime());
		$time_start = $time_now[1] + $time_now[0];

		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		$query = $this->format_query($args);

		$result = $this->db->query($query) or error(SANDBOX_QUERY_ERROR, $this->db->error, $query, $this->db->errno);

		$this->queries++;

		$time_now  = explode(' ', microtime());
		$time_exec = round($time_now[1] + $time_now[0] - $time_start, 5);
		$this->query_time = $time_exec;
		$this->queries_exec += $time_exec;

		return $result;
	}

	function row( $result )
	{
		return $result->fetch_row();
	}

	function assoc( $result )
	{
		return $result->fetch_assoc();
	}

	function quick_query( $query )
	{
		$args = array();
		if (is_array($query)) {
			$args = $query; // only use arg 1
		} else {
			$args  = func_get_args();
		}

		return $this->assoc( $this->dbquery( $args ) );
	}

	function num_rows( $result )
	{
		return $result->num_rows;
	}

	function insert_id()
	{
		return $this->db->insert_id;
	}

	function escape( $str )
	{
		return $this->db->real_escape_string( $str );
	}

	function optimize($tables)
	{
		return $this->dbquery( 'OPTIMIZE TABLE ' . $tables );
	}

	function repair($tables)
	{
		return $this->dbquery( 'REPAIR TABLE ' . $tables );
	}

	function error()
	{
		return $this->db->error;
	}
}
?>