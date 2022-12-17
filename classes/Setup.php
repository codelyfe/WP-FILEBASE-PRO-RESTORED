<?php

class WPFB_Setup
{
    const MANY_FILES = 50;
    const MANY_CATEGORIES = 200;

    static function AddOptions()
    {
        $default_opts = WPFB_Admin::SettingsSchema();
        $existing_opts = get_option(WPFB_OPT_NAME);
        $new_opts = array();

        foreach ($default_opts as $opt_name => $opt_data) {
            $new_opts[$opt_name] = $opt_data['default'];
        }

        $new_opts['widget'] = array(); // placeholder to keep old widget settings!

        $new_opts['version'] = WPFB_VERSION;
        $new_opts['tag_ver'] = WPFB_TAG_VER;


        if (empty($existing_opts)) //if no opts at all
            add_option(WPFB_OPT_NAME, $new_opts);
        else {
            foreach ($new_opts as $opt_name => $opt_data) {
                // check if this option already exists, and if changed, take the existing value
                if ($opt_name != 'version' && $opt_name != 'tag_ver' && isset($existing_opts[$opt_name]) && $existing_opts[$opt_name] != $opt_data)
                    $new_opts[$opt_name] = $existing_opts[$opt_name];
            }

            // check for old tags
            if (empty($existing_opts['tag_ver']) || intval($existing_opts['tag_ver']) < WPFB_TAG_VER) {
                $new_opts['tag_conv_req'] = true;
            }

            update_option(WPFB_OPT_NAME, $new_opts);
        }

        WPFB_Core::$settings = (object)get_option(WPFB_OPT_NAME);

        add_option(WPFB_OPT_NAME . '_ftags', array(), null, 'no'/*autoload*/);

        add_option(WPFB_OPT_NAME . '_rsyncs', array(), null, 'no'/*autoload*/);

        // for static css caching
        add_option('wpfb_css', WPFB_PLUGIN_URI . 'wp-filebase.css?v=' . WPFB_VERSION);

        add_option('wpfilebase_cron_sync_stats', array(), null, 'no'/*autoload*/);

    }

