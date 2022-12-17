=== WP-Filebase Download Manager ===
Contributors: fabifott
Tags: filebase, filemanager, file, files, manager, upload, download, downloads, downloadmanager, images, pdf, widget, filelist, list, thumbnails, thumbnail, attachment, attachments, category, categories, media, template, ftp, http, mp3, id3
Requires at least: 3.1
Tested up to: 4.8.1
Stable tag: 3.4.32
Demo link: http://demo.wpfilebase.com/


Adds a powerful download manager including file categories, downloads counter, widgets, sorted file lists and more to your WordPress blog.

== Description ==

WP-Filebase is an advanced file download manager for WordPress.
It keeps Files structured in Categories, offers a Template System to create sortable, paginated File Lists and can sideload Files from other websites.
The Plugin is made for easy management of many Files and consistent output using Templates.

For feature list and documentation see [https://wpfilebase.com/](https://wpfilebase.com/).

Support is available at [https://wpfilebase.com/premium-support/](https://wpfilebase.com/premium-support/).

== Installation ==

The usual way:
1. Upload the `wp-filebase-pro` folder with all it's files to `wp-content/plugins`
2. Activate the Plugin

If you get an error message saying that the upload directory is not writable create the directory `/wp-content/uploads/filebase` and make it writable (FTP command: `CHMOD 777 wp-content/uploads/filebase`) for the webserver.

If you run nginx, add this to your config file to prevent direct file access:
`
location /wp-content/uploads/filebase {
	deny all;
	return 403;
}
`
With Apache, WP-Filebase adds this rule automatically to the `.htaccess` files. You have to manually protect this path on any other web servers (IIS, ...) .

Read more in [WP-Filebase documentation](https://wpfilebase.com/documentation/setup/).

== Frequently Asked Questions ==

= How can do I get the AJAX tree view like on https://fabi.me/downloads/ ? =

This feature is called File Browser. Go to WP-Filebase settings and click on the tab 'File Browser'. There you can select a post or page where the tree view shoud appear.

= How do I insert a file list into a post?  =

In the post editor click on the *WP-Filebase* button. In the appearing box click on *File list*, then select a category. Optionally you can select a custom template.

= How do I list a categories, sub categories and files?  =

To list all categories and files on your blog, create an empty page (e.g named *Downloads*). Then goto *WP-Filebase Settings* and select it in the post browser for the option *Post ID of the file browser*.
Now a file browser should be appended to the content of the page.

= How do I add files with FTP? =

Upload all files you want to add to the WP-Filebase upload directory (default is `wp-content/uploads/filebase`) with your FTP client. Then goto WP-Admin -> Tools -> WP-Filebase and click *Sync Filebase*. All your uploaded files are added to the database now. Categories are created automatically if files are in sub folders.

= How do I customize the appearance of filelists and attached files? =

You can change the HTML template under WP-Filebase -> Settings. To edit the stylesheet goto WP-Admin -> Tools -> WP-Filebase and click *Edit Stylesheet*.
Since Version 0.1.2.0 you can create your custom templates for individual file lists. You can manage the templates under WP-Admin -> Tools -> WP-Filebase -> Manage templates. When adding a tag to a post/page you can now select the template.

= How can I use custom file type/extension icons? =

WP-Filebase uses WordPress' default file type icons in `wp-includes/images/crystal` for files without a thumbnail. To use custom icons copy the icon files in PNG format named like `pdf.png` or `audio.png` to `wp-content/images/fileicons` (you have to create that folder first).

= What to do when downloading files does not work? =

Goto WP-Filebase Settings and disable Permalinks under "Download". Try to disable other plugins. Disable WP_CACHE. Enable WP_DEBUG to get more info about possible errors.

== Screenshots ==
1. The form to upload files
2. AJAX file tree view
3. Example of an embedded download box with the default template
4. The Editor Button to insert tags for filelists and download urls
5. The Editor Plugin to create shortcodes for files, categories and lists
6. The WP-Filebase Widgets


== Changelog ==

= 3.4.32 =
* New feature: Custom Fields select options (add custom field like `Type|type|[draft,sample,release]`)
* New CloudSync option `Keep files locally`
* New CloudSync option `Disabled`
* New CloudSync action `Duplicate`
* FTP sync now always tries SSL connection first, warns on insecure connection
* File picker: Moved the upload form below file list
* Removed link from `%file_category%`
* New template variable `%file_category_link%`
* Disable transient cache during rescan fixes progress display
* Fixed settings tab links
* Fixed CSS url issue (removed protocol)
* Fixed Upload `Error -200: HTTP Error`
* Fixed PHP 5.6 compat issue

= 3.4.30 =
* Changes to posts of post type `wpfb_filepage` now sync back to Files
* "+ Add Category" now submits on enter/return
* Fixed filebrowser issues with Divi Builder
* `file_hash_sha256` now in template editor
* New option `List inaccessible categories`
* Files can now listed by Cloud Sync
* You can clear logs now
* New filter `wpfilebase_tpl_var_{$tpl_var}_override` for template variables
* List any file pages if `Hide inaccessible files` is disabled

= 3.4.29 =
* Fixed file upload bug on windows servers (dont remove slashes on `$_FILES`)
* Added sha-256 file hash
* Renamed `Search ID3 Tags` to `Deep file search` and moved to `Common` tab

= 3.4.28 =
* Added new option `Link to file list` to category widget
* New default file and file page template
* Fixed encoding issue `ERR_CONTENT_DECODING_FAILED` breaking rescan and sync
* Changed file size formatting: 101 kB is now 0.1 MB
* Admin menu: changed `Embed Templates` to `Templates`, `Embeddable Forms` to `Forms`

= 3.4.27 =
* Category dropdown fix

= 3.4.25 =
* Fixed several small bugs
* Better error reporting

= 3.4.24 =
* Removed data-table template, use [extension](https://wpfilebase.com/extend/wpfb-datatables-2/) instead
* Fixed a search performance issue (adding index in file table for `LEFT JOIN`)
* Added instructions for upload path protection on NGINX
* Fixed permission issue with file pages
* Fixed permission issue when moving a category to another
* Removed some code obfuscation to prevent Wordfence false positive

= 3.4.23 =
* Fixed inline upload permission issue
* Fixed drag&drop issues
* Fixed a memory leak when generating thumbnails
* Fixed XSS vulnerability

= 3.4.22 =
* Rename field now visible when adding files
* Search widget now has placeholder
* %file_tags% now generates a list of tags with links
* Disable file pages with constant `WPFILEBASE_DISABLE_FILE_PAGES`
* Using Imagick for bmp thumbnails
* Prevent reporting PHP strict warnings and notices
* Fixed permissions issue for guests in `GetPermissionWhere`
* Fixed multiple uploaders on single page
* Fixed cloud sync caching bug
* Fixed FacetWP support bug

= 3.4.21 =
* Renamed WP-Filebase dashboard menu entry to `Dashboard`
* Developers: new filter `wpfilebase_ajax_public_actions`
* Auto-delete category ZIP files from `.tmp` folder
* Removed trailing `.0` in file size format for <1000 B
* Fixed incompatibility with the Divi-Builder plugin

= 3.4.19 =
* Fixed file browser category and file movement (drag&drop)
* Fixed upload widget permission issue
* Now capturing fatal PHP errors in the logs

= 3.4.18 =
* Improved handling of remote URLs of cloud files
* WP-Filebase Pro now auto-activates on domain name change (if License slots are available)

= 3.4.17 =
* New feature: change owner of file
* New template variable `%file_url_no_preview%`
* Fixed file browser delete button feedback (files no disappear after deletion)
* Fixed missing thumbnails after sync when custom thumbnail path is set
* Fixed CloudSync not scanning files with file preview enabled
* Fixed embedded video template for cloud files with enabled file preview
* Added cloud sync system tests

= 3.4.15 =
* Added support for Easy Digital Downloads integration

= 3.4.14 =
* Added column for deletion handling in Cloud Sync dashboard
* Fixed a front-end upload issue setting the wrong category
* Fixed FacetWP support

= 3.4.13 =
* Fixed FacetWP support: filter files without access permission

= 3.4.11 =
* Fixed cloud sync bug caused the file tree to be flattened
* FacetWP support: filter files without access permission
* Fixed file browser uploading to root category
* Fixed authentication issue for thumbnails

= 3.4.10 =
* Filepages now adopt file tags (e.g. for tag clouds)
* Fixed disable state detection for `exec`
* Verbose RPC test
* Automatic cloud Sync cache flush
* Fixed admin dashboard columns layout
* Removed deprecated reference to global `$user_ID`

= 3.4.9 =
* Prevent file rename for cloud-hosted files
* Fixed cloud sync file URL retrieval
* New template variables `%button_edit%`, `%button_delete%`

= 3.4.8 =
* New file batch action: delete thumbnails
* Improved error handling when generating cloud links
* Fixed WP post attachment images
* Fixed thumbnail creation error handling, https://github.com/f4bsch/WP-Filebase/pull/42
* Fixed cloud sync browser
* Fixed editor plugin menu bar


= 3.4.6 =
* Added document indexing hook for extensions
* Fixed error when editing categories
* Fixed date display in backend file list
* Fixed bug with small thumbnails
* Fixed encoding issue for keywords
* Fixed Cloud Sync browser for WebDav
* Fixed download count when behind a proxy (e.g. Cloudflare)
* Fixed broken thumbnails handling during rescan

= 3.4.4 =
* Fixed jQuery treeview compatibility issue
* Sync: improved thumbnail handling (stop thumbnails from being added as files)

= 3.4.3 =
* New Dashboard
* New upload box -- More responsive, new coloring adapts to admin theme
* Added logging system
* Added GitHub file name format version recognition
* Fills out file display and version automatically
* Fixed cron bug
* Updated image-picker and jquery-deserialize
* Added download URLs to backend file browser
* Generate filepage excerpt template fix
* PPTM indexing
* Fixed ghotscript detection
* Added file page meta values see [TODO]
* New taxonomy `wpfb_file_category` -- This makes categories from WP-Filebase available in the WordPress post infrastructure. You can now add categories to navigation menus or use them in other plugins
* Fixed generation of filepage excerpt to show thumbnails
* Fixed file page publish data in future
* Fixed permissions when uploading and creating a new category from front-end
* Removed file browser warning if not set
* Template variable file_name uses file_name_orignal if set
* Disabled output buffering for NGINX on progress reporting
* Fixed defaults for custom fields
* Combined & minified treeview scripts
* Fixed thumbnail detection
* Fixed AJAX for sites with semi-HTTPS (backend-only)
* Admin colors in file form
* Added better thumbnail preview after upload
* Fixed responsiveness of batch uploader
* Set Default Thumbnail size to 300px
* Fixed error `class getid3_lib not found`
* Changed thumbnail file name pattern: `X._[key].thumb.(jpg|png)` -- This prevents thumbnails from being added as actual files when meta data is lost (on site migration)
* Changed JS registration `jquery-treeview` to `wpfb-treeview` to avoid conflicts
* Fix: Send a 1x1 transparent thumbnail if thumbnail not available
* Filepages and File Categories now appear in Navigation Menus page -- You can add these to your navigation menu to easily link to a file details page. You can also link to file category listing the file pages in that category
* Fixed remote redirect
* Fixed remote file name detection
* Fixed file hit counter
* New template variable ``%is_mobile%`
* Fixed file list sorting bug
* Fixed file browser showing up unexpectedly
* Fixed permission issue in backend file browser

= 3.3.3 =
* DataTables update to 1.10.10
* Fixed backslashes in file data when adding
* Fixed `Could not store rsync meta`
* Template var `%file_small_icon%` added to dropdown menu
* Cloud Sync fixes

= 3.3.2 =
* Fixed AJAX calls
* Thumbnails not served through direct plugins script


= 3.3.1 =
* FileBrowser: new option `Inline Add` to toggle the display of Add File/Category links
* WordPress 4.4 compatibility
* Changed textdomain from 'wpfb' to 'wp-filebase' for language pack compatibility
* Fixed File Browser Drag&Drop for newly uploaded files
* Moved custom cform7 elements above submit button
* Added cform7 edit link
* Fixed file name detection on remote upload
* Fixed XXS URL redirection vulnerability found by [Cybersecurity Works](http://www.cybersecurityworks.com)
* Fix: Load getid3_lib if necessary
* Added Extension Update API caching
* PHP 7 compatibility: `mysql_close` only called if exists
* Async Uploader: Added error message on invalid server response after upload
* Prevent direct script access for Editor Plugin, Post Browser and AJAX

= 3.3.0 =
* New Feature: Upload notification email template
* Added delete buttons to backend file browser 
* Show icons in file/category selector tree
* PHP 7 constructor compatibility (and WP 4.3.0)
* Delayed file scanning in sync process for faster syncing
* Better sync progress reporting
* Improved sync performance, reduced server load during sync
* Improved PDF indexing (stability)
* Rescan process can be resumed
* Rescan Tool now rescans cloud files
* Removed FLV player, replaced with HTML5 video player
* Added compatibility for latest CF7
* Fix: More robust file name handling with special characters
* Fix: Also send upload notifications for offline files
* Fixed individual file force download option
* Fix: Attachment uploader disables with front-end upload setting
* Fix: hide attach uploader if front-end uploads disabled
* Fixed sideload issue
* File browser: only show add category if user has permission

= 3.2.12 =
* Run a File Sync to fix category file counter bug (categories no opening in file browser)
* Inherit category upload permissions
* Added number of files colulmn in cloud sync backend
* Deleting a category removes the folder
* Password page title fix
* New list template hader/footer var: `%search_term%`
* Made treeview drag&drop IE compatible
* Rescan looks for thumbnails with same basename if `Auto-detect thumbnails` is enabled
* Fixed fatal error in editor plugin with coflicting plugins
* Updated french translation by Marco Siviero

= 3.2.11 =
* Added search indexing of Powerpoint and Excel files (PPTX, XLSX)
* Disable expiration time of thumbnail browser caching
* Fixed extensions install screen on multisite
* Fixed pagination for lists
* Fixed MP3 cover image extraction
* Fixed PDF indexing (removing binary data)

= 3.2.10 =
* Fixed editor plugin
* Fixed extension install page
* Made CSS path protocol relative (http/https)

= 3.2.08 =
* Added extension system
* Google Drive and OneDrive Sync are now extensions (need to be installed after update!)
* Back-end filebrowser: hide edit button if not permitted
* Increased multisite compatibility
* New template variable `%file_user_can_edit%`
* Fix: make sure that publish date of file pages is not in the future
* Fixed mysql table structure update causing `Unknown column` errors
* Fixed some treeview drag and drop issues
* Fixed template when adding category from filebrowser
* Added support for remote urls for local files with `file://` scheme
* Only allow WP-Filebase Pro or the free version enabled at the same time

= 3.2.06 =
* New feature: Sort files by multiple fields in shortcode argument (seperate by ,)
* Camera RAW .CR2 thumbnails
* Automatically scroll to attachment uploader when dragging a file over a post
* Files are moved when changing a Cloud Sync's root category
* Cloud Sync: Prevent chaning remote path after first sync
* Updated DataTables to 1.10.4
* Updated DataTables column filter to 1.5.6
* During Cron Sync, unset current user if logged in
* Using `plugin_dir_url` function for better compatibility (from GitHub)
* Fixed broken thumbnails when chaning category of a remote or cloud file
* Fix: Files were not added to restricted categories during sync
* Fixed pagination in back-end category list
* Fix: Hide attach uploader even if JS is broken
* Fixed Drag&Drop uploader issue when uploading file updates

= 3.2.05 =
* Added automatic updates for extensions
* Improved mobile responsive appearence on front and back-end
* Fixed embedded forms warning
* Fixed Drag&Drop uploader issue when uploading file updates

= 3.2.04 =
* Fixed batch uploader JS bug on front end (getUserSetting undefined)
* New option for embeddable forms to disable seucirty checks
* Uploads within the filebrowser will apply the filebrowser template
* New remote sync option: disable deletion of files
* Fixed file list pagination links
* FTP Sync: fixed space escaping
* Fixed inline category creating (not double)

= 3.2.03 =
* Added Front-end Drag & Drop toggle to Admin Bar
* Made some user options global on Site Networks
* Improved security (thanks to Venkateswara Reddy)
* Fixed conflcit with WP SEO where jQuery was not loading
* Late front-end script loading in footer increases compatibility with other plugins
* Fixed front-end treeview Add File
* Fixed Drag & Drop bug with Firefox
* Improved stability of JS code on failures and conflicts

= 3.2.02 =
* Added Cloud Sync service overlay to file icons in backend
* In Back-End filebrowser prevent click event when clicking `Edit`
* General GUI fixes and mobile optimization
* Fixed critical warning in backend
* Fixed bug that prevented updating file details
* Fixed Cloud Sync management bug
* Fixed file browser on mobile devices
* Fixed Editor Plugin File and Category Selector

= 3.2.01 =
* New Feature: Bulk actions
* New Feature: Microsoft OneDrive Support
* New Feature: Users are notified if granted access
* New Feature: Treeview: Drag & Drop Files, Move Categories and Files by dragging
* Inline Add Category & Add File in File Browser
* Inline Add Category in Select Drop Down
* New Feature: Drag&Drop Files directly to post content to attach
* New Option: Category Substitute to change the Category a new meaning
* Added Logos for Cloud Sync
* Name Change: `Remote Sync` to `Cloud Sync`
* Post type field is removed from search form
* Added file table views: Local & Cloud
* New feature: custom fields  %file_info/...%
* `%'` chars are escaped in download urls (see Github issue)
* Fixed performance issue when chaning a file's category
* Improved debug output during sync
* Fixed PDF thumbnail generation using Imagick (catching exceptions)
* Improved Performance of Google Drive Sync
* Fixed Google Drive file versioning
* Fixed admin redirection URL escaping
* Fixed `preg_replace(): The /e modifier is deprecated`
* Moved Edit Stylesheet menu entry to Manage Templates
* Disabled max exec. time while moving folders
* Changed the default category icon

= 3.1.16 =
* Make files private after upload
* New Option Category Substitute                  
* Custom fields filled by ID3
* Empty Custom fields not change to default value when editing a file

= 3.1.15 =
* New Option: Disable WP-Filebse Stylesheet (wpfilebase.css)
* Users can upload to sub-categories even if they don't have upload permissions for parent categories
* Fixed error `Unable to insert item into DB! Unknown column 'cat_upload_permissions' in 'field list'`
* Fixed a bug in Dropbox sync when curl is not available

= 3.1.14 =
* New Feature: PSD thumbnails
* New Feature: File browser sorting
* New Feature: Preset Sync
* New Feature: File URL: Prepend asterisk (*) to linktext to open in new tab
* New Feature: Embeddable Forms: Extra Form fields (from Cforms) are appended to notification emails
* Chinese translation by [Darlexlin](http://darlexlin.cn/)
* Added styling for extended field of embedded forms
* Added Google Universal Analytics compatibility
* CloudSync: DropboxSync, S3: files are deleted from cloud if locally removed
* New file template var %cat_id%
* Added alt tags for cat icons
* Increased security for RPC-Calls
* New batch uploader field: File Display Name
* New function: reset all hit counters
* New File Browser code
* Embeddable Forms: validate destination category
* Unlimited number of file permissions
* PDF indexing: count pages before index to improve stability
* Fix: Suppressing deprecation errors on AJAX requests
* Fixed WebDav issues
* Fixed output suppression during Ajax requests
* Fixed: keep thumbnail during file update
* Fixed permission control for roles with names shorter than 4 (or mysql ft_min_word_len)
* NGG_DISABLE_RESOURCE_MANAGER to disable the NextGen Gallery resource manager
* Fixed cannot redeclar class S3 and Google_* 
* FTPSync: fixed sync of folder containing spaces
* FTPSync: fixed file urls
* Updated DropPHP to 1.7
* Updated getId3 1.10.0

= 3.1.13 =
* New Feature: Embeddable Forms: Batch Uploader
* New Feature: Embeddable Forms: Custom Upload Confirmation template
* New Feature: Embeddable Forms: Advanced Custom Elements with Contact Form 7 Plugin
* New Feature: Google Drive Sync
* New Feature: Added Shortcode `catzipurl`
* New Feature: XML Sitemap
* GUI adjustments to fit latest WordPress version
* Fixed Security Issue in `fileMD5` (thanks to [Samir Megueddem](http://www.synacktiv.com))
* Fixed image urls in custom CSS stylesheet
* Improved Sideload
* Fixed some permission issue for edit permissions and editor plugin
* BatchSync: Sync is only resumed once

= 3.1.12 =
* New RemoteSync Service: WebDAV
* Enhanced Search Functions: added dash (-) operator to exclude words, added wildcard (*)
* Fixed user permissions selector for users with @ in login name
* `File Page Permalink with Front Base` defaults to enabled
* Fixed file download permission issue
* Fixed usage of `wp_check_filetype`
* Fixed general permission bug, where user roles were not loaded (added get_role_caps())
* Fixed remote sync error handling
* Fixed typos and update language files

= 3.1.11 =
* New Option `File Page Permalink with Front Base`
* New Option `Empty Message` for File Browser
* Added Delete, Edit & Approve Link to Upload Notification 
* If user cannot access file page, they are redirected to login
* Updated DropPHP Dropbox Client to 1.6
* Custom static CSS file improving site performance
* Sync: categories are removed if folder has been deleted and `Remove missing Files` is enabled
* Removed test cookie
* Fixed file edit permissions
* Fixed small icon with for files & categories
* File Browser: Safer JS loading of treeview plugins sorts out compatibility issues with other plugins (like NextGEN Gallery)
* Fixed Tools visibility
* Fixed direct linking protection: redirection to file page
* Fixed batch uploader
* FTPSync fixed url mapping
* Fixed error handling when adding remote Sync files
* Fixed escaping of special characters in PDF files
* Fixed minor bugs and typos
* Disabled extracting version no. from  filenames like 01.01.01-01.02.03

= 3.1.10 =
* Fixed Sync Error
* Fixed S3 sync warning if open_basedir is on
* Fixed some strict standard warnings

= 3.1.09 =
* Added Sync Option `Fake MD5` to improve performance if MD5 not required
* Added Rescan to Files Bulk actions
* Added Custom Fields to File List View
* Added Settings for FTP Sync: Port & SSL
* Changed Category List Widget: If Root Category is Empty, all childs are displayed
* Improved CSS & thumbnail loading time
* Improved Batch Sync performance
* Disabled Visual Editor for File Description in Editor Plugin
* Fixed blank Editor Plugin screen occuring with some 3-rd party plugins
* Fixed sync error handling
* Fixed ID3 tag detection for files with large meta data
* Fixed `mysql_close()` during download

= 3.1.08 =
* New S3 Option `Virtual-hosted-style URIs`
* Fixed feedback page when creating a category with the widget
* Fixed notification bug
* Fixed `Unable to insert item into DB!` error
* RPC improvements (experimental!)

= 3.1.07 =
* New File List Table in Dashboard
* Custom Folder Icons for File Browser
* Changed behavior of `Upload Notifications`
* Upload Notifications for all users
* Remote Sync: added support for Amazon CloudFront
* Added imagick PDF thumbnails support
* Improved PDF indexing
* Fixed `MySQL server has gone away`
* Fixed Ghostscript
* Added mime type `application/x-windows-gadget`
* Fixed ´GPL Ghostscript´ named PDF files
* Fixed File Widget: also display secondary category files
* Fixed File Browser click handling
* Editor Plugin remembers extendend/simple form 
* Updated Amazon S3 client to 0.5.0-dev

= 3.1.06 =
* Fixed batch uploader
* Fixed warning when keyword search for file pages is enabled
* Added default values for custom fields
* Added `wpfilebase_file_downloaded` hook for download logging
* Fixed HTML escaping for some file template vars
* Updated jQuery treeview plugin. There were some CSS changes, please check your File Browser!

= 3.1.05 =
* New Feature: 2-Way-Sync: Files are Uploaded to Dropbox, S3 and FTP
* New Feature: Private File Browser
* New Option `File Browser expanded` in File Browser Settings and Editor Plugin
* New fresh looking default File & Category templates. [HTML/CSS for upgrading](https://wpfilebase.com/how-tos/file-category-template-v2/)
* New Option for File Pages `Content Keywords`
* Improved Fix File Pages tool
* Resetting settings to default will not reset the default templates anymore
* Resetting templates to default will also reset default templates
* Small Icon Size can be set to 0 to display full size icons
* Further memory optimizations
* New category template variable `%cat_has_icon%`
* Update Changelog is now available when updating in the Dashboard
* Better GhostScript detection
* Updated DataTables to 1.9.4
* JS code of embedded uploader is minified
* Removed line breaks from search form HTML to prevent auto-<br>-tags
* Fixed monthly/daily traffic limit
* Fixed download range header handling (thanks to mrogaski)
* Fixed Batch uploader
* Fixed HTML comments in templates
* Fixed URL issues when using HTTPS
* Fixed file lists showing only secondary categories
* Minified DataTables init JS to prevent auto <p>

= 3.1.04 =
* New Feature: Drag&Drop Batch Uploader with Upload Presets
* Added AJAX user search for Role/User Selector
* New Feature: Shortcode parameter `search` to filter files in lists
* New Tool: Fix File Pages
* Added MP4 mime type
* Small Icon Size can be set to 0 to display full size icons
* Improved Sync performance when free memory is low
* Sync: missing thumbnails are removed from database
* Sync recognizes moved files so meta data is retained and only the path will be updated
* Updated SK translation by Peter Šuranský
* Memory optimizations
* Fixed embedded form category select
* Fixed embedded form upload notifications
* Fixed file size bug for big files
* Fixed URL issues when using HTTPS
* Bulk Actions NOT included yet, planned for next update. Sorry for the delay!

= 3.1.02 =
* New Feature: Category ZIP Packages (a ZIP containing all files in the category)
* New template variable for categories: `%cat_zip_url%`
* New Option `Category ZIP Files` under Download Settings
* File Page settings arranged in new tab
* New Option `File Page URL slug` to change permalink base of File Pages
* New Option `Comments on File Page` to enabled file comments
* Sync: Added support for external cron service
* Increased stability of sync
* Fixed download URL on file pages
* RemoteSync: expired Dropbox links are automatically refreshed
* RemoteSync: Added Link Lifetime option for S3 sync
* Backend: Fixed not all files beeing visible for Admins
* Fixed minor bugs

= 3.1.01 =
* New custom post type `wpfb_filepage` for file pages
* Extended Embeddable Forms
* New Feature: Added individual limits for custom roles
* RemoteSync: files deleted on remote service are also removed locally
* New Default Template: `filepage_excerpt` used for file pages in search results
* Custom language files dir can be set with PHP constant WPFB_LANG_DIR in wp-config.php
* Upload permissions are inherited
* New Option 'Use fpassthru' to avoid invalid download data on some servers
* New GUI tab for File Page Templates
* Removed Option `Destroy session when downloading`, this will now work in a different way
* Added `.svn, .git` directories to sync ignore list
* Fixed flash uploader behavior when uploading file updates
* Fixed embeddable forms GUI
* Fixed file renaming on upload
* Fixed GUI for Embeddable Forms
* Fixed quote escaping in template IF expressions

= 3.0.14 =
* New Option: Default File Direct Linking
* DataTables are now sorted according to the Shortcode argument `sort`
* Fixed Extended/Simple Form toggle
* Improved stability of Sync algorithm
* Admins can download offline files
* Permission Selector: Users are grouped by Roles
* Added complete un-install (Button located at WP-Filebase dashboard bottom)
* Custom AJAX loader CSS class for AJAX lists
* Fixed download URLs for file names containing `'`
* Remote Sync: File URLs are refreshed if settings changed
* Remote Sync: Added option to disable File scan for faster synchronisation
* Amazon S3: New Option to strip AWSAccessKeyId query var from URLs
* Improved DataTable support: Lists are sorted by shortcode argument
* Files added with multi uploader are added directly after upload finished
* Fixed Remote Sync Path browser permissions
* File Form: Licenses are hidden if none specified in Settings
* New Option: Search Result Template

= 3.0.13 =
* New Feature: AJAX Lists (edit a List Template to enable AJAX)
* New Feature: Upload Notification, all Admins are notified by email when a file is added from the back-end (enable at Settings -> Misc -> Upload Notifications)
* Added pagenav checkbox to editor plugin
* Fixed Dropbox Sync Issue when cURL is installed
* Added Visual Editor for File Description
* Fixed thumnail upload issue
* Fixed minor bugs
* Fixed context menu on DataTables
* Added ID display to back-end Category list
* Shortcodes are parsed in template preview
* Removed deprecated file list widget control
* Decreased time for cache revalidation when downloading a File

= 3.0.12 =
* Made code adjustments for WordPress 3.5 compatibility
* New Option `Small Icon Size` in File Browser settings to adjust the size of icons and thumbnails
* New Tool `Reset Permissions`
* Improved compatibility with custom Role Plugins
* Some GUI changes
* Added warning on Embedded Forms GUI if front end upload is disabled
* Fixed 'Cheating uh?' bug when using the category seachr form after editing (thanks to David Bell)
* Fixed secondary category query causing files to appear in root folder
* Removed call wp_create_thumbnail which is deprecated since WP 3.5

= 3.0.11 =
* Added Embeddable Forms Email Notification
* Added Embeddable Forms File Approval
* Better support for big files (>1.5GB)
* Widget File Search Form now looks like the default search form
* Add New Button is now hidden when using the Drag&Drop Uploader in Embeddable Forms
* Added length limit for template variables: `%file_display_name:20%` limits the name to 20 characters
* When deleting a RemoteSync the corresponding file meta data is removed
* Fixed RemoteSync crash when a category exists with the same name as a file
* SSL Host/Peer Verification disabled for Amazon S3 sync, that caused trouble
* Fixed pagenav shortcode parameter, thanks to yuanl
* Fixed file size limit in Drag&Drop uploader causing trouble
* Fixed possible fatal error on PDF indexing (mb_detect_encoding)
* Fixed CSS Editor Bug
* Fixed bug in list sorting

= 3.0.10 =
* Added Amazon S3 support (see Remote Sync)
* Fixed file counter for secondary categories (run a sync to fix existing categories!)
* New template variable `%file_sec_cats%`
* Fixed AJAX tree not showing
* Optimized Sync code for less memory consumption
* Front end forms are submitted automatically after upload
* New tool: Rescan Files
* Updated Brazillian Portuguese translation by Felipe Cavalcanti
* New Option Remove Missing Files
* Missing files will automatically set offline druing sync
* Fixed Item::GetParents() stuck in endless loop
* Fixed non-expandable categories in file browser containing only secondary file links
* Fixed `Call to undefined function unzip_file()` error that happend when uploading a DOCX or ODT file from front end
 
= 3.0.08 =
* New feature: file passwords (see File Form to set the password)
* New option for FtpSync: Files can be mapped to a HTTP/FTP URI
* Fixed Drag & Drop Flash Uploader
* Fixed Front End upload permissions
* Fixed Admin Bar JavaScript loading
* Fixed last Cron Sync time display
* Decreased Dropbox download buffer size

= 3.0.07 =
* First Pro Version

== Upgrade Notice ==

= 0.2.0 =
PHP 5 or later required! This is a big upgrade with lots of new features. You have to convert old content tags to new shortcodes. Go to WP-Filebase management page and you should see a yellow box with the converter notice (backup the Database before!). And sync the filebase after that!

== Documentation ==
[WP-Filebase Documentation](https://wpfilebase.com/documentation/)

== Translation ==
If you want to translate WP-Filebase in your language, open `wp-filebase/languages/template.po` with [Poedit](http://www.poedit.net/download.php) and save as `wpfb-xx_YY.po` (`xx` is your language code, `YY` your country). Poedit will create the file `wpfb-xx_YY.mo`. Put this file in `wp-filebase/languages` and share it if you like (attach it to an email or post it on my blog).

== Plugin Developers ==
WP-Filebase currently offers the action `wpfilebase_sync`. This will run a fast filebase sync that adds new files.

The hook `wpfilebase_file_downloaded` with file_id as parameter can be used for download logging.

[WP-Filebase on GitHub](https://github.com/f4bsch/WP-Filebase)


== WP-Filebase Pro ==
[WP-Filebase Pro](https://wpfilebase.com/) is the commercial version of WP-Filebase with an extended range of functions. It supports secondary categories, extended permissions, embedded upload forms. Furthermore it can generate PDF thumbnails, sync with Dropbox or FTP and includes an improved file sync algorithm.

== Traffic Limiter ==
If you only want to limit traffic or bandwidth of media files you should take a look at my [Traffic Limiter Plugin](http://wordpress.org/extend/plugins/traffic-limiter/ "Traffic Limiter").

