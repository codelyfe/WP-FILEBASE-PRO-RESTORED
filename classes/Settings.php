<?php class WPFB_Settings {

private static function cleanPath($path) {
	return str_replace('//','/',str_replace('\\', '/', $path));
}

static function Schema()
{
	wpfb_loadclass('Models');

	$multiple_entries_desc = __('One entry per line. Seperate the title and a short tag (not longer than 8 characters) with \'|\'.<br />All lines beginning with \'*\' are selected by default.','wp-filebase');
	$multiple_line_desc = __('One entry per line.','wp-filebase');
	$bitrate_desc = __('Limits the maximum tranfer rate for downloads. 0 = unlimited','wp-filebase');
	$traffic_desc = __('Limits the maximum data traffic. 0 = unlimited','wp-filebase');
	$dls_per_day = __('downloads per day','wp-filebase');
	$daily_limit_for = __('Daily limit for %s','wp-filebase');

	$upload_path_base = str_replace(ABSPATH, '', get_option('upload_path'));
	if($upload_path_base == '' || $upload_path_base == '/')
		$upload_path_base = 'wp-content/uploads';

	$sync_stats	= (get_option('wpfilebase_cron_sync_stats'));
	wpfb_loadclass('Output');
	$last_sync_time =  (!empty($sync_stats)) ? ("<br> (".
		sprintf( __('Last cron sync %s ago took %s and used %s of RAM.','wp-filebase'), human_time_diff($sync_stats['t_start']), human_time_diff($sync_stats['t_start'], $sync_stats['t_end']), WPFB_Output::FormatFilesize($sync_stats['mem_peak']) )
		." "
		.(($next=wp_next_scheduled(WPFB.'_cron')) ? sprintf( __('Next cron sync scheduled in %s.','wp-filebase'), human_time_diff(time(), $next) ) : "")
		.")") : '';

	$list_tpls = array_keys(wpfb_call('ListTpl','GetAll'));
	$list_tpls = empty($list_tpls) ? array() : array_combine($list_tpls, $list_tpls);

	global $wp_roles;
	static $default_roles = array('administrator', 'subscriber','contributor','author','editor');
	$role_limit_opts = array();
	foreach ( $wp_roles->roles as $role => $details ) {
		if(!in_array($role, $default_roles))
			$role_limit_opts['daily_limit_'.$role] = array('default' => 10, 'title' => sprintf($daily_limit_for, translate_user_role($details['name'])), 'type' => 'number', 'unit' => $dls_per_day);
	}

	require_once(ABSPATH . 'wp-admin/includes/file.php');

	$folder_icon_files = array_map(array(__CLASS__,'cleanPath'), array_merge(list_files(WPFB_PLUGIN_ROOT.'images/folder-icons'), list_files(WP_CONTENT_DIR.'/images/foldericons')));
	sort($folder_icon_files);
	$folder_icons = array();
	foreach($folder_icon_files as $fif)
		$folder_icons[] = array('path' => str_replace(self::cleanPath(WP_CONTENT_DIR),'',$fif),'url' => str_replace(self::cleanPath(WP_CONTENT_DIR),WP_CONTENT_URL,$fif));


	$isApache = stripos($_SERVER["SERVER_SOFTWARE"], 'Apache') !== false;


	if(!$isApache) {
		$nginx_conf = "<pre>location /" . ltrim(WPFB_Core::GetOpt('upload_path'), '/') . " {\n\tdeny all;\n\treturn 403;\n}\n</pre>";
		$protect_instructions = "<br><b>Please add the following rules to your nginx config file to disable direct file access:</b><br>$nginx_conf";
	}

	return
	array_merge($role_limit_opts,
	array (

	// common
	'upload_path'			=> array('default' => $upload_path_base . '/filebase', 'title' => __('Upload Path','wp-filebase'), 'desc' => __('Path where all files are stored. Relative to WordPress\' root directory.','wp-filebase'), 'type' => 'text', 'class' => 'code', 'size' => 65),
	'thumbnail_size'		=> array('default' => 300, 'title' => __('Thumbnail size'), 'desc' => __('The maximum side of the image is scaled to this value.','wp-filebase'), 'type' => 'number', 'class' => 'num', 'size' => 8),
	'thumbnail_path'		=> array('default' => '', 'title' => __('Thumbnail Path','wp-filebase'), 'desc' => __('Thumbnails can be stored at a different path than the actual files. Leave empty to use the default upload path. The directory specified here CANNOT be inside the upload path!','wp-filebase'), 'type' => 'text', 'class' => 'code', 'size' => 65),

	'base_auto_thumb'		=> array('default' => true, 'title' => __('Auto-detect thumbnails','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Images are considered as thumbnails for files with the same name when syncing. (e.g `file.jpg` &lt;=&gt; `file.zip`)','wp-filebase')),

	'fext_blacklist'		=> array('default' => 'db,tmp', 'title' => __('Extension Blacklist','wp-filebase'), 'desc' => __('Files with an extension in this list are skipped while synchronisation. (seperate with comma)','wp-filebase'), 'type' => 'text', 'class' => 'code', 'size' => 100),

	'attach_pos'			=> array('default' => 1, 'title' => __('Attachment Position','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'select', 'options' => array(__('Before the Content','wp-filebase'),__('After the Content','wp-filebase'))),

	'attach_loop' 			=> array('default' => false,'title' => __('Attachments in post lists','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Attach files to posts in archives, index and search result.','wp-filebase')),

	// display
	'auto_attach_files' 	=> array('default' => true,'title' => __('Show attached files','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled, all associated files are listed below an article','wp-filebase')),
	'filelist_sorting'		=> array('default' => 'file_display_name', 'title' => __('Default sorting','wp-filebase'), 'type' => 'select', 'desc' => __('The file property lists are sorted by','wp-filebase'), 'options' => WPFB_Models::FileSortFields()),
	'filelist_sorting_dir'	=> array('default' => 0, 'title' => __('Sort Order:'/*def*/), 'type' => 'select', 'desc' => __('The sorting direction of file lists','wp-filebase'), 'options' => array(0 => __('Ascending'), 1 => __('Descending'))),
	'filelist_num'			=> array('default' => 0, 'title' => __('Number of files per page','wp-filebase'), 'type' => 'number', 'desc' => __('Length of the file list per page. Set to 0 to disable the limit.','wp-filebase')),

	'file_date_format'	=> array('default' => get_option('date_format'), 'title' => __('File Date Format','wp-filebase'), 'desc' => __('Date/Time formatting for files.','wp-filebase').' '.__('<a href="http://codex.wordpress.org/Formatting_Date_and_Time">Documentation on date and time formatting</a>.'), 'type' => 'text', 'class' => 'small-text'),

	'disable_css'			=> array('default' => false, 'title' => __('Disable wp-filebase.css','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If you don\'t need default WP-Filebase styling. Improves site performance.','wp-filebase')),

	'category_substitute' => array('default' => '', 'title' => __('<i>Category</i> Substitute','wp-filebase'), 'desc' => __('Alternative term for the label of category drop downs in upload forms. Affects front-end only (Widgets &amp; Embedded Forms).','wp-filebase'), 'type' => 'text'),
	// limits
	'bitrate_unregistered'	=> array('default' => 0, 'title' => __('Bit rate limit for guests','wp-filebase'), 'type' => 'number', 'unit' => 'KiB/Sec', 'desc' => &$bitrate_desc),
	'bitrate_registered'	=> array('default' => 0, 'title' => __('Bit rate limit for registered users','wp-filebase'), 'type' => 'number', 'unit' => 'KiB/Sec', 'desc' => &$bitrate_desc),
	'traffic_day'			=> array('default' => 0, 'title' => __('Daily traffic limit','wp-filebase'), 'type' => 'number', 'unit' => 'MiB', 'desc' => &$traffic_desc),
	'traffic_month'			=> array('default' => 0, 'title' => __('Monthly traffic limit','wp-filebase'), 'type' => 'number', 'unit' => 'GiB', 'desc' => &$traffic_desc),
	'traffic_exceeded_msg'	=> array('default' => __('Traffic limit exceeded! Please try again later.','wp-filebase'), 'title' => __('Traffic exceeded message','wp-filebase'), 'type' => 'text', 'size' => 65),
	'file_offline_msg'		=> array('default' => __('This file is currently offline.','wp-filebase'), 'title' => __('File offline message','wp-filebase'), 'type' => 'text', 'size' => 65),

	'daily_user_limits'		=> array('default' => false, 'title' => __('Daily user download limits','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled, unregistered users cannot download any files. You can set different limits for each user role below.','wp-filebase')),

	'daily_limit_subscriber'	=> array('default' => 5, 'title' => sprintf($daily_limit_for, _x('Subscriber', 'User role')), 'type' => 'number', 'unit' => &$dls_per_day),
	'daily_limit_contributor'	=> array('default' => 10, 'title' => sprintf($daily_limit_for, _x('Contributor', 'User role')), 'type' => 'number', 'unit' => &$dls_per_day),
	'daily_limit_author'		=> array('default' => 15, 'title' => sprintf($daily_limit_for, _x('Author', 'User role')), 'type' => 'number', 'unit' => &$dls_per_day),
	'daily_limit_editor'		=> array('default' => 20, 'title' => sprintf($daily_limit_for, _x('Editor', 'User role')), 'type' => 'number', 'unit' => &$dls_per_day),

	'daily_limit_exceeded_msg'	=> array('default' => __('You can only download %d files per day.','wp-filebase'), 'title' => __('Daily limit exceeded message','wp-filebase'), 'type' => 'text', 'size' => 65),

	// download
	'file_page_proxy'		=> array('default' => false, 'title' => __('File Page Proxy','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('Redirects users to a page with file details. See <a href="%s">Filepage Template</a>','wp-filebase'), esc_attr(admin_url('admin.php?page=wpfilebase_tpls&action=edit&type=file&tpl=filepage')))),
	'file_page_countdown'	=> array('default' => 0, 'title' => __('File Page Download Countdown','wp-filebase'), 'type' => 'number', 'unit' => 'sec', 'desc' => __('Time the user has to wait until the download automatically starts on a file page. Use <code>%dl_countdown%</code> in Filepage Template to display the time. Set to 0 to disable this feature.','wp-filebase')),
	'file_page_comments'		=> array('default' => false, 'title' => __('Comments on File Page','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('If users are able to leave comments on Filepages. After changing you must run the <a href="%s">Rescan Files</a> tool.','wp-filebase'), esc_attr(admin_url('admin.php?page=wpfilebase_manage&action=rescan')))),
	'file_page_url_slug' => array('default' => 'wpfb-file', 'title' => __('File Page URL slug','wp-filebase'), 'type' => 'text', 'desc' => sprintf(__('The url prefix for Filepage links. Example: <code>%s</code> (Only used when Permalinks are enabled.)','wp-filebase'), get_option('home').'/%value%/my-file-pdf/')),
	'file_page_url_wfront' => array('default' => true, 'title' => __('File Page Permalink with Front Base','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('Should the permastruct be prepended with the front base. (enabled-> /%s/, disabled->/blog/%s/)</a>','wp-filebase'), @WPFB_Core::$settings->file_page_url_slug, @WPFB_Core::$settings->file_page_url_slug )),

	'file_page_gen_content' => array('default' => true, 'title' => __('Content Keywords','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('Put keywords in File Pages content to make them searchable by plugins.','wp-filebase'), esc_attr(admin_url('admin.php?page=wpfilebase_manage&action=rescan')))),
	'disable_permalinks'	=> array('default' => false, 'title' => __('Disable download permalinks','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Enable this if you have problems with permalinks.','wp-filebase')),
	'download_base'			=> array('default' => 'download', 'title' => __('Download URL base','wp-filebase'), 'type' => 'text', 'desc' => sprintf(__('The url prefix for file download links. Example: <code>%s</code> (Only used when Permalinks are enabled.)','wp-filebase'), get_option('home').'/%value%/category/file.zip')),

	'file_browser_post_id'		=> array('default' => '', 'title' => __('Post ID of the file browser','wp-filebase'), 'type' => 'number', 'unit' => '<span id="file_browser_post_title">'.(($fbid=@WPFB_Core::$settings->file_browser_post_id)?('<a href="'.get_permalink($fbid).'">'.get_the_title($fbid).'</a>'):'').'</span> <a href="javascript:;" class="button" onclick="WPFB_PostBrowser(\'file_browser_post_id\',\'file_browser_post_title\')">' . __('Select') . '</a>', 'desc' => __('Specify the ID of the post or page where the file browser should be placed. If you want to disable this feature leave the field blank.','wp-filebase').' '.__('Note that the selected page should <b>not have any sub-pages</b>!')),

	'file_browser_cat_sort_by'		=> array('default' => 'cat_name', 'title' => __('File browser category sorting','wp-filebase'), 'type' => 'select', 'desc' => __('The category property categories in the file browser are sorted by','wp-filebase'), 'options' => WPFB_Models::CatSortFields()),
	'file_browser_cat_sort_dir'	=> array('default' => 0, 'title' => __('Sort Order:'/*def*/), 'type' => 'select', 'desc' => '', 'options' => array(0 => __('Ascending'), 1 => __('Descending'))),

	'file_browser_file_sort_by'		=> array('default' => 'file_display_name', 'title' => __('File browser file sorting','wp-filebase'), 'type' => 'select', 'desc' => __('The file property files in the file browser are sorted by','wp-filebase'), 'options' => WPFB_Models::FileSortFields()),
	'file_browser_file_sort_dir'	=> array('default' => 0, 'title' => __('Sort Order:'/*def*/), 'type' => 'select', 'desc' => '', 'options' => array(0 => __('Ascending'), 1 => __('Descending'))),

	'file_browser_fbc'		=> array('default' => false, 'title' => __('Files before Categories','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Files will appear above categories in the file browser.','wp-filebase')),

   'file_browser_expanded'		=> array('default' => false, 'title' => __('Initially Expanded','wp-filebase'), 'type' => 'checkbox', 'desc' => __('The File Browser will be fully expanded when page is loaded. Do not enabled if you have many files!','wp-filebase')),
   'file_browser_empty_msg'		=> array('default' => __('No files available.','wp-filebase'), 'title' => __('Empty Message','wp-filebase'), 'type' => 'text', 'size' => 65, 'desc' => __('Message that will be displayed if file browser is empty.','wp-filebase')),
        'file_browser_inline_add' => array('default' => true, 'title' => __('Inline Add','wp-filebase'), 'type' => 'checkbox', 'desc' => __('In each category display actions to add a file or category.','wp-filebase')),

			'folder_icon' => array('default' => '/plugins/wp-filebase-pro/images/folder-icons/folder_orange48.png', 'title' => __('Folder Icon','wp-filebase'), 'type' => 'icon', 'icons' => $folder_icons, 'desc' => sprintf(__('Choose the default category icon and file browser icon. You can put custom icons in <code>%s</code>.','wp-filebase'),'wp-content/images/foldericons')),
		'small_icon_size'		=> array('default' => 32, 'title' => __('Small Icon Size'), 'desc' => __('Icon size (height) for categories and files. Set to 0 to show icons in full size.','wp-filebase'), 'type' => 'number', 'class' => 'num', 'size' => 8),


	'cat_drop_down'			=> array('default' => false, 'title' => __('Category drop down list','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Use category drop down list in the file browser instead of listing like files.','wp-filebase')),

	'force_download'		=> array('default' => false, 'title' => __('Always force download','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled files that can be viewed in the browser (like images, PDF documents or videos) can only be downloaded (no streaming).','wp-filebase')),
	'range_download'		=> array('default' => true, 'title' => __('Send HTTP-Range header','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Allows users to pause downloads and continue later. In addition download managers can use multiple connections at the same time.','wp-filebase')),
	'hide_links'			=> array('default' => false, 'title' => __('Hide download links','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('File download links wont be displayed in the browser\'s status bar. You should enable \'%s\' to make it even harder to find out the URL.','wp-filebase'), __('Always force download','wp-filebase')). ' '. __('<b>Note:</b> We do not recommend enabling this option because users cannot open files in new tabs.','wp-filebase')),
	'ignore_admin_dls'		=> array('default' => true, 'title' => __('Ignore downloads by admins','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('Download by an admin user does not increase hit counter. <a href="%s" class="button" onclick="alert(\'Sure?\');" style="vertical-align: baseline;">Reset All Hit Counters to 0</a>'),esc_attr(admin_url('admin.php?page=wpfilebase_manage&action=reset-hits')))),
	'hide_inaccessible'		=> array('default' => false, 'title' => __('Hide inaccessible files and categories','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled files tagged <i>For members only</i> will not be listed for guests or users whith insufficient rights.','wp-filebase')),
    'list_inaccessible'     => array('default' => false, 'title' => __('List inaccessible categories','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Users can list files in protected categories. Descendants still inherit permissions.','wp-filebase')),

            //        'hide_inaccessible'		=> array('default' => false, 'title' => __('Hide inaccessible files and categories','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled files tagged <i>For members only</i> will not be listed for guests or users whith insufficient rights.','wp-filebase')),

	'inaccessible_msg'		=> array('default' => __('You are not allowed to access this file!','wp-filebase'), 'title' => __('Inaccessible file message','wp-filebase'), 'type' => 'text', 'size' => 65, 'desc' => (__('This message will be displayed if users try to download a file they cannot access','wp-filebase').'. '.__('You can enter a URL to redirect users.','wp-filebase'))),
	'inaccessible_redirect'	=> array('default' => false, 'title' => __('Redirect to login','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Guests trying to download inaccessible files are redirected to the login page if this option is enabled.','wp-filebase')),
	'cat_inaccessible_msg'	=> array('default' => __('Access to category denied!','wp-filebase'), 'title' => __('Inaccessible category message','wp-filebase'), 'type' => 'text', 'size' => 65, 'desc' => (__('This message will be displayed if users try to access a category without permission.','wp-filebase'))),
	'login_redirect_src'	=> array('default' => false, 'title' => __('Redirect to referring page after login','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Users are redirected to the page where they clicked on the download link after logging in.','wp-filebase')),

	'http_nocache'			=> array('default' => false, 'title' => __('Disable HTTP Caching','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Enable this if you have problems with downloads while using Wordpress with a cache plugin.','wp-filebase')),

	'parse_tags_rss'		=> array('default' => true, 'title' => __('Parse template tags in RSS feeds','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled WP-Filebase content tags are parsed in RSS feeds.','wp-filebase')),

	'allow_srv_script_upload'	=> array('default' => false, 'title' => __('Allow script upload','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If you enable this, scripts like PHP or CGI can be uploaded. <b>WARNING:</b> Enabling script uploads is a <b>security risk</b>!','wp-filebase')),
	'protect_upload_path'       => array('default' => true && $isApache, 'title' => __('Protect upload path','wp-filebase'), 'type' => 'checkbox', 'desc' => __('This prevents direct access to files in the upload directory.','wp-filebase'). ' '.__('Only applies on Apache webservers! For non-Apache server you have to edit its config file manually.','wp-filebase').$protect_instructions, 'disabled' => !$isApache),

	'enable_cat_zip'		=> array('default' => false, 'title' => __('Category ZIP Files','wp-filebase'), 'type' => 'checkbox', 'desc' => sprintf(__('Allows users to download whole categories in a compressed zip file. Use template variable <code>%s</code>.','wp-filebase'),'%cat_zip_url%')),

	'private_files'			=> array('default' => false, 'title' => __('Private Files','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Access to files is only permitted to owner and administrators.','wp-filebase').' '.__('This completely overrides access permissions.','wp-filebase')),

	'frontend_upload'  		=> array('default' => false, 'title' => __('Enable front end uploads','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Global option to allow file uploads from widgets and embedded file forms','wp-filebase')), //  (Pro only)


	'accept_empty_referers'	=> array('default' => false, 'title' => __('Accept empty referers','wp-filebase'), 'type' => 'checkbox', 'desc' => __('If enabled, direct-link-protected files can be downloaded when the referer is empty (i.e. user entered file url in address bar or browser does not send referers)','wp-filebase')),
	'allowed_referers' 		=> array('default' => '', 'title' => __('Allowed referers','wp-filebase'), 'type' => 'textarea', 'desc' => __('Sites with matching URLs can link to files directly.','wp-filebase').'<br />'.$multiple_line_desc),

	//'dl_destroy_session' 	=> array('default' => false, 'title' => __('Destroy session when downloading','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Should be enabled to allow users to download multiple files at the same time. This does not interfere WordPress user sessions, but can cause trouble with other plugins using the global $_SESSION.','wp-filebase')),
	'use_fpassthru'			=> array('default' => false, 'title' => __('Use fpassthru','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Downloads will be serverd using the native PHP function fpassthru. Enable this when you are experiencing trouble with large files. Note that bandwidth throttle is not available for this method.','wp-filebase')),

	'decimal_size_format'	=> array('default' => false, 'title' => __('Decimal file size prefixes','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Enable this if you want decimal prefixes (1 MB = 1000 KB = 1 000 000 B) instead of binary (1 MiB = 1024 KiB = 1 048 576 B)','wp-filebase')),

	'admin_bar'	=> array('default' => true, 'title' => __('Add WP-Filebase to admin menu bar','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Display some quick actions for file management in the admin menu bar.','wp-filebase')),
	//'file_context_menu'	=> array('default' => true, 'title' => '', 'type' => 'checkbox', 'desc' => ''),

	'cron_sync'	=> array('default' => true, 'title' => __('Automatic Sync','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Schedules a cronjob to hourly synchronize the filesystem and the database.','wp-filebase').$last_sync_time),

	'remove_missing_files'	=> array('default' => false, 'title' => __('Remove Missing Files','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Missing files are removed from the database during sync','wp-filebase')),

	'upload_notifications' =>  array('default' => false, 'title' => __('Upload Notifications','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Notifies all blog users whenever a file they can access is uploaded.','wp-filebase')),

	'search_integration' =>  array('default' => true, 'title' => __('Search Integration','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Searches in attached files and lists the associated posts and pages when searching the site.','wp-filebase').' '.sprintf(__('If you experience performance issues with many posts and files (>1000), disable this option but enable %s.','wp-filebase'), 'File Pages / Content Keywords')),

	'search_result_tpl' =>  array('default' => 'default', 'title' => __('Search Result File List Template','wp-filebase'), 'type' => 'select', 'options' => $list_tpls, 'desc' => __('Set the List Template used for Search Results when using the Search Widget','wp-filebase')),

	'search_results'		=> array('default' => 'pages', 'title' => __('Search Results Type','wp-filebase'), 'desc' => __('The way search results are displayed when using the WordPress Search','wp-filebase'), 'type' => 'select', 'options' => array('pages' => __('Show File Pages','wp-filebase'), 'list' => __('File List','wp-filebase'))),
	'disable_id3' =>  array('default' => false, 'title' => __('Disable ID3 tag detection','wp-filebase'), 'type' => 'checkbox', 'desc' => __('This disables all meta file info reading. Use this option if you have issues adding large files.','wp-filebase')),
	'search_id3' =>  array('default' => true, 'title' => __('Deep file search','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Search in file meta data, like ID3 for MP3 files, EXIF for JPEG... (this option does not increase significantly server load since all data is cached in a MySQL table)','wp-filebase') .' '. sprintf(__('For PDF documents there is a <a href="%s">OCR extension</a>.','wp-filebase'), 'https://wpfilebase.com/?p=7322&ref=sets-ocr-ext')),
	'use_path_tags' => array('default' => false, 'title' => __('Use path instead of ID in Shortcode','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Files and Categories are identified by paths and not by their IDs in the generated Shortcodes','wp-filebase')),
	'no_name_formatting'  => array('default' => false, 'title' => __('Disable Name Formatting','wp-filebase'), 'type' => 'checkbox', 'desc' => __('This will disable automatic formatting/uppercasing file names when they are used as title (e.g. when syncing)','wp-filebase')),

	'ghostscript_path' => array('default' => ''/*default is empty, will be detected!*/, 'title' => __('Ghostscript Path','wp-filebase'), 'desc' => __('Path to Ghostscript executable used for PDF thumbnails. If ghostscript is not installed, PDF thumbnails will not be created.','wp-filebase'), 'type' => 'text', 'class' => 'code', 'size' => 65),
	'pdf_thumbnails' => array('default' => true, 'title' => __('Generate PDF thumbnails','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Thumbnail of first page','wp-filebase')),
	'pdf_extract_title' =>  array('default' => false, 'title' => __('Extract PDF Title','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Using the pattern below, Titles of PDF documents will be searched and used as file_display_name','wp-filebase')),
	'pdf_title_regex' => array('default' => '([^0-9]{2,}.+)\r?\n', 'title' => __('PDF Title Pattern','wp-filebase'), 'desc' => __('Regular expression to find the title in the PDF Document.','wp-filebase'), 'type' => 'text', 'class' => 'code', 'size' => 65),
	'rpc_calls' => array('default' => false, 'title' => __('Scan Files via RPC Calls','wp-filebase'), 'type' => 'checkbox', 'desc' => __('EXPERIMENTAL. This can improve stability of sync but requires more time for each file beeing scanned.','wp-filebase')),
	'fake_md5' => array('default' => false, 'title' => __('Fake MD5 Hashes','wp-filebase'), 'type' => 'checkbox', 'desc' => __('This dramatically speeds up sync, since no real MD5 checksum of the files is calculated but only a hash of modification time and file size.','wp-filebase')),


	// file browser
	'late_script_loading'	=> array('default' => false, 'title' => __('Late script loading','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Scripts will be included in content, not in header. Enable if your AJAX tree view does not work properly.','wp-filebase')),

	'default_author' => array('default' => '', 'title' => __('Default Author','wp-filebase'), 'desc' => __('This author will be used as form default and when adding files with FTP','wp-filebase'), 'type' => 'text', 'size' => 65),
	'default_roles' => array('default' => array(), 'title' => __('Default User Roles','wp-filebase'), 'desc' => __('These roles are selected by default and will be used for files added with FTP','wp-filebase'), 'type' => 'roles'),

	'default_cat' => array('default' => 0, 'title' => __('Default Category','wp-filebase'), 'desc' => __('Preset Category in the file form','wp-filebase'), 'type' => 'cat'),

	'languages'				=> array('default' => "English|en\nDeutsch|de", 'title' => __('Languages'), 'type' => 'textarea', 'desc' => &$multiple_entries_desc),
	'platforms'				=> array('default' => "Windows 7|win7\n*Windows 8|win8\nLinux|linux\nMac OS X|mac", 'title' => __('Platforms','wp-filebase'), 'type' => 'textarea', 'desc' => &$multiple_entries_desc, 'nowrap' => true),
	'licenses'				=> array('default' =>
"*Freeware|free\nShareware|share\nGNU General Public License|gpl|http://www.gnu.org/copyleft/gpl.html\nCC Attribution-NonCommercial-ShareAlike|ccbyncsa|http://creativecommons.org/licenses/by-nc-sa/3.0/", 'title' => __('Licenses','wp-filebase'), 'type' => 'textarea', 'desc' => &$multiple_entries_desc, 'nowrap' => true),
	'requirements'			=> array('default' => "Java|java|http://www.java.com/download/\nOpen Office|ooffice|http://www.openoffice.org/download/index.html\n",
	'title' => __('Requirements','wp-filebase'), 'type' => 'textarea', 'desc' => $multiple_entries_desc . ' ' . __('You can optionally add |<i>URL</i> to each line to link to the required software/file.','wp-filebase'), 'nowrap' => true),

	'default_direct_linking'	=> array('default' => 1, 'title' => __('Default File Direct Linking'), 'type' => 'select', 'desc' => __('','wp-filebase'), 'options' => array(1 => __('Allow direct linking','wp-filebase'), 0 => __('Redirect to post','wp-filebase') ,2 => __('Redirect to file page','wp-filebase'))),

	'custom_fields'			=> array('default' => "Custom 1|cf1\nType|type|[draft,release,sample]", 'title' => __('Custom Fields'), 'type' => 'textarea', 'desc' =>
	__('With custom fields you can add even more file properties.','wp-filebase').' '.$multiple_entries_desc.' '
    . sprintf(__('Append another %s to set the default value.','wp-filebase'),'|<i>Default Value</i>').' '
        . sprintf(__('Set default value to %s for a selection field.','wp-filebase'),'<code>[option1,option2]</code>')),


	'perm_upload_files'			=> array('default' => array('administrator','editor','author'), 'title' => __('Upload Files','wp-filebase'), 'desc' => __('Roles and Users allowed to upload files in Admin Dashboard','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),
	'perm_edit_file_details'	=> array('default' => array('administrator','editor','author'), 'title' => __('Edit File Details','wp-filebase'), 'desc' => __('Roles and Users allowed to edit file details of files uploaded by others','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),
	'perm_delete_files'			=> array('default' => array('administrator','editor','author'), 'title' => __('Delete Files','wp-filebase'), 'desc' => __('Roles and Users allowed to delete files uploaded by others','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),

	'perm_create_cat'			=> array('default' => array('administrator','editor','author'), 'title' => __('Create Categories','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),
	'perm_delete_cat'			=> array('default' => array('administrator','editor','author'), 'title' => __('Delete Categories','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),

	'perm_frontend_upload'		=> array('default' => array('administrator','editor','author'), 'title' => __('Frontend Upload','wp-filebase'), 'desc' => __('Roles and Users allowed to upload files with the Upload Widget or Embedded Forms','wp-filebase'), 'type' => 'roles'),

	'perm_manage_templates'		=> array('default' => array('administrator','editor'), 'title' => __('Manage Templates','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),

	'perm_manage_rsyncs'		=> array('default' => array('administrator','editor'), 'title' => __('Manage Remote Syncs','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),
	'perm_manage_forms'			=> array('default' => array('administrator','editor'), 'title' => __('Manage Forms','wp-filebase'), 'desc' => __('','wp-filebase'), 'type' => 'roles', 'not_everyone' => true),


	'template_file'			=> array('default' =>
<<<TPLFILE

<div class="wpfb-file-%file_id%" onclick="if('undefined' == typeof event.target.href) document.getElementById('wpfb-file-link-%uid%').click();"
  style="background: #eee; box-shadow: 1px 1px 1px  #CCC; max-width: 440px; margin: auto; cursor:pointer; margin: 1.5em;">

<div style="padding: 1em; display: flex;">
  <a href="%file_url%" target="_blank" title="Download %file_display_name%" style="text-decoration: none; box-shadow: none;" id="wpfb-file-link-%uid%"><img align="middle" src="%file_icon_url%" alt="%file_display_name%" style="max-width:5em;" /></a>
  <div style="margin-left:1em;">
    <span style="font-size:1.2em;">%file_display_name%</span> %button_edit% %button_delete%<br />
    <!-- IF %file_version% -->Version %file_version%<br /><!-- ENDIF -->
       
  </div>
  <div style="margin-left: auto; padding-left: 0.3em; min-width: 4.5em;">
    %file_size%<br />
    %file_hits% <u>&#129095;</u><br />
    <a href="#" onclick="return wpfilebase_filedetails(%uid%);">%'Details'%</a>

  </div>
</div>

 
  <div class="details" id="wpfilebase-filedetails%uid%" style="display: none; background-color: #ccc; padding: 1em;">
  <!-- IF %file_description% --><p>%file_description%</p><!-- ENDIF -->
  <table border="0">
   <tr><td><strong>%'File Name'%</strong></td><td>%file_name%</td></tr>
   <!-- IF %file_languages% --><tr><td><strong>%'Languages'%</strong></td><td>%file_languages%</td></tr><!-- ENDIF -->
   <!-- IF %file_author% --><tr><td><strong>%'Author'%</strong></td><td>%file_author%</td></tr><!-- ENDIF -->
   <!-- IF %file_platforms% --><tr><td><strong>%'Platforms'%</strong></td><td>%file_platforms%</td></tr><!-- ENDIF -->
   <!-- IF %file_requirements% --><tr><td><strong>%'Requirements'%</strong></td><td>%file_requirements%</td></tr><!-- ENDIF -->
   <!-- IF %file_category% --><tr><td><strong>%'Category:'%</strong></td><td>%file_category%</td></tr><!-- ENDIF -->
   <!-- IF %file_license% --><tr><td><strong>%'License'%</strong></td><td>%file_license%</td></tr><!-- ENDIF -->
   <tr><td><strong>%'Date'%</strong></td><td>%file_date%</td></tr>
  </table>
      <!-- IF %file_post_id% AND %post_id% != %file_post_id% --><a href="%file_post_url%" style="display: inline-block; float:right;">%'More '%</a><!-- ENDIF -->
<div style="clear: both;"></div>
  </div>
 <div style="clear: both;"></div>
</div>
TPLFILE
	, 'title' => __('Default File Template','wp-filebase'), 'type' => 'textarea', 'desc' => (WPFB_Models::TplFieldsSelect('template_file') . '<br />' . __('The template for attachments','wp-filebase')), 'class' => 'code'),

	'template_cat'			=> array('default' =>
<<<TPLCAT
<div class="wpfilebase-cat-default">
  <h3>
    <!-- IF %cat_has_icon% || true -->%cat_small_icon%<!-- ENDIF -->
    <a href="%cat_url%" title="Go to category %cat_name%">%cat_name%</a>
    <span>%cat_num_files% <!-- IF %cat_num_files% == 1 -->file<!-- ELSE -->files<!-- ENDIF --></span>
  </h3>
</div>
TPLCAT
	, 'title' => __('Category Template','wp-filebase'), 'type' => 'textarea', 'desc' => (WPFB_Models::TplFieldsSelect('template_cat', false, true) . '<br />' . __('The template for category lists (used in the file browser)','wp-filebase')), 'class' => 'code'),

	'dlclick_js'			=> array('default' =>
<<<JS
if(typeof pageTracker == 'object') {
	pageTracker._trackPageview(file_url); // new google analytics tracker
} else if(typeof urchinTracker == 'function') {	
	urchinTracker(file_url); // old google analytics tracker
} else if(typeof ga == 'function') {
	ga('send', 'pageview', file_url); // universal analytics
}
JS
	, 'title' => __('Download JavaScript','wp-filebase'), 'type' => 'textarea', 'desc' => __('Here you can enter JavaScript Code which is executed when a user clicks on file download link. The following variables can be used: <i>file_id</i>: the ID of the file, <i>file_url</i>: the clicked download url','wp-filebase'), 'class' => 'code'),

	'upload_ntf_tpl'			=> array('default' =>
<<<TPL
<!-- IF %embedded_form_tag% -->New file upload with form "%embedded_form_tag%"<!-- ELSE -->New file upload<!-- ENDIF -->
Uploader : %uploader_name% (IP: %uploader_ip% , %uploader_host%)
File: %file_display_name% (%file_size%)
Category: %file_category%
	
%file_more_data%

<!-- IF %file_offline% -->The file is not approved yet (currently offline).<!-- ENDIF -->
Download: %file_url%
<!-- IF %file_email_user_can_edit% -->Edit File: %file_edit_url%<!-- ENDIF -->
<!-- IF %file_email_user_can_edit% && %file_offline% -->Approve it: %file_approve_url%<!-- ENDIF -->
<!-- IF %file_email_user_can_delete% -->Delete it: %file_delete_url%<!-- ENDIF -->
TPL
	, 'title' => __('Upload Notification Template','wp-filebase'), 'type' => 'textarea', 'desc' => __('Template used for emails sent on uploads.','wp-filebase'), 'class' => 'code'),
	//'max_dls_per_ip'			=> array('default' => 10, 'title' => __('Maximum downloads','wp-filebase'), 'type' => 'number', 'unit' => 'per file, per IP Address', 'desc' => 'Maximum number of downloads of a file allowed for an IP Address. 0 = unlimited'),
	//'archive_lister'			=> array('default' => false, 'title' => __('Archive lister','wp-filebase'), 'type' => 'checkbox', 'desc' => __('Uploaded files are scanned for archives','wp-filebase')),
	//'enable_ratings'			=> array('default' => false, 'title' => __('Ratings'), 'type' => 'checkbox', 'desc' => ''),
	)

	);
}

}


/**
 * This is currently used for IDE completion only
 *
 * Class WPFB_Options
 */
 class WPFB_Options {
 var $upload_path;
 var $thumbnail_size;
 var $thumbnail_path;
 var $base_auto_thumb;
 var $fext_blacklist;
 var $attach_pos;
 var $attach_loop;
 var $auto_attach_files;
 var $filelist_sorting;
 var $filelist_sorting_dir;
 var $filelist_num;
 var $file_date_format;
 var $bitrate_unregistered;
 var $bitrate_registered;
 var $traffic_day;
 var $traffic_month;
 var $traffic_exceeded_msg;
 var $file_offline_msg;
 var $daily_user_limits;
 var $daily_limit_subscriber;
 var $daily_limit_contributor;
 var $daily_limit_author;
 var $daily_limit_editor;
 var $daily_limit_exceeded_msg;
 var $file_page_proxy;
 var $file_page_countdown;
 var $file_page_comments;
 var $file_page_url_slug;
 var $file_page_url_wfront;
 var $disable_permalinks;
 var $download_base;
 var $file_browser_post_id;
 var $file_browser_cat_sort_by;
 var $file_browser_cat_sort_dir;
 var $file_browser_file_sort_by;
 var $file_browser_file_sort_dir;
 var $file_browser_fbc;
 var $small_icon_size;
 var $cat_drop_down;
 var $force_download;
 var $range_download;
 var $hide_links;
 var $ignore_admin_dls;
 var $hide_inaccessible;
 var $list_inaccessible;
 var $inaccessible_msg;
 var $inaccessible_redirect;
 var $cat_inaccessible_msg;
 var $login_redirect_src;
 var $http_nocache;
 var $parse_tags_rss;
 var $allow_srv_script_upload;
 var $protect_upload_path;
 var $private_files;
 var $frontend_upload;
 var $accept_empty_referers;
 var $allowed_referers;
 var $dl_destroy_session;
 var $decimal_size_format;
 var $admin_bar;
 var $cron_sync;
 var $remove_missing_files;
 var $upload_notifications;
 var $search_integration;
 var $search_result_tpl;
 var $disable_id3;
 var $search_id3;
 var $use_path_tags;
 var $no_name_formatting;
 var $ghostscript_path;
 var $pdf_thumbnails;
 var $pdf_extract_title;
 var $pdf_title_regex;
 var $late_script_loading;
 var $default_author;
 var $default_roles;
 var $default_cat;
 var $languages;
 var $platforms;
 var $licenses;
 var $requirements;
 var $default_direct_linking;
 var $custom_fields;
 var $perm_upload_files;
 var $perm_edit_file_details;
 var $perm_delete_files;
 var $perm_create_cat;
 var $perm_delete_cat;
 var $perm_frontend_upload;
 var $perm_manage_templates;
 var $perm_manage_rsyncs;
 var $perm_manage_forms;
 var $template_file;
 var $template_cat;
 var $dlclick_js;
 var $disable_css;

	 var $file_page_gen_content;

 var $category_substitute;
 private $widget; // placeholder

 var $tag_ver;
 var $version;
 var $template_file_parsed;

 var $fake_md5;

 var $upload_ntf_tpl;

	 /**
	  * @var Relative to WP_CONTENT
	  */
	 var $folder_icon;

	 var $file_context_menu;

 static function Load()
 {
 $options = get_option(WPFB_OPT_NAME);
 foreach($options as $k => $v)
 	WPFB_Settings::${$k} = $v;
 }

 static function Save()
 {
 $option_names = array_keys(get_class_vars(__CLASS__));
 update_option(WPFB_OPT_NAME, array_combine($option_names, array_map(array(__CLASS__, 'Get'), $option_names)));
 }

 static function Get($name) { return WPFB_Settings::${$name}; }
 }

 