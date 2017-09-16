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

class sys extends module
{
	function execute()
	{
		if( $this->user['user_level'] < USER_ADMIN )
			return $this->error( 'Access Denied: You do not have permission to perform that action.' );

		if( isset($this->get['s']) ) {
			switch( $this->get['s'] )
			{
				case 'phpinfo':
					$this->nohtml = true;
					return phpinfo();
				case 'sql':		return $this->perform_sql();
				case 'stats':		return $this->display_stats();
				case 'optimize':	return $this->opt_tables();
				case 'repair':		return $this->repair_tables();
				case 'recount':		return $this->recount_all();
				case 'backup':		return $this->db_backup();
				case 'restore':		return $this->db_restore();
			}
		}
		return $this->display_stats();
	}

	// Counts all comment entries and resets the counters on each blog, image, or download entry.
	// Should not be needed unless you are manually removing entries through another database interface.
	function recount_all()
	{
		$posts = $this->db->dbquery( 'SELECT post_id FROM %pblogposts' );
		while( $row = $this->db->assoc($posts) )
		{
			$comments = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments
				WHERE comment_post=%d AND comment_type=%d', $row['post_id'], COMMENT_BLOG );
			if( $comments['count'] && $comments['count'] > 0 )
				$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=%d WHERE post_id=%d', $comments['count'], $row['post_id'] );
			else
				$this->db->dbquery( 'UPDATE %pblogposts SET post_comment_count=0 WHERE post_id=%d', $row['post_id'] );
		}

		$images = $this->db->dbquery( 'SELECT photo_id FROM %pphotogallery' );
		while( $row = $this->db->assoc($images) )
		{
			$comments = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments
				WHERE comment_post=%d AND comment_type=%d', $row['photo_id'], COMMENT_GALLERY );
			if( $comments['count'] && $comments['count'] > 0 )
				$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=%d WHERE photo_id=%d', $comments['count'], $row['photo_id'] );
			else
				$this->db->dbquery( 'UPDATE %pphotogallery SET photo_comment_count=0 WHERE photo_id=%d', $row['photo_id'] );
		}

		$downloads = $this->db->dbquery( 'SELECT file_id FROM %pfilelist' );
		while( $row = $this->db->assoc($downloads) )
		{
			$comments = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments
				WHERE comment_post=%d AND comment_type=%d', $row['file_id'], COMMENT_FILE );
			if( $comments['count'] && $comments['count'] > 0 )
				$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=%d WHERE file_id=%d', $comments['count'], $row['file_id'] );
			else
				$this->db->dbquery( 'UPDATE %pfilelist SET file_comment_count=0 WHERE file_id=%d', $row['file_id'] );
		}

		$users = $this->db->quick_query( 'SELECT COUNT(user_id) count FROM %pusers' );
		$this->settings['user_count'] = $users['count'];
		$this->save_settings();

		return $this->message( 'Recount Comments', 'All comment counts have been corrected.', 'Continue', 'admin.php' );
	}

	/**
	 * Grabs the current list of table names in the database
	 *
	 * @author Roger Libiez [Samson] http://www.iguanadons.net
	 * @since 1.2
	 * @return array
	 **/
	function get_db_tables()
	{
		$tarray = array();

		// This looks a bit strange, but it will pull all of the proper prefixed tables.
		$tb = $this->db->dbquery( "SHOW TABLES LIKE '%p%%'" );
		while( $tb1 = $this->db->assoc($tb) )
		{
			foreach( $tb1 as $col => $data )
				$tarray[] = $data;
		}

		return $tarray;
	}

	function repair_tables()
	{
		$tables = implode( ', ', $this->get_db_tables() );

		$result = $this->db->repair( $tables );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		$xtpl->assign( 'header_text', 'Repair Database' );

		while ($row = $this->db->assoc($result))
		{
			foreach ($row as $col => $data)
			{
				$xtpl->assign( 'table_row_entry', htmlspecialchars($data) );
				$xtpl->parse( 'Database.Row.Entry' );
			}
			$xtpl->parse( 'Database.Row' );
		}

		$xtpl->parse( 'Database' );
		return $xtpl->text( 'Database' );
	}