    static function AddTpls($old_ver = null)
    {
        $def_tpls_file = array(
            'filebrowser' => '%file_small_icon% <a href="%file_url%" title="Download %file_display_name%">%file_display_name%</a> (%file_size%) %button_edit% %button_delete%',
            'filepage' => <<<TPL
<style type="text/css" media="screen">
	.wpfb-icon { background-color: #eee; min-width: 200px; max-width: 300px; text-align: center; padding: 30px; }
	.wpfb-desc {     padding: 22px 0; clear: left; }
	.wpfb-details th { padding-right:10px; }
        
	.wpfb-flatbtn { font-size: 28px; background: #27ae60; display: block; text-decoration: none; color: white; padding: 2px 20px; text-transform: uppercase; font-weight: lighter; text-align: center; font-family: Montserrat, "Helvetica Neue", sans-serif; border-bottom: none !important; }
	.wpfb-flatbtn:hover { background: #2ecc71; color: white; }
</style>

<div style="    text-align: center;">
<div style="display: inline-block; max-width: 205px; margin-bottom: 1em;">
  <div class="wpfb-icon"><img src="%file_icon_url%" /></div>
</div>
<div style="display: inline-block; width: 270px; margin: 0 1em;     vertical-align: top; ">
<!-- IF %dl_countdown% -->
  <p>Your Download will start in %dl_countdown% seconds...</p>
<!-- ELSE -->



<a href="%file_url%" class="wpfb-flatbtn" title="%file_name%" style="
    width: 4em;
    margin: auto;
"><div style="text-decoration: underline;">&#x1F847;</div><div style="
    font-size: 10px;
">%file_extension% - %file_size%</div></a>

<!-- ENDIF -->
  <table class="wpfb-details" style="margin-top: 20px;">
    <!-- <tr><th colspan="2" style="text-align:center;">%file_name%</td></tr> -->
    <tr><th>%'Date'%</th><td>%file_date%</td></tr>
    <tr><th>%'Downloads'%</th><td>%file_hits%</td></tr>
    <!-- IF %file_version% --><tr><th>%'Version'%</th><td>%file_version%</td></tr><!-- ENDIF -->
    <!-- IF %file_languages% --><tr><th>%'Languages'%</th><td>%file_languages%</td></tr><!-- ENDIF -->
    <!-- IF %file_author% --><tr><th>%'Author'%</th><td>%file_author%</td></tr><!-- ENDIF -->
    <!-- IF %file_platforms% --><tr><th>%'Platforms'%</th><td>%file_platforms%</td></tr><!-- ENDIF -->    
    <!-- IF %file_license% --><tr><th>%'License'%</th><td>%file_license%</td></tr><!-- ENDIF -->    
  </table>
</div>
</div>
<div class="wpfb-desc">%file_description%</div>
<div style="clear:both;"></div>
TPL
        ,
            'filepage_excerpt' => '%file_small_icon% %file_display_name%<!-- IF %file_author% --> by %file_author%<!-- ENDIF --><!-- IF %file_version% -->, Version %file_version%<!-- ENDIF -->, %file_name% (%file_size%) <!-- IF %file_description% -->- %file_description%<!-- ENDIF -->',
            'download-button' => '<div style="text-align:center; width:250px; margin: auto; font-size:smaller;"><a href="%file_url%" class="wpfb-dlbtn"><div></div></a>
%file_display_name% (%file_size%, %file_hits% downloads)
</div>',
            'image_320' => '[caption id="file_%file_id%" align="alignnone" width="320" caption="<!-- IF %file_description% -->%file_description%<!-- ELSE -->%file_display_name%<!-- ENDIF -->"]<img class="size-full" title="%file_display_name%" src="%file_url%" alt="%file_display_name%" width="320" />[/caption]' . "\n\n",
            'thumbnail' => '<div class="wpfilebase-fileicon"><a href="%file_url%" title="Download %file_display_name%"><img align="middle" src="%file_icon_url%" /></a></div>' . "\n",
            'simple' => '<p><img src="%file_icon_url%" style="height:20px;vertical-align:middle;" /> <a href="%file_url%" title="Download %file_display_name%">%file_display_name%</a> (%file_size%)</p>',
            '3-col-row' => '<tr><td><a href="%file_url%">%file_display_name%</a></td><td>%file_size%</td><td>%file_hits%</td></tr>',
            'mp3' => '<div class="wpfilebase-attachment">
 <div class="wpfilebase-fileicon"><a href="%file_url%" title="Download %file_display_name%"><img align="middle" src="%file_icon_url%" alt="%file_display_name%" height="80"/></a></div>
 <div class="wpfilebase-rightcol">
  <div class="wpfilebase-filetitle">
   <a href="%file_url%" title="Download %file_display_name%">%file_info/tags/id3v2/title%</a><br />
%file_info/tags/id3v2/artist%<br />
%file_info/tags/id3v2/album%<br />
   <!-- IF %file_post_id% AND %post_id% != %file_post_id% --><a href="%file_post_url%" class="wpfilebase-postlink">%\'View post\'%</a><!-- ENDIF -->
  </div>
 </div>
 <div class="wpfilebase-fileinfo">
  %file_info/playtime_string%<br />
  %file_info/bitrate%<br />
  %file_size%<br />
  %file_hits% %\'Downloads\'%<br />
 </div>
 <div style="clear: both;"></div>
</div>',

            'html5_video' => "<video width='%file_info/video/resolution_x%' height='%file_info/video/resolution_y%' controls>
  <source src='%file_url%' type='%file_type%'>
Your browser does not support the video tag.  <a href='%file_url%'>Open Video directly</a>.
</video>",

            //'data-table' => '<tr><td><a href="%file_url%">%file_display_name%</a></td><td>%file_size%</td><td>%file_hits%</td></tr>',
        );

        $def_tpls_cat = array(
            'filebrowser' => '%cat_small_icon% <a href="%cat_url%" onclick="return false;">%cat_name%</a>',
            '3-col-row' => '<tr><td colspan="3" style="text-align:center;font-size:120%;">%cat_name%</td></tr>',
            //'data-table' => '<!-- EMPTY: categories should not be listed in DataTables -->',
        );

        add_option(WPFB_OPT_NAME . '_tpls_file', $def_tpls_file, null, 'no'/*autoload*/);
        add_option(WPFB_OPT_NAME . '_tpls_cat', $def_tpls_cat, null, 'no'/*autoload*/);
        add_option(WPFB_OPT_NAME . '_ptpls_file', array(), null, 'no'/*autoload*/);
        add_option(WPFB_OPT_NAME . '_ptpls_cat', array(), null, 'no'/*autoload*/);

        $def_tpls_list = array(
            'default' => array(
                'header' => '',
                'footer' => '',
                'file_tpl_tag' => 'default',
                'cat_tpl_tag' => 'default'
            ),
            'table' => array(
                'header' => '%search_form%
<table>
<thead>
	<tr><th scope="col"><a href="%sortlink:file_name%">Name</a></th><th scope="col"><a href="%sortlink:file_size%">Size</a></th><th scope="col"><a href="%sortlink:file_hits%">Hits</a></th></tr>
</thead>
<tfoot>
	<tr><th scope="col"><a href="%sortlink:file_name%">Name</a></th><th scope="col"><a href="%sortlink:file_size%">Size</a></th><th scope="col"><a href="%sortlink:file_hits%">Hits</a></th></tr>
</tfoot>
<tbody>',
                'footer' => '</tbody>
</table>
<div class="tablenav-pages">%page_nav%</div>',
                'file_tpl_tag' => '3-col-row',
                'cat_tpl_tag' => '3-col-row'
            ),
            'mp3-list' => array(
                'header' => '',
                'footer' => '',
                'file_tpl_tag' => 'mp3',
                'cat_tpl_tag' => 'default'
            ),

            /*
            'data-table' => array(
                'header' =>
                    '%print_script:jquery-dataTables%
%print_style:jquery-dataTables%
<table id="wpfb-data-table-%uid%">
<thead>
	<tr><th scope="col">Name</th><th scope="col">Size</th><th scope="col">Hits</th></tr>
</thead>
<tbody>',
                'footer' =>
                    '</tbody>
</table>
<script type="text/javascript" charset="utf-8">
	jQuery(document).ready(function() {
		jQuery(\'#wpfb-data-table-%uid%\').DataTable();
	} );
</script>',
                'file_tpl_tag' => 'data-table',
                'cat_tpl_tag' => 'data-table'

            ) */
        );
        add_option(WPFB_OPT_NAME . '_list_tpls', $def_tpls_list, null, 'no'/*autoload*/);

        // delete old (<0.2.0) tpl options and copy to new
        $old_tpls = get_option(WPFB_OPT_NAME . '_tpls');
        delete_option(WPFB_OPT_NAME . '_tpls');
        delete_option(WPFB_OPT_NAME . '_tpls_parsed');
        if (!empty($old_tpls)) {
            $file_tpls = array_merge(WPFB_Core::GetFileTpls(), $old_tpls);
            WPFB_Core::SetFileTpls($file_tpls);
        }

        // add protected tpls
        $tpls_file = get_option(WPFB_OPT_NAME . '_tpls_file');
        $tpls_cat = get_option(WPFB_OPT_NAME . '_tpls_cat');
        $tpls_list = get_option(WPFB_OPT_NAME . '_list_tpls');

        wpfb_loadclass('AdminGuiTpls');
        $default_templates = WPFB_AdminGuiTpls::$protected_tags;

        // add new data table template
        if (!empty($old_ver)) {
            if (version_compare($old_ver, '0.2.9.22') < 0) {
                //$default_templates[] = 'data-table';
                $default_templates[] = 'download-button';
            }
        }

        foreach ($default_templates as $pt) {
            if (empty($tpls_file[$pt]) && !empty($def_tpls_file[$pt])) $tpls_file[$pt] = $def_tpls_file[$pt];
            if (empty($tpls_cat[$pt]) && !empty($def_tpls_cat[$pt])) $tpls_cat[$pt] = $def_tpls_cat[$pt];
            if (empty($tpls_list[$pt]) && !empty($def_tpls_list[$pt])) $tpls_list[$pt] = $def_tpls_list[$pt];
        }

        update_option(WPFB_OPT_NAME . '_tpls_file', $tpls_file);
        update_option(WPFB_OPT_NAME . '_tpls_cat', $tpls_cat);
        update_option(WPFB_OPT_NAME . '_list_tpls', $tpls_list);

        WPFB_Admin::ParseTpls();
    }

    static function RemoveOptions()
    {
        delete_option(WPFB_OPT_NAME);

        delete_option('wpfb_css');

        delete_metadata('user', 0, 'wpfb_ext_tagtime', '', true);

        // delete old options too
        $options = WPFB_Admin::SettingsSchema();
        foreach ($options as $opt_name => $opt_data)
            delete_option(WPFB_OPT_NAME . '_' . $opt_name);
        WPFB_Core::$settings = new stdClass();
    }

    static function RemoveTpls()
    {
        delete_option(WPFB_OPT_NAME . '_tpls_file');
        delete_option(WPFB_OPT_NAME . '_tpls_cat');
        delete_option(WPFB_OPT_NAME . '_ptpls_file');
        delete_option(WPFB_OPT_NAME . '_ptpls_cat');
        delete_option(WPFB_OPT_NAME . '_list_tpls');
    }

    static function ResetOptions()
    {
        $traffic = isset(WPFB_Core::$settings->traffic_stats) ? WPFB_Core::$settings->traffic_stats : null;    // keep stats
        self::RemoveOptions();
        self::AddOptions();
        if (!is_null($traffic))
            WPFB_Core::UpdateOption('traffic_stats', $traffic);
        WPFB_Admin::ParseTpls();
    }

    static function ResetTpls()
    {
        self::RemoveTpls();
        self::AddTpls();
    }


    static function SetupDBTables($old_ver = null)
    {
        global $wpdb;


        $inno_db = false;
        $engine = $inno_db ? 'InnoDB' : 'MyISAM';

        $queries = array();
        $tbl_cats = $wpdb->prefix . 'wpfb_cats';
        $tbl_files = $wpdb->prefix . 'wpfb_files';
        $tbl_files_id3 = $wpdb->prefix . 'wpfb_files_id3';
        $tbl_rsync_meta = $wpdb->prefix . 'wpfb_rsync_meta';
        $queries[] = "CREATE TABLE IF NOT EXISTS `$tbl_cats` (
  `cat_id` int(8) unsigned NOT NULL auto_increment,
  `cat_name` varchar(255) NOT NULL default '',
  `cat_description` text,
  `cat_folder` varchar(300) NOT NULL,
  `cat_path` varchar(2000) NOT NULL,
  `cat_parent` int(8) unsigned NOT NULL default '0',
  `cat_num_files` int(8) unsigned NOT NULL default '0',
  `cat_num_files_total` int(8) unsigned NOT NULL default '0',
  `cat_user_roles` text NOT NULL default '',
  `cat_owner` bigint(20) unsigned default NULL,
  `cat_upload_permissions` text NOT NULL default '',
  `cat_icon` varchar(255) default NULL,
  `cat_exclude_browser` enum('0','1') NOT NULL default '0',
  `cat_order` int(8) NOT NULL default '0',
  `cat_wp_term_id` bigint(20) NOT NULL default '0',
  `cat_scan_lock` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`cat_id`),
  FULLTEXT KEY `USER_ROLES` (`cat_user_roles`)
  , FULLTEXT KEY `UL_PERMS` (`cat_upload_permissions`)
) ENGINE=$engine  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";


        $queries[] = "CREATE TABLE IF NOT EXISTS `$tbl_files` (
  `file_id` bigint(20) unsigned NOT NULL auto_increment,
  `file_name` varchar(300) NOT NULL default '',
  `file_name_original` varchar(300) NOT NULL default '',
  `file_path` varchar(2000) NOT NULL default '',
  `file_size` bigint(20) unsigned NOT NULL default '0',
  `file_date` datetime NOT NULL default '1000-01-01 00:00:00',
  `file_mtime` bigint(20) unsigned NOT NULL default '0',
  `file_hash` char(32) NOT NULL default '',
  `file_hash_sha256` char(64) NOT NULL default '',
  `file_remote_uri` varchar(2000) NOT NULL default '',
  `file_thumbnail` varchar(255) NOT NULL default '',
  `file_display_name` varchar(255) NOT NULL default '',
  `file_description` text NOT NULL default '',
  `file_tags` varchar(255) NOT NULL default '',
  `file_requirement` varchar(255) NOT NULL default '',
  `file_version` varchar(64) NOT NULL default '',
  `file_author` varchar(255) NOT NULL default '',
  `file_language` varchar(255) NOT NULL default '',
  `file_platform` varchar(255) NOT NULL default '',
  `file_license` varchar(255) NOT NULL default '',
  `file_user_roles` text NOT NULL default '',
  `file_edit_permissions` text NOT NULL default '',
  `file_password` varchar(32) NOT NULL default '',
  `file_offline` enum('0','1') NOT NULL default '0',
  `file_direct_linking` enum('0','1','2') NOT NULL default '0',
  `file_force_download` enum('0','1') NOT NULL default '0',
  `file_category` int(8) unsigned NOT NULL default '0',
  `file_category_name` varchar(127) NOT NULL default '',
  `file_sec_cat1` int(8) unsigned NOT NULL default '0',
  `file_sec_cat2` int(8) unsigned NOT NULL default '0',
  `file_sec_cat3` int(8) unsigned NOT NULL default '0',
  `file_update_of` bigint(20) unsigned NOT NULL default '0',
  `file_post_id` bigint(20) unsigned default NULL,
  `file_attach_order` int(8) NOT NULL default '0',
  `file_wpattach_id` bigint(20) NOT NULL default '0',
  `file_added_by` bigint(20) unsigned NOT NULL default '0',
  `file_hits` bigint(20) unsigned NOT NULL default '0',
  `file_ratings` bigint(20) unsigned NOT NULL default '0',
  `file_rating_sum` bigint(20) unsigned NOT NULL default '0',
  `file_last_dl_ip` varchar(100) NOT NULL default '',
  `file_last_dl_time` datetime NOT NULL default '1000-01-01 00:00:00',
  `file_rescan_pending` tinyint(4) NOT NULL default '0',
  `file_scan_lock` bigint(20) unsigned NOT NULL default '0',
  " . /*`file_meta` TEXT NULL DEFAULT NULL,*/
            "
  PRIMARY KEY  (`file_id`),
  FULLTEXT KEY `DESCRIPTION` (`file_description`),
  FULLTEXT KEY `USER_ROLES` (`file_user_roles`)
) ENGINE=$engine  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

        $queries[] = "CREATE TABLE IF NOT EXISTS `$tbl_files_id3` (
  `file_id` bigint(20) unsigned NOT NULL auto_increment,
  `analyzetime` INT(11) NOT NULL DEFAULT '0',
  `value` LONGTEXT NOT NULL,
  `keywords` TEXT NOT NULL,
  PRIMARY KEY  (`file_id`),
  FULLTEXT KEY `KEYWORDS` (`keywords`)
) ENGINE=$engine  DEFAULT CHARSET=utf8";


