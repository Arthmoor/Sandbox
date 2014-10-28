<?php
/* Sandbox v0.5-1.0b http://sandbox.kiasyn.com
 * Copyright (c) 2006-2007 Sam O'Connor (Kiasyn)
 *
 * Additions to Sandbox after 1.0:
 * Copyright (c) 2007-2015
 * Roger Libiez [Samson] http://www.iguanadons.net
 *
 * Sandbox installer module. Based on QSF Portal installer module.
 * QSF Portal Copyright (c)2006-2007 The QSF Portal Team
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

if ( !defined('SANDBOX_INSTALLER') )
	die;

$queries[] = "DROP TABLE IF EXISTS %pactive";
$queries[] = "CREATE TABLE %pactive (
  active_action varchar(50) NOT NULL,
  active_time int(10) unsigned NOT NULL,
  active_ip varchar(40) NOT NULL,
  active_user_agent varchar(100) NOT NULL,
  PRIMARY KEY (active_ip)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pblogcats";
$queries[] = "CREATE TABLE %pblogcats (
  cat_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  cat_name varchar(50) NOT NULL DEFAULT '',
  cat_description text NOT NULL,
  PRIMARY KEY (cat_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pblogcomments";
$queries[] = "CREATE TABLE %pblogcomments (
  comment_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  comment_post int(12) unsigned NOT NULL,
  comment_user int(10) unsigned NOT NULL DEFAULT '1',
  comment_date int(10) unsigned NOT NULL DEFAULT '0',
  comment_editdate int(10) unsigned NOT NULL DEFAULT '0',
  comment_type tinyint(2) unsigned NOT NULL DEFAULT '0',
  comment_ip varchar(40) NOT NULL DEFAULT '',
  comment_author varchar(30) NOT NULL,
  comment_editedby varchar(30) DEFAULT '',
  comment_message mediumtext NOT NULL,
  comment_referrer tinytext,
  comment_agent tinytext,
  PRIMARY KEY (comment_id),
  KEY comment_post (comment_post),
  KEY comment_user (comment_user),
  KEY comment_date (comment_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pblogposts";
$queries[] = "CREATE TABLE %pblogposts (
  post_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  post_user int(10) unsigned NOT NULL DEFAULT '0',
  post_date int(10) unsigned NOT NULL DEFAULT '0',
  post_flags int(10) unsigned NOT NULL DEFAULT '0',
  post_comment_count int(10) unsigned NOT NULL DEFAULT '0',
  post_image varchar(50) NOT NULL DEFAULT '',
  post_subject varchar(50) NOT NULL,
  post_summary varchar(255) DEFAULT NULL,
  post_text mediumtext NOT NULL,
  PRIMARY KEY (post_id),
  KEY post_date (post_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pblogroll";
$queries[] = "CREATE TABLE %pblogroll (
  link_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  link_name varchar(30) NOT NULL,
  link_url varchar(100) NOT NULL,
  link_title varchar(100) DEFAULT NULL,
  PRIMARY KEY (link_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pemoticons";
$queries[] = "CREATE TABLE %pemoticons (
  emote_id int(10) unsigned NOT NULL auto_increment,
  emote_string varchar(15) NOT NULL default '',
  emote_image varchar(255) NOT NULL default '',
  emote_clickable tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (emote_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pfilefolders";
$queries[] = "CREATE TABLE %pfilefolders (
  folder_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  folder_parent int(10) unsigned NOT NULL DEFAULT '0',
  folder_user int(10) unsigned NOT NULL DEFAULT '0',
  folder_hidden tinyint(1) NOT NULL DEFAULT '0',
  folder_name varchar(50) NOT NULL DEFAULT '',
  folder_summary varchar(255) DEFAULT '',
  folder_tree varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (folder_id),
  KEY folder_parent (folder_parent)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pfilelist";
$queries[] = "CREATE TABLE %pfilelist (
  file_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  file_folder int(10) unsigned NOT NULL DEFAULT '0',
  file_user int(10) unsigned NOT NULL DEFAULT '0',
  file_date int(10) unsigned NOT NULL DEFAULT '0',
  file_downcount int(10) unsigned NOT NULL DEFAULT '0',
  file_downloaded int(10) unsigned NOT NULL DEFAULT '0',
  file_comment_count int(10) unsigned NOT NULL DEFAULT '0',
  file_flags int(10) unsigned NOT NULL DEFAULT '0',
  file_size int(12) unsigned NOT NULL,
  file_name varchar(50) NOT NULL,
  file_md5name varchar(33) NOT NULL DEFAULT '',
  file_type varchar(4) NOT NULL DEFAULT '',
  file_img_ext varchar(5) DEFAULT '',
  file_version varchar(10) DEFAULT '',
  file_filename varchar(100) NOT NULL,
  file_summary text,
  file_description text NOT NULL,
  PRIMARY KEY (file_id),
  KEY file_folder (file_folder)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %ppages";
$queries[] = "CREATE TABLE %ppages (
  page_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  page_user int(10) unsigned NOT NULL DEFAULT '0',
  page_flags int(10) unsigned NOT NULL DEFAULT '0',
  page_createdate int(10) unsigned NOT NULL,
  page_editdate int(10) unsigned NOT NULL,
  page_title varchar(100) NOT NULL,
  page_meta varchar(255) DEFAULT NULL,
  page_content mediumtext NOT NULL,
  PRIMARY KEY (page_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pphotofolders";
$queries[] = "CREATE TABLE %pphotofolders (
  folder_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  folder_parent int(10) unsigned NOT NULL DEFAULT '0',
  folder_user int(10) unsigned NOT NULL DEFAULT '0',
  folder_hidden tinyint(1) NOT NULL DEFAULT '0',
  folder_name varchar(50) NOT NULL DEFAULT '',
  folder_summary varchar(255) DEFAULT '',
  folder_tree varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (folder_id),
  KEY folder_parent (folder_parent)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pphotogallery";
$queries[] = "CREATE TABLE %pphotogallery (
  photo_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  photo_folder int(10) unsigned NOT NULL DEFAULT '0',
  photo_user int(10) unsigned NOT NULL DEFAULT '0',
  photo_date int(10) unsigned NOT NULL DEFAULT '0',
  photo_flags int(10) unsigned NOT NULL DEFAULT '0',
  photo_type varchar(4) NOT NULL DEFAULT '',
  photo_size int(10) unsigned NOT NULL,
  photo_width int(8) unsigned NOT NULL,
  photo_height int(8) unsigned NOT NULL,
  photo_comment_count int(10) unsigned NOT NULL DEFAULT '0',
  photo_md5name varchar(33) NOT NULL DEFAULT '',
  photo_caption varchar(50) NOT NULL DEFAULT '',
  photo_summary varchar(255) DEFAULT '',
  photo_details text,
  PRIMARY KEY (photo_id),
  KEY photo_folder (photo_folder),
  KEY photo_date (photo_date)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %ppostcats";
$queries[] = "CREATE TABLE %ppostcats (
  pc_post int(10) unsigned NOT NULL DEFAULT '0',
  pc_cat int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (pc_post,pc_cat)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %prandom_quotes";
$queries[] = "CREATE TABLE %prandom_quotes (
  quote_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  quote_text text,
  PRIMARY KEY (quote_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %psettings";
$queries[] = "CREATE TABLE %psettings (
  settings_id tinyint(2) NOT NULL AUTO_INCREMENT,
  settings_version smallint(2) NOT NULL default 2,
  settings_value text NOT NULL,
  PRIMARY KEY (settings_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pspam";
$queries[] = "CREATE TABLE %pspam (
  spam_id int(12) unsigned NOT NULL AUTO_INCREMENT,
  spam_post int(12) unsigned NOT NULL,
  spam_user int(10) unsigned NOT NULL DEFAULT '1',
  spam_date int(10) unsigned NOT NULL,
  spam_type int(10) unsigned NOT NULL DEFAULT '0',
  spam_author varchar(30) NOT NULL,
  spam_ip varchar(40) NOT NULL,
  spam_url varchar(100) DEFAULT NULL,
  spam_message mediumtext NOT NULL,
  spam_server text NOT NULL,
  PRIMARY KEY (spam_id),
  KEY spam_post (spam_post)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";

$queries[] = "DROP TABLE IF EXISTS %pusers";
$queries[] = "CREATE TABLE %pusers (
  user_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  user_joined int(10) unsigned DEFAULT '0',
  user_level smallint(2) unsigned NOT NULL DEFAULT '1',
  user_perms smallint(4) unsigned NOT NULL DEFAULT '0',
  user_ip varchar(40) NOT NULL DEFAULT '127.0.0.1',
  user_name varchar(30) NOT NULL DEFAULT '',
  user_icon varchar(30) DEFAULT '',
  user_password varchar(64) NOT NULL DEFAULT '',
  user_email varchar(100) NOT NULL DEFAULT '',
  user_url varchar(100) DEFAULT '',
  user_stylesheet varchar(100) DEFAULT '',
  user_signature text,
  PRIMARY KEY (user_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
?>