	function opt_tables()
	{
		$this->title('Optimize Database');

		$tables = implode( ', ', $this->get_db_tables() );

		$result = $this->db->optimize( $tables );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		$xtpl->assign( 'header_text', 'Optimize Database' );

		while ($row = $this->db->assoc($result))
		{
			foreach ($row as $col => $data)
			{
				$xtpl->assign( 'table_row_entry', htmlspecialchars($data) );
				$xtpl->parse( 'Database.Row.Entry' );
			}
			$xtpl->parse( 'Database.Row' );
		}

		$xtpl->parse( 'Database' );
		return $xtpl->text( 'Database' );
	}

	function display_stats()
	{
		$post = $this->db->quick_query( 'SELECT COUNT(post_id) count FROM %pblogposts' );
		$comment = $this->db->quick_query( 'SELECT COUNT(comment_id) count FROM %pblogcomments' );
		$file = $this->db->quick_query( 'SELECT COUNT(file_id) count FROM %pfilelist' );
		$pics = $this->db->quick_query( 'SELECT COUNT(photo_id) count FROM %pphotogallery' );
		$spam = isset($this->settings['spam_count']) ? $this->settings['spam_count'] : 0;
		$espam = isset($this->settings['email_spam_count']) ? $this->settings['email_spam_count'] : 0;
		$uspam = isset($this->settings['register_spam_count']) ? $this->settings['register_spam_count'] : 0;
		$ham = isset($this->settings['ham_count']) ? $this->settings['ham_count'] : 0;
		$false_neg = isset($this->settings['spam_uncaught']) ? $this->settings['spam_uncaught'] : 0;

		$total_comments = $comment['count'] + $spam;
		$pct_spam = null;
		if( $total_comments > 0 && $spam > 0 ) {
			$percent = floor(( $spam / $total_comments ) * 100);

			$pct_spam = ", {$percent}";
		}

		$active = $this->db->dbquery( 'SELECT * FROM %pactive' );

		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/stats.xtpl' );

		$xtpl->assign( 'user_count', $this->settings['user_count'] );
		$xtpl->assign( 'post_count', $post['count'] );
		$xtpl->assign( 'total_comments', $total_comments );
		$xtpl->assign( 'pct_spam', $pct_spam );
		$xtpl->assign( 'spam', $spam );
		$xtpl->assign( 'ham', $ham );
		$xtpl->assign( 'false_neg', $false_neg );
		$xtpl->assign( 'espam', $espam );
		$xtpl->assign( 'uspam', $uspam );
		$xtpl->assign( 'file_count', $file['count'] );
		$xtpl->assign( 'pics_count', $pics['count'] );

		while( $user = $this->db->assoc( $active ) )
		{
			$xtpl->assign( 'ip', $user['active_ip'] );
			$xtpl->assign( 'agent', htmlspecialchars($user['active_user_agent']) );
			$xtpl->assign( 'date', date( $this->settings['blog_dateformat'], $user['active_time'] ) );
			$xtpl->assign( 'action', $user['active_action'] );

			$xtpl->parse( 'Stats.UserAgent' );
		}

		$xtpl->parse( 'Stats' );
		return $xtpl->text( 'Stats' );
	}