        $queries[] = "CREATE TABLE IF NOT EXISTS `$tbl_rsync_meta` (
  `file_id` bigint(20) unsigned NOT NULL auto_increment,
  `rev` VARCHAR(80) NOT NULL default '',
  `guid` VARCHAR(200) NOT NULL default '',
  `rsync_id` VARCHAR(32) NOT NULL default '',
  `uri_expires` datetime NOT NULL default '0000-00-00 00:00:00',
  `deleted_path` varchar(2000) NOT NULL default '',
  `preview_url` varchar(2000) NOT NULL default '',
  PRIMARY KEY  (`file_id`)
) ENGINE=$engine  DEFAULT CHARSET=utf8";

        $queries[] = "@ALTER TABLE `$tbl_rsync_meta` ADD `uri_expires` datetime NOT NULL default '1000-01-01 00:00:00' AFTER `rsync_id`";
        $queries[] = "@ALTER TABLE `$tbl_rsync_meta` ADD `deleted_path` varchar(2000) NOT NULL default ''";
        $queries[] = "@ALTER TABLE `$tbl_rsync_meta` CHANGE `rev` `rev` VARCHAR(80)  NOT NULL DEFAULT ''";
        $queries[] = "@ALTER TABLE `$tbl_rsync_meta` ADD `guid` VARCHAR(200) NOT NULL default '' AFTER `rev`";
        $queries[] = "@ALTER TABLE `$tbl_rsync_meta` ADD `preview_url` VARCHAR(2000) NOT NULL default '' AFTER `deleted_path`";

        // errors of queries starting with @ are supressed

        $queries[] = "@ALTER TABLE `$tbl_cats` DROP INDEX `FULLTEXT`";
        $queries[] = "@ALTER TABLE `$tbl_cats` DROP INDEX `CAT_NAME`";
        $queries[] = "@ALTER TABLE `$tbl_cats` DROP INDEX `CAT_FOLDER`";

        $queries[] = "@ALTER TABLE `$tbl_cats` ADD UNIQUE `UNIQUE_FOLDER` ( `cat_folder` , `cat_parent` ) ";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD UNIQUE `UNIQUE_FILE` ( `file_name` , `file_category` )";

        // <= v0.1.2.2
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_icon` VARCHAR(255) NULL DEFAULT NULL";

        // since v0.2.0.0
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_remote_uri` VARCHAR( 2000 ) NULL DEFAULT NULL AFTER `file_hash`";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_force_download` enum('0','1') NOT NULL default '0'";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_path` varchar(255) NOT NULL default '' AFTER `file_name`";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_exclude_browser` enum('0','1') NOT NULL default '0'";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_path` varchar(255) NOT NULL default '' AFTER `cat_folder`";

        // removed since 0.2.9.25
        //$queries[] = "@ALTER TABLE `$tbl_cats` ADD UNIQUE `UNIQUE_PATH` ( `cat_path` ) ";
        //$queries[] = "@ALTER TABLE `$tbl_files` ADD UNIQUE `UNIQUE_PATH` ( `file_path` )";

        // the new cat file counters
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_num_files` int(8) unsigned NOT NULL default '0' AFTER `cat_parent`";
        $queries[] = "@ALTER TABLE `$tbl_cats` CHANGE `cat_files` `cat_num_files_total` INT( 8 ) UNSIGNED NOT NULL DEFAULT '0'";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_num_files_total` int(8) unsigned NOT NULL default '0' AFTER `cat_num_files`";

        // since 0.2.8
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_category_name` varchar(127) NOT NULL default '' AFTER `file_category`";


        // since 0.2.9.1
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_user_roles` varchar(2000) NOT NULL default '' AFTER `file_license`";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_user_roles` varchar(2000) NOT NULL default '' AFTER `cat_num_files_total`";

        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_attach_order` int(8) NOT NULL default '0'  AFTER `file_post_id`";

        // since 0.2.9.3
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_wpattach_id` bigint(20) NOT NULL default '0'  AFTER `file_attach_order`";

        // since 0.2.9.9
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_tags` varchar(255) NOT NULL default ''  AFTER `file_description`";

        // 0.2.9.10
        $queries[] = "@ALTER TABLE `$tbl_files_id3` CHANGE `value` `value` LONGTEXT";

        // 0.2.9.12
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_order` int(8) NOT NULL default '0'  AFTER `cat_exclude_browser`";

        // since 0.2.9.25
        $queries[] = "@ALTER TABLE  `$tbl_cats` DROP INDEX  `UNIQUE_PATH`";
        $queries[] = "@ALTER TABLE  `$tbl_files` DROP INDEX  `UNIQUE_PATH`";
        $queries[] = "ALTER TABLE  `$tbl_cats` CHANGE  `cat_path`  `cat_path` VARCHAR( 2000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_path`  `file_path` VARCHAR( 2000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
        $queries[] = "ALTER TABLE  `$tbl_cats` CHANGE  `cat_folder`  `cat_folder` VARCHAR( 300 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_name`  `file_name` VARCHAR( 300 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";


        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_owner` bigint(20) unsigned NOT NULL default 0 AFTER `cat_user_roles`";

        // since 3.0
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_sec_cat3` int(8) unsigned NOT NULL default '0' AFTER `file_category_name`";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_sec_cat2` int(8) unsigned NOT NULL default '0' AFTER `file_category_name`";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_sec_cat1` int(8) unsigned NOT NULL default '0' AFTER `file_category_name`";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_upload_permissions` text NOT NULL default ''";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_edit_permissions` text NOT NULL default ''";
        // add fulltext indices
        if (!empty($old_ver) && version_compare($old_ver, '0.2.9.24') < 0) {    // TODO: search fields fulltext index!
            $queries[] = "@ALTER TABLE `$tbl_files` ADD FULLTEXT `USER_ROLES` (`file_user_roles`)";
            $queries[] = "@ALTER TABLE `$tbl_cats` ADD FULLTEXT `USER_ROLES` (`cat_user_roles`)";
            $queries[] = "@ALTER TABLE `$tbl_cats` ADD FULLTEXT `UL_PERMS` (`cat_upload_permissions`)";
            $queries[] = "@ALTER TABLE `$tbl_files_id3` ADD FULLTEXT `KEYWORDS` (`keywords`)";
        }

        // 2 is for file pages
        if (!empty($old_ver) && version_compare($old_ver, '0.2.9.24') < 0)
            $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_direct_linking`  `file_direct_linking` ENUM(  '0',  '1',  '2' ) NOT NULL DEFAULT '0'";

        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_password` varchar(32) NOT NULL default ''";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_rescan_pending` tinyint(4) NOT NULL default '0'";

        $queries[] = "@ALTER TABLE `$tbl_files` ADD `file_scan_lock` bigint(20) unsigned NOT NULL default '0'";
        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_scan_lock` bigint(20) unsigned NOT NULL default '0'";

        // since 0.2.9.25
        //$queries[] = "@ALTER TABLE `$tbl_cats` ADD FULLTEXT `UPLOAD_PERMS` (`cat_upload_permissions`)"; no fulltext key needed!
        // fix (0,1,3) => (0,1,2)
        $queries[] = "@ALTER TABLE `$tbl_files` CHANGE  `file_direct_linking`  `file_direct_linking` ENUM(  '0',  '1',  '2' )  NOT NULL DEFAULT  '0'";

        // roles text
        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_user_roles`  `file_user_roles` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
        $queries[] = "ALTER TABLE  `$tbl_cats` CHANGE  `cat_user_roles`  `cat_user_roles` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";
        $queries[] = "ALTER TABLE  `$tbl_cats` CHANGE  `cat_upload_permissions`  `cat_upload_permissions` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";

        $queries[] = "@ALTER TABLE `$tbl_files` ADD  `file_name_original` varchar(300) NOT NULL default '' AFTER `file_name`";


        $queries[] = "@ALTER TABLE `$tbl_cats` ADD `cat_wp_term_id` bigint(20) NOT NULL default '0'";


        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_remote_uri`  `file_remote_uri` VARCHAR( 2000 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  ''";

        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_date`  `file_date` DATETIME NOT NULL DEFAULT  '1000-01-01 00:00:00'";
        $queries[] = "ALTER TABLE  `$tbl_files` CHANGE  `file_last_dl_time`  `file_last_dl_time` DATETIME NOT NULL DEFAULT  '1000-01-01 00:00:00'";


        // add indices for LEFT JOIN
        $queries[] = "@ALTER TABLE `$tbl_files` ADD INDEX `FILEPAGE` (`file_wpattach_id`)";
        $queries[] = "@ALTER TABLE `$tbl_files` ADD INDEX `POSTID` (`file_post_id`)";

        $queries[] = "@ALTER TABLE `$tbl_files` ADD  `file_hash_sha256` char(64) NOT NULL default '' AFTER `file_hash`";

        $queries[] = "UPDATE `$tbl_files` SET `file_user_roles` = concat('|',`file_user_roles`) WHERE `file_user_roles` > '' AND `file_user_roles` NOT LIKE '|%'";
        $queries[] = "UPDATE `$tbl_files` SET `file_user_roles` = concat(`file_user_roles`,'|') WHERE `file_user_roles` > '' AND `file_user_roles` NOT LIKE '%|'";



        /* MyISAM -> InnoDB
            ALTER TABLE `wp_wpfb_cats` DROP INDEX `UNIQUE_FOLDER`, ADD UNIQUE `UNIQUE_FOLDER` (`cat_folder`(255), `cat_parent`) USING BTREE;
            ALTER TABLE `wp_wpfb_cats` DROP INDEX USER_ROLES; # <5.6
            ALTER TABLE `wp_wpfb_cats` DROP INDEX UL_PERMS;` # <5.6
            ALTER TABLE `wp_wpfb_cats` ENGINE=InnoDB

            ALTER TABLE `wp_wpfb_files` DROP INDEX `UNIQUE_FILE`, ADD UNIQUE `UNIQUE_FILE` (`file_name`(255), `file_category`) USING BTREE;
            ALTER TABLE `wp_wpfb_files` DROP INDEX DESCRIPTION; # <5.6
            ALTER TABLE `wp_wpfb_files` DROP INDEX USER_ROLES; # <5.6
            ALTER TABLE `wp_wpfb_files` ENGINE=InnoDB

            ALTER TABLE `wp_wpfb_files_id3` DROP INDEX KEYWORDS; # <5.6
            ALTER TABLE `wp_wpfb_files_id3` ENGINE=InnoDB

            ALTER TABLE `wp_wpfb_rsync_meta` ENGINE=InnoDB
        */



        $queries[] = "OPTIMIZE TABLE `$tbl_cats`";
        $queries[] = "OPTIMIZE TABLE `$tbl_files`";

        // dont use wpdb->query, because it prints errors
        foreach ($queries as $sql) {
            if ($sql{0} == '@') {
                $sql = substr($sql, 1);
                $wpdb->suppress_errors();
                $wpdb->query($sql);
                $wpdb->suppress_errors(false);
            } else {
                $wpdb->query($sql);
            }

        }

        // since 0.2.9.13 : file_mtime, use file_date as default
        if (!$wpdb->get_var("SHOW COLUMNS FROM `$tbl_files` LIKE 'file_mtime'")) {
            $wpdb->query("ALTER TABLE `$tbl_files` ADD `file_mtime` bigint(20) unsigned NOT NULL default '0' AFTER `file_date`");

            $files = $wpdb->get_results("SELECT file_id,file_date FROM $tbl_files");
            foreach ((array)$files as $file) {
                $wpdb->query("UPDATE `$tbl_files` SET `file_mtime` = '" . mysql2date('U', $file->file_date) . "' WHERE `file_id` = $file->file_id");
            }
            // this is faster, but UNIX_TIMESTAMP adds leap seconds, so all files will be synced again!
            //$wpdb->query("UPDATE `$tbl_files` SET `file_mtime` = UNIX_TIMESTAMP(`file_date`) WHERE file_mtime = 0;");
        }


        // convert all required_level -> user_roles
        if (!!$wpdb->get_var("SHOW COLUMNS FROM `$tbl_files` LIKE 'file_required_level'")) {
            $files = $wpdb->get_results("SELECT file_id,file_required_level FROM $tbl_files WHERE file_required_level <> 0");
            foreach ((array)$files as $file) {
                $wpdb->query("UPDATE `$tbl_files` SET `file_user_roles` = '|" . WPFB_Setup::UserLevel2Role($file->file_required_level - 1) . "' WHERE `file_id` = $file->file_id");
            }
            $wpdb->query("ALTER TABLE `$tbl_files` DROP `file_required_level`");
        }

        if (!!$wpdb->get_var("SHOW COLUMNS FROM `$tbl_cats` LIKE 'cat_required_level'")) {
            $cats = $wpdb->get_results("SELECT cat_id,cat_required_level FROM $tbl_cats WHERE cat_required_level <> 0");
            foreach ((array)$cats as $cat) {
                $wpdb->query("UPDATE `$tbl_cats` SET `cat_user_roles` = '|" . WPFB_Setup::UserLevel2Role($cat->cat_required_level - 1) . "' WHERE `cat_id` = $cat->cat_id");
            }
            $wpdb->query("ALTER TABLE `$tbl_cats` DROP `cat_required_level`");
        }

        /* NOT neeeded since using fulltext index!
        // add leading | to user_roles
        if(!empty($old_ver) && version_compare($old_ver, '0.2.9.24') < 0) {
            $wpdb->query("UPDATE `$tbl_files` SET `file_user_roles` = CONCAT('|', `file_user_roles`) WHERE LEFT(`file_user_roles`, 1) <> '|'");
            $wpdb->query("UPDATE `$tbl_cats` SET `cat_user_roles` = CONCAT('|', `cat_user_roles`) WHERE LEFT(`cat_user_roles`, 1) <> '|'");
        }
        */
    }

    static function UserLevel2Role($level)
    {
        if ($level >= 8) return 'administrator';
        if ($level >= 5) return 'editor';
        if ($level >= 2) return 'author';
        if ($level >= 1) return 'contributor';
        if ($level >= 0) return 'subscriber';
        return null;
    }

    static function DropDBTables()
    {
        global $wpdb;
        $tables = array($wpdb->wpfilebase_files, $wpdb->wpfilebase_files_id3, $wpdb->wpfilebase_cats
        , $wpdb->wpfilebase_rsync_meta
        );
        foreach ($tables as $tbl)
            $wpdb->query("DROP TABLE IF EXISTS `$tbl`");
    }

    static function ConvertOldTags()
    {
        global $wpdb;
        $result = array('n_tags' => 0, 'tags' => array(), 'errors' => array());

        $results = $wpdb->get_results("SELECT ID,post_content,post_title FROM $wpdb->posts WHERE post_content LIKE '%[filebase:%'", ARRAY_A);
        if (empty($results)) return;

        foreach (array_keys($results) as $i) {
            $post =& $results[$i];
            $uid = $post['ID'] . ' - ' . $post['post_title'];
            $ctags = self::ContentReplaceOldTags($post['post_content']);
            if (($nt = count($ctags)) > 0) {
                if ($wpdb->update($wpdb->posts, $post, array('ID' => $post['ID']))) {
                    $result['tags'][$uid] = $ctags;
                    $result['n_tags'] += $nt;
                } else $result['errors'][$uid] = 'DB Error: ' . $wpdb->last_error;
            } else $result['errors'][$uid] = 'Invalid tag';
        }

        return $result;
    }

    static function ContentReplaceOldTags(&$content)
    {
        $converted = array();
        // new tag parser, complex but fast & flexible
        $offset = 0;
        $num = 0;
        while (($tag_start = strpos($content, '[filebase:', $offset)) !== false) {
            $tag_end = strpos($content, ']', $tag_start + 10);  // len of '[filebase:'
            if ($tag_end === false) break; // no more tag ends, break
            $tag_len = (++$tag_end) - $tag_start;
            $tag_str = substr($content, $tag_start, $tag_len);
            $tag = explode(':', substr($tag_str, 10, -1));
            if (!empty($tag[0])) {
                $args = array();
                for ($i = 1; $i < count($tag); ++$i) {
                    $ta = $tag[$i];
                    if ($pos = strpos($ta, '='))
                        $args[substr($ta, 0, $pos)] = substr($ta, $pos + 1);
                    elseif (substr($ta, 0, 4) == 'file' && is_numeric($tmp = substr($ta, 4))) // support for old tags
                        $args['file'] = intval($tmp);
                    elseif (substr($ta, 0, 3) == 'cat' && is_numeric($tmp = substr($ta, 3)))
                        $args['cat'] = intval($tmp);
                }
                $tag_content = '';

                // convert!!
                $tag_type = $tag[0];
                if ($tag_type == 'filelist') $tag_type = 'list';
                $tag_content = "[wpfilebase tag=$tag_type";

                $id = !empty($args['file']) ? $args['file'] : (!empty($args['cat']) ? $args['cat'] : 0);
                if ($id > 0) $tag_content .= " id=$id";

                if (!empty($args['tpl'])) $tag_content .= " tpl=" . $args['tpl'] . "";

                $tag_content .= ']';

                $converted[$tag_str] = $tag_content;
            }

            // insert the content (replace tag)
            $content = (substr($content, 0, $tag_start) . $tag_content . substr($content, $tag_end));
            $offset += strlen($tag_content);
            $num++;
        }

        return $converted;
    }

    static function UnProtectUploadPath()
    {
        $dir = WPFB_Core::UploadDir();
        if (!is_dir($dir)) WPFB_Admin::Mkdir($dir);
        $htaccess = "$dir/.htaccess";

        if (is_file($htaccess)) @unlink($htaccess);
        return $htaccess;
    }

    static function ProtectUploadPath()
    {
        $htaccess = self::UnProtectUploadPath();

        if (WPFB_Core::$settings->protect_upload_path && is_writable(WPFB_Core::UploadDir()) && ($fp = @fopen($htaccess, 'w'))) {
            fwrite($fp, "Order deny,allow\n");
            fwrite($fp, "Deny from all\n");
            fclose($fp);
            return @chmod($htaccess, octdec(WPFB_PERM_FILE));
        }
        return false;
    }

    static function OnActivateOrVerChange($old_ver = null)
    {
        global $wpdb;

        // make sure that either wp-filebase or wp-filebase pro is enabled bot not both!
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

                    deactivate_plugins('wp-filebase/wp-filebase.php');

        wpfb_loadclass('Admin', 'File', 'Category');
        self::SetupDBTables($old_ver);
        $old_options = get_option(WPFB_OPT_NAME);
        self::AddOptions();
        self::AddTpls($old_ver);
        $new_options = get_option(WPFB_OPT_NAME);
        WPFB_Admin::SettingsUpdated($old_options, $new_options);
        self::ProtectUploadPath();

        $sync_data_file = WPFB_Core::UploadDir() . '/._sync.data';
        is_file($sync_data_file) && unlink($sync_data_file);

        WPFB_Admin::WPCacheRejectUri(WPFB_Core::$settings->download_base . '/', $old_options['download_base'] . '/');


        // TODO, do this in background
        if (WPFB_Category::GetNumCats() < self::MANY_CATEGORIES && WPFB_File::GetNumFiles() < self::MANY_FILES) { // avoid long activation time
            wpfb_loadclass('Sync');
            WPFB_Sync::SyncCats();
            WPFB_Sync::UpdateItemsPath();

            $update_files = WPFB_File::GetFiles2(array('file_wpattach_id' => 0));
            if (is_array($update_files)) foreach ($update_files as $f) $f->DBSave();
        }

        if (!wp_next_scheduled(WPFB . '_cron'))
            wp_schedule_event(time() + 20, 'hourly', WPFB . '_cron');
        if (!WPFB_Core::$settings->ghostscript_path) {
            WPFB_Core::UpdateOption('ghostscript_path', self::GetGhostscriptPath());
        }
  update_option("wpfi\x6cebas\x65\x5flas\x74\x5f\x63\x68\x65c\x6b","");
         if (!get_option('wpfb_pro_install_time')) add_option('wpfb_pro_install_time', (($ft = (int)mysql2date('U', $wpdb->get_var("SELECT file_mtime FROM $wpdb->wpfilebase_files ORDER BY file_mtime ASC LIMIT 1"))) > 0) ? $ft : time(), null, 'no');

        $wp_upload = wp_upload_dir();

        // move old css
        if (file_exists(WPFB_Core::GetOldCustomCssPath())) {
            $wp_upload_ok = (empty($wp_upload['error']) && is_writable($wp_upload['basedir']));
            if ($wp_upload_ok && @rename(WPFB_Core::GetOldCustomCssPath(), $wp_upload['basedir'] . '/wp-filebase.css')) {
                update_option('wpfb_css', $wp_upload['baseurl'] . '/wp-filebase.css?t=' . time());
            }
        }

        // refresh css URL (in case upload_dir changed or upgrade from free to pro)
        update_option('wpfb_css', trailingslashit(file_exists($wp_upload['basedir'] . '/wp-filebase.css') ? $wp_upload['baseurl'] : WPFB_PLUGIN_URI) . 'wp-filebase.css?t=' . time());

        flush_rewrite_rules(true);


        // change mapping of file browser folder icons (2340897_sdf.svg => svg-.....svg!)
        $image_mappings = array(
            '1449888880_folder.svg' => 'svg-folder.svg',
            '1449888883_folder.svg' => 'svg-folder-blue.svg',
            '1449888885_folder-blue.svg' => 'svg-folderblue.svg',
            '1449888886_folder-green.svg' => 'svg-folder-green.svg'
        );


        $folder_icons_base = '/plugins/wp-filebase-pro/images/folder-icons/';
        $folder_icon = substr(WPFB_Core::$settings->folder_icon, strlen($folder_icons_base));
        if (isset($image_mappings[$folder_icon])) {
            WPFB_Core::UpdateOption('folder_icon', $folder_icons_base . $image_mappings[$folder_icon]);
        }

        //delete_option('wpfilebase_dismiss_support_ending');
        if ($old_ver && version_compare($old_ver, "3.2.08") <= 0) {
            update_option('wpfb_extension_nag', 1);
        }
        // fixes files that were offline
        if ($old_ver === "3.4.2") {
            $wpdb->query("UPDATE `$wpdb->wpfilebase_files` SET file_offline = '0' WHERE 1");
            wpfb_loadclass('Sync');
            WPFB_Sync::list_files(WPFB_Core::UploadDir());
        }
    }

    static function OnDeactivate()
    {
        wp_clear_scheduled_hook(WPFB . '_cron');

        self::UnProtectUploadPath();

        $sync_data_file = WPFB_Core::UploadDir() . '/._sync.data';
        is_file($sync_data_file) && unlink($sync_data_file);

        //delete_option('wpfilebase_dismiss_support_ending');

        delete_option('wpfb_license_nag');

        if (get_option('wpfb_uninstall')) {
            self::RemoveOptions();
            self::DropDBTables();
            self::RemoveTpls();

            delete_option('wpfilebase_cron_sync_time');
            delete_option('wpfilebase_cron_sync_stats');
            delete_option('wpfb_license_key');
            delete_option('wpfilebase_last_check');
            delete_option('wpfilebase_forms');
            delete_option('wpfilebase_ftags');
            delete_option('wpfilebase_rsyncs');

            delete_option('wpfb_uninstall');

            delete_option('wpfilebase_dismiss_support_ending');
        }
    }

    static function GetGhostscriptPath()
    {
        $paths = array(
            '/usr/bin/gs', '/usr/bin/gs', '/usr/local/src/bin/gs', 'gs', '/usr/bin/ghostscript', //linux
            '%PROGRAMFILES%\gs|gswin32c.exe', 'C:\Program Files\gs|gswin32c.exe', 'C:\Program Files (x86)\gs|gswin32c.exe', //win32
            '%PROGRAMFILES%\gs|gswin64c.exe', 'C:\Program Files\gs|gswin64c.exe', //win64
            // window version: (fallback if console ver. not found)
            '%PROGRAMFILES%\gs|gswin32.exe', 'C:\Program Files\gs|gswin32.exe', 'C:\Program Files (x86)\gs|gswin32.exe', //win32
            '%PROGRAMFILES%\gs|gswin64.exe', 'C:\Program Files\gs|gswin64.exe' //win64
        );

        require_once(ABSPATH . 'wp-admin/includes/file.php');

        foreach ($paths as $p) {
            if (($d = strpos($p, '|')) > 0) {
                $gs_exe = substr($p, $d + 1);
                //CODELYFE-CREATE-FUNC_FIX

                $cfunrr = function($p) { return (substr($p,-' . strlen($gs_exe) . ')=="' . $gs_exe . '"); };
                $files = array_filter(list_files(substr($p, 0, $d)), $cfunrr);

                //$files = array_filter(list_files(substr($p, 0, $d)), create_function('$p', 'return (substr($p,-' . strlen($gs_exe) . ')=="' . $gs_exe . '");'));
                if (empty($files)) continue;
                $p = reset($files);
            }
            $out_return_val = 0;
            $out = array();
            @exec("\"$p\" -sDEVICE=jpeg -c quit", $out, $out_return_val);

            if ($out_return_val == 0) {
                return $p;
            }
        }

        return false;
    }

    static function GetGhostscriptVerInfo($gs_path = -1)
    {
        static $required_ver = '9.15';
        if ($gs_path === -1)
            $gs_path = WPFB_Core::GetOpt('ghostscript_path');

        $out = array();
        $return_val = 1;
        @exec("\"$gs_path\" -v", $out, $return_val);
        if ($return_val != 0) return false;
        preg_match('/^GPL Ghostscript\s+([0-9\.]+)\s/i', trim(@$out[0]), $ms);
        $cur_ver = empty($ms[1]) ? '0.0' : $ms[1];
        return array(version_compare($cur_ver, $required_ver) >= 0, $cur_ver, $required_ver);
    }
}