	function perform_sql()
	{
		$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/database.xtpl' );

		if ( !isset($this->post['submit']) )
		{
			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', null );

			$xtpl->parse( 'QueryForm' );
			return $xtpl->text( 'QueryForm' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		if( empty($this->post['sqlquery']) )
			return $this->message( 'SQL Query', 'You cannot supply an empty query.', 'Query Form', 'admin.php?a=sys&s=sql' );

		$result = $this->db->dbquery( $this->post['sqlquery'] );

		if( !$result ) {
			$xtpl->assign( 'error', $this->db->error() );
			$xtpl->parse( 'QueryForm.Error' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', $this->post['sqlquery'] );

			$xtpl->parse( 'QueryForm' );
			return $xtpl->text( 'QueryForm' );
		}

		$show_fields = true;
		$col_span = 0;
		while( $row = $this->db->assoc($result) )
		{
			if( $show_fields ) {
				foreach( $row as $field => $value ) {
					$xtpl->assign( 'result_field', htmlspecialchars($field) );
					$xtpl->parse( 'QueryResult.Field' );
					$col_span++;
				}
				$show_fields = false;
			}

			foreach( $row as $value ) {
				$xtpl->assign( 'result_row', htmlspecialchars($value) );
				$xtpl->parse( 'QueryResult.Row.Entry' );
			}
			$xtpl->parse( 'QueryResult.Row' );
		}

		$xtpl->assign( 'col_span', $col_span );
		$xtpl->assign( 'num_rows', $this->db->num_rows($result) );

		$xtpl->parse( 'QueryResult' );
		return $xtpl->text( 'QueryResult' );
	}

	/**
	 * Generate a backup
	 *
	 * @author Aaron Smith-Hayes <davionkalhen@gmail.com>
	 * @since 2.1
	 * @return string HTML
	 **/
	function db_backup()
	{
		if( !isset($this->post['submit'] ) ) {
			$xtpl = new XTemplate( './skins/' . $this->skin . '/AdminCP/db_backup.xtpl' );

			$xtpl->assign( 'token', $this->generate_token() );
			$xtpl->assign( 'query', null );

			$xtpl->parse( 'DBBackup.BackupForm' );
			$xtpl->parse( 'DBBackup' );
			return $xtpl->text( 'DBBackup' );
		}

		if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		}

		srand();
		$mcookie = sha1( crc32( rand() ) );

		$filename = 'sandbox_backup_' . date('d-m-y-H-i-s') . '-' . $mcookie . '.sql';
		$options = "";

		foreach( $this->post as $key => $value )
			$$key = $value;
		if(isset($insert))
			$options .= " -c";
		if(isset($droptable))
			$options .= " --add-drop-table";

		$tables = implode( ' ', $this->get_db_tables() );

		$mbdump = "mysqldump {$options} -p --host={$this->db->host} --user={$this->db->user}";
		$mbdump .= " --result-file='./files/{$filename}' {$this->db->name}";

		$fds = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' )
				);

		$pipes = NULL;

		$proc = proc_open( $mbdump, $fds, $pipes );
		if( $proc === false || !is_resource( $proc ) )
			return $this->error( 'Database Backup Failed. System interface is not available.' );

		fwrite( $pipes[0], $this->db->pass . PHP_EOL );
		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$retval = proc_close( $proc );

		if ( 0 != $retval )
		{
			return $this->error( 'Database Backup Failed!!!<br /><br />' . $stderr );
		}

		chmod( "./files/" . $filename, 0440 );
		return $this->message( 'Database Backup', 'Backup successfully created.<br /><br />', $filename, './files/' . $filename, 0 );
	}

	/**
	 * Restore a backup
	 *
	 * @author Aaron Smith-Hayes <davionkalhen@gmail.com>
	 * @since 2.1
	 * @return string HTML
	 **/
	function db_restore()
	{
		if (!isset($this->get['restore']))
		{
			if ( ($dir = opendir("./files") ) === false )
				return $this->error( 'Unable to read database backups folder.' );

			$token = $this->generate_token();
			$backups = array();
			while( ($file = readdir($dir) ) )
			{
				if(strtolower(substr($file, -4) ) != ".sql")
					continue;
				$backups[] = $file;
			}
			closedir($dir);

			if( count($backups) <= 0 )
				return $this->error( 'No backup files were found to restore.' );

			$output = '<b>Warning:</b> This will overwrite all existing data used by Sandbox!<br /><br />';
			$output .= 'The following backups were found in the files directory:<br /><br />';
			$count = 0;

			foreach( $backups as $bkup )
			{
				$output .= "<a href=\"admin.php?a=sys&amp;s=restore&amp;restore=" . $bkup . "\">" . $bkup . "</a><br />";
			}
			return $this->message( 'Restore Database', $output );
		}

		if(!file_exists("./files/".$this->get['restore']) )
			return $this->error( 'Sorry, that backup does not exist.' );

		/* if( !$this->is_valid_token() ) {
			return $this->error( 'Invalid or expired security token. Please go back, reload the form, and try again.' );
		} */

		$filename = $this->get['restore'];
		$mbimport = "mysql --database={$this->db->name} --host={$this->db->host} --user={$this->db->user} -p";

		$fds = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' )
				);

		$pipes = NULL;

		$proc = proc_open( $mbimport, $fds, $pipes );

		if( $proc === false || !is_resource( $proc ) )
			return $this->error( 'Database restoration failed. System interface is not available.' );

		fwrite( $pipes[0], $this->db->pass . PHP_EOL );
		sleep(3);
		fwrite( $pipes[0], "\\. ./files/{$filename}" . PHP_EOL );
		sleep(3);
		fwrite( $pipes[0], "\\q" . PHP_EOL );
		fclose( $pipes[0] );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$retval = proc_close( $proc );

		if ( 0 != $retval )
		{
			return $this->error( 'Database restoration failed to import.<br /><br />' . $stderr );
		}

		return $this->message( 'Restore Database', 'Database restoration successful.<br /><br />' . $stdout . $stderr );
	}
}
?>