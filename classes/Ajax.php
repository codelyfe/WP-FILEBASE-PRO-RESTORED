<?php

class WPFB_Ajax
{

    private static function dispatchAction(&$actions)
    {
        $args = stripslashes_deep($_REQUEST);

        if (empty($args['wpfb_action']) || empty($actions[$args['wpfb_action']])) {
            die('-1');
        }

        $func = is_array($actions[$args['wpfb_action']]) ? $actions[$args['wpfb_action']] : array(__CLASS__, $actions[$args['wpfb_action']]);

        if (!is_callable($func)) {
            WPFB_Core::LogMsg("AJAX error: not callable " . json_encode($func));
            die('-1');
        }

        // will be overwritten by wp_send_json. use text/html for error messages for errors that might stop execution
        // otherwise jQuery will try to parse the JSON, resulting a parse error
        @header('Content-Type: text/html; charset=' . get_option('blog_charset'));

        global $wpdb;
        // we expect that the executed code will properly catch any errors
        // error messages should be send using JSON
        $wpdb->suppress_errors(true);

        try {
            call_user_func($func, $args);
        } catch (Exception $ex) {
            WPFB_Core::LogMsg("AJAX exception: " . $ex->getMessage());
            die('-1');
        }
        exit;
    }

    public static function AdminRequest()
    {
        // case 'attach-file':
        $public_actions = array(
            'tree' => 'actionTree',
            'list' => 'actionList',
            'upload' => 'upload',
            'upload-chunked' => 'uploadChunked',
            'sideload' => 'sideload',
            'delete' => 'actionDelete',
            'tpl-sample' => 'tplSample',
            'fileinfo' => 'fileInfo',
            'catinfo' => 'catInfo',
            'change-category' => 'actionChangeCategory',
            'new-cat' => 'actionNewCat',
            'toggle-context-menu' => 'actionToggleContextMenu',
            'set-user-setting' => 'setUserSetting',
            'get-user-setting' => 'getUserSetting',
            'attach-file' => 'actionAttachFile',
            'tag_autocomplete' => 'tagAutocomplete',
            'usersearch' => 'usersearch',
            'postbrowser-main' => 'postBrowserMain',
            'postbrowser' => 'postBrowser',
            'parse-filename' => 'parseFilename',
            'rsync-browser' => 'rsyncBrowser',
            //'postbrowser' => '',
            //rsync-browser
        );
        self::dispatchAction($public_actions);
    }

    public static function PublicRequest()
    {
        $public_actions = array(
            'tree' => 'actionTree',
            'list' => 'actionList',
            'upload' => 'upload',
            'parse-filename' => 'parseFilename'
        );

        $public_actions = apply_filters('wpfilebase_ajax_public_actions', $public_actions);

        self::dispatchAction($public_actions);
    }

    private static function actionTree($args, $return = false)
    {
        wpfb_loadclass('File', 'Category', 'Output');

        // fixed exploit, thanks to Miroslav Stampar http://unconciousmind.blogspot.com/
        $root_id = (empty($args['root']) || $args['root'] == 'source') ? 0 : (is_numeric($args['root']) ? intval($args['root']) : intval(substr(strrchr($args['root'], '-'), 1)));
        $parent_id = ($root_id == 0 && isset($args['base'])) ? intval($args['base']) : $root_id;

        $args = wp_parse_args($args, array(
            'sort' => array(),
                        'private' => false,             'onselect' => null,
            'idp' => null,
            'tpl' => null,
            'inline_add' => true,
        ));

        isset($args['cats_only']) && $args['cats_only'] === 'false' && $args['cats_only'] = false;
        isset($args['exclude_attached']) && $args['exclude_attached'] === 'false' && $args['exclude_attached'] = false;
                $args['private'] === 'false' && $args['private'] = false;         $items = WPFB_Output::GetTreeItems($parent_id, $args);

        if ($return)
            return $items;

        wp_send_json($items);
    }

    private static function actionList($args)
    {
        wpfb_loadclass('ListTpl', 'File', 'Category', 'Output');
        if (empty($args['tpl']) || is_null($tpl = WPFB_ListTpl::Get($args['tpl'])))
            die('-1');

        $cats = (empty($args['cats']) || $args['cats'] == -1) ? ($args['cat_grouping'] ? WPFB_Category::GetCats() : null) : array_filter(array_map(array('WPFB_Category', 'GetCat'), explode(',', $args['cats'])));

        $content = '';

        $tpl->GenerateList($content, $cats, $args);
        header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        echo do_shortcode($content);
        exit;
    }

    /**
     *
     * $args['file_id']
     * $args['cat_id']
     *
     * @param type $args
     */
    private static function actionDelete($args)
    {
        wpfb_loadclass('File', 'Category');
        if (isset($args['file_id'])) {
            $file_id = intval($args['file_id']);
            if ($file_id <= 0 || ($file = WPFB_File::GetFile($file_id)) == null || !$file->CurUserCanDelete())
                die('-1');

            $data = (array(
                'id' => $file->GetId(),
                'url' => $file->GetUrl(),
                'path' => $file->GetLocalPathRel()
            ));

            $data['deleted'] = $file->Remove();
            wp_send_json($data);

        } elseif (isset($args['cat_id'])) {
            $cat_id = intval($args['cat_id']);
            if ($cat_id <= 0 || ($cat = WPFB_Category::GetCat($cat_id)) == null || !$cat->CurUserCanEdit())
                die('-1');


            if (!empty($args['get_tree_items'])) {
                $items = self::actionTree($args['get_tree_items'], true);
                $cat->Delete();
                wp_send_json(array('deleted' => 1, 'children' => $items));
            }

            $cat->Delete();
            die('1');
        } else
            die('-1');
    }

    private static function tplSample($args)
    {
        global $current_user;
        if (!current_user_can('edit_posts'))
            die('-1');

        wpfb_loadclass('File', 'Category', 'TplLib', 'Output');

        if (isset($args['tpl']) && empty($args['tpl']))
            exit;

        $cat = new WPFB_Category(array(
            'cat_id' => 0,
            'cat_name' => 'Example Category',
            'cat_description' => 'This is a sample description.',
            'cat_folder' => 'example',
            'cat_num_files' => 0, 'cat_num_files_total' => 0
        ));
        $cat->Lock();

        $file = new WPFB_File(array(
            'file_name' => 'example.pdf',
            'file_display_name' => 'Example Document',
            'file_size' => 1024 * 1024 * 1.5,
            'file_date' => gmdate('Y-m-d H:i:s'),
            'file_hash' => md5(''),
            'file_thumbnail' => 'thumb.png',
            'file_description' => 'This is a sample description.',
            'file_version' => WPFB_VERSION,
            'file_author' => $current_user->display_name,
            'file_hits' => 3,
            'file_added_by' => $current_user->ID
        ));
        $file->Lock();

        if (!empty($args['type']) && $args['type'] == 'cat')
            $item = $cat;
        elseif (!empty($args['type']) && $args['type'] == 'list') {
            wpfb_loadclass('ListTpl');
            $tpl = new WPFB_ListTpl('sample', $args['tpl']);
            echo $tpl->Sample($cat, $file);
            exit;
        } elseif (empty($args['file_id']) || ($item = WPFB_File::GetFile($args['file_id'])) == null || !$file->CurUserCanAccess(true))
            $item = $file;
        else
            die('-1');

        $tpl = empty($args['tpl']) ? null : WPFB_TplLib::Parse($args['tpl']);
        echo do_shortcode($item->GenTpl($tpl, 'ajax'));
    }

    private static function fileInfo($args)
    {
        wpfb_loadclass('File', 'Category');
        if (empty($args['url']) && (empty($args['id']) || !is_numeric($args['id'])))
            die('-1');
        $file = null;

        if (!empty($args['url'])) {
            $url = $args['url'];
            $matches = array();
            if (preg_match('/\?wpfb_dl=([0-9]+)$/', $url, $matches) || preg_match('/#wpfb-file-([0-9]+)$/', $url, $matches)) {
                $file = WPFB_File::GetFile($matches[1]);
            } else {
                $base = trailingslashit(get_option('home')) . trailingslashit(WPFB_Core::$settings->download_base);
                $path = substr($url, strlen($base));
                $path_u = substr(urldecode($url), strlen($base));
                $file = WPFB_File::GetByPath($path);
                if ($file == null)
                    $file = WPFB_File::GetByPath($path_u);
            }
        } else {
            $file = WPFB_File::GetFile((int)$args['id']);
        }

        if ($file != null && $file->CurUserCanAccess(true)) {
            wp_send_json(array(
                'id' => $file->GetId(),
                'url' => $file->GetUrl(),
                'path' => $file->GetLocalPathRel()
            ));
        } else {
            echo '-1';
        }
    }

    private static function catInfo($args)
    {
        wpfb_loadclass('Category', 'Output');
        if (empty($args['id']) || !is_numeric($args['id']))
            die('-1');
        $cat = WPFB_Category::GetCat((int)$args['id']);

        if ($cat != null && $cat->CurUserCanAccess(true)) {
            wp_send_json(array(
                'id' => $cat->GetId(),
                'url' => $cat->GetUrl(),
                'path' => $cat->GetLocalPathRel(),
                'roles' => $cat->GetReadPermissions(),
                'roles_str' => WPFB_Output::RoleNames($cat->GetReadPermissions(), true)
            ));
        } else {
            echo '-1';
        }
    }

    private static function actionChangeCategory($args)
    {
        wpfb_loadclass('File', 'Admin');
        $item = WPFB_Item::GetById($args['id'], $args['type']);
        $cat = WPFB_Category::GetCat($args['new_cat_id']);
        if ($item && $item->CurUserCanEdit() && (!$cat || $cat->CurUserCanAddFiles())) {
            $res = $item->ChangeCategoryOrName($args['new_cat_id']);
            wp_send_json($res);
        } else {
            wp_send_json(array('error' => __("Sorry, you are not allowed to do that.")));
        }
    }

    private static function actionNewCat($args)
    {
        wpfb_loadclass('Category');
        $parent_cat = empty($args['cat_parent']) ? null : WPFB_Category::GetCat($args['cat_parent']);
        if (!WPFB_Core::CurUserCanCreateCat() || ($parent_cat && !$parent_cat->CurUserCanAddFiles()))
            wp_send_json(array('error' => __("Sorry, you are not allowed to do that.")));
        wpfb_loadclass('Admin');
        $result = WPFB_Admin::InsertCategory($args);
        if (isset($result['error']) && $result['error']) {
            wp_send_json(array('error' => $result['error']));
            exit;
        }

        $cat = $result['cat'];
        $fb_args = WPFB_Output::fileBrowserArgs(empty($args['args']) ? array() : $args['args']);
        $filesel = ($fb_args['type'] === 'fileselect');
        $catsel = ($fb_args['type'] === 'catselect');

        $tpl = empty($args['tpl']) ? (empty($args['is_admin']) ? 'filebrowser' : 'filebrowser_admin') : $args['tpl'];

        wp_send_json(array(
            'error' => 0,
            'id' => $cat->GetId(),
            'name' => $cat->GetTitle(),
            'id_str' => $fb_args['idp'] . 'cat-' . $cat->cat_id,
            'url' => $cat->GetUrl(),
            'text' => WPFB_Output::fileBrowserCatItemText($catsel, $filesel, $cat, $fb_args['onselect'], $tpl),
            'classes' => ($filesel || $catsel) ? 'folder' : null
        ));
    }

    private static function usersearch($args)
    {

        if (!WPFB_Core::CheckPermission('upload_files|edit_file_details|delete_files|create_cat|delete_cat|manage_templates|manage_rsyncs') || empty($args['name_startsWith']))
            die('-1');
        $pattern = $args['name_startsWith'] . '*';
        $users = get_users(array('search' => $pattern, 'number' => 15, 'fields' => array('ID', 'user_login', 'display_name')));

        $data = array();
        for ($i = 0; $i < count($users); $i++)
            $data[$i] = array('id' => $users[$i]->ID, 'login' => $users[$i]->user_login, 'name' => $users[$i]->display_name);
        wp_send_json($data);
    }

    private static function tagAutocomplete($args)
    {
        if (empty($args['tag'])) {
            wp_send_json(array());
            exit;
        }

        $tag = $args['tag'];
        $tags = (array)get_option(WPFB_OPT_NAME . '_ftags'); // sorted!
        $props = array();
        if (($n = count($tags)) > 0) {
            $ks = array_keys($tags);
            for ($i = 0; $i < $n; $i++) {
                if (stripos($ks[$i], $tag) === 0) {
                    while ($i < $n && stripos($ks[$i], $tag) === 0) {
                        $props[] = array('t' => $ks[$i], 'n' => $tags[$ks[$i]]);
                        $i++;
                    }
                }
            }
        }
        wp_send_json($props);
    }

    private static function actionAttachFile($args)
    {
        wpfb_loadclass('File');
        if (!current_user_can('upload_files') || empty($args['post_id']) || empty($args['file_id']) || !($file = WPFB_File::GetFile($args['file_id'])))
            die('-1');
        $file->SetPostId($args['post_id']);
        die('1');
    }

    private static function getUserSetting($args)
    {
        if (!WPFB_Core::CheckPermission('upload_files|edit_file_details|delete_files|create_cat|delete_cat|manage_templates|manage_rsyncs') || empty($args['name']))
            die('-1');
        wp_send_json(get_user_option('wpfb_set_' . $args['name']));
    }

    private static function setUserSetting($args)
    {
        if (!WPFB_Core::CheckPermission('upload_files|edit_file_details|delete_files|create_cat|delete_cat|manage_templates|manage_rsyncs') || empty($args['name']))
            die('0');
        echo update_user_option(get_current_user_id(), 'wpfb_set_' . $args['name'], stripslashes($args['value']), true);
        exit;
    }

    private static function actionToggleContextMenu($args)
    {
        //case 'toggle-context-menu':
        if (!current_user_can('upload_files'))
            die('-1');
        WPFB_Core::UpdateOption('file_context_menu', empty(WPFB_Core::$settings->file_context_menu));
        die('1');
    }

    private static function postBrowserMain($args)
    {
        wpfb_loadclass('PostBrowser');
        WPFB_PostBrowser::Main($args);
    }

    private static function postBrowser($args)
    {
        wpfb_loadclass('PostBrowser');
        WPFB_PostBrowser::Ajax($args);
    }

    private static function basename($path)
    {
        $path = trim($path, '/');
        $p = strrpos($path, '/');
        return $p ? substr($path, $p + 1) : $path;
    }

    private static function rsyncBrowser($args)
    {
        if (true  && !WPFB_Core::CheckPermission('manage_rsyncs')) {
            wp_send_json(array(array('id' => '', 'text' => __('Cheatin&#8217; uh?'), 'classes' => '', 'hasChildren' => false)));
            exit;
        }


        wpfb_loadclass('RemoteSync');
        $rsync = WPFB_RemoteSync::GetSync($args['rsync_id']);
        if (empty($rsync))
            exit;
        //CODELYFE-CREATE-FUNCTION_FIX-MAY-BREAK
        //add_filter('wp_die_ajax_handler', create_function('$v', 'return "' . create_function('$msg', 'if(empty($msg)) die(); @header ("HTTP/1.1 200 OK"); wp_send_json(array(array(\'id\' => \'\', \'text\'=> \'<b>ERROR</b>: \'.$msg, \'classes\' => \'empty\', \'hasChildren\' => false )));') . '";'));


        $root_path = (empty($args['root']) || $args['root'] == 'source') ? '/' : $args['root'];
        $onclick = empty($args['onclick']) ? '' : $args['onclick'];
        $dirs_only = !empty($args['dirs_only']) && $args['dirs_only'] !== 'false';

        try {
            $files_and_dirs = $rsync->GetFiles($root_path);
        } catch (Exception $e) {
            wp_send_json(array(array('id' => '', 'text' => '<b>ERROR</b>: ' . $e->getMessage(), 'classes' => 'empty', 'hasChildren' => false)));
            exit;
        }
        $dirs = array();
        $files = array();

        foreach ($files_and_dirs as $f) {
            $item = array(
                'id' => $f->path,
                'text' => ($f->is_dir ? // no basename() here!
                    ('<a href="javascript:' . sprintf($onclick, str_replace('\'', '\\\'', (stripslashes(rawurlencode($f->path))))) . '">' . esc_html(self::basename($f->path)) . '</a>') : esc_html(self::basename($f->path))
                ),
                'classes' => $f->is_dir ? 'folder' : 'file',
                'hasChildren' => ($f->is_dir)
            );

            if ($f->is_dir)
                $dirs[] = $item;
            elseif (!$dirs_only)
                $files[] = $item;
        }
        $items = array_merge($dirs, $files);

        if (empty($items)) {
            $items[] = array('id' => '', 'text' => '<i>' . ($dirs_only ? __('No Directories', 'wp-filebase') : __('Empty', 'wp-filebase')) . '</i>', 'classes' => 'empty', 'hasChildren' => false);
        }

        wp_send_json($items);
        exit;
    }

    private static function upload($args)
    {
        define('TMP_FILE_MAX_AGE', 3600 * 3);
        $frontend_upload = !empty($args['frontend_upload']) && $args['frontend_upload'] !== "false";
        $file_add_now = !empty($args['file_add_now']) && $args['file_add_now'] !== "false";

        // TODO: need to check if frontend_upload and user logged in state
        // Flash often fails to send cookies with the POST or upload, so we need to pass it in GET or POST instead
        if (!is_user_logged_in()) {
            if (is_ssl() && empty($_COOKIE[SECURE_AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']))
                $_COOKIE[SECURE_AUTH_COOKIE] = $_REQUEST['auth_cookie'];
            elseif (empty($_COOKIE[AUTH_COOKIE]) && !empty($_REQUEST['auth_cookie']))
                $_COOKIE[AUTH_COOKIE] = $_REQUEST['auth_cookie'];
            if (empty($_COOKIE[LOGGED_IN_COOKIE]) && !empty($_REQUEST['logged_in_cookie']))
                $_COOKIE[LOGGED_IN_COOKIE] = $_REQUEST['logged_in_cookie'];

            if (!empty($_REQUEST['auth_cookie']) || !empty($_REQUEST['logged_in_cookie'])) {
                wp_set_current_user(wp_validate_auth_cookie());
            }
        }

        wpfb_loadclass('Category', 'File');
        $parent_cat = empty($args['cat_id']) ? null : WPFB_Category::GetCat($args['cat_id']);

        if ($frontend_upload) {
            if ($file_add_now) {
                //expecting embedded form if add now
                wpfb_loadclass('EmbeddedForm');
                $embedded_form = isset($args['form_tag']) ? WPFB_EmbeddedForm::Get($args['form_tag']) : null;
                if (is_null($embedded_form) || ($msg = $embedded_form->SecurityIssues($args)))
                    wpfb_ajax_die($msg);
            } else {
                if (!WPFB_Core::$settings->frontend_upload || !WPFB_Core::CheckPermission('frontend_upload', true))
                        wpfb_ajax_die(__('You do not have permission to upload files.', 'wp-filebase'));
            }
        } else {
            if (!WPFB_Core::CurUserCanUpload() && (!$parent_cat || !$parent_cat->CurUserCanAddFiles()))
                wpfb_ajax_die(__('You do not have permission to upload files.', 'wp-filebase'));

            check_admin_referer(WPFB . '-async-upload');
        }


        wpfb_loadclass('Admin');

        if (!empty($args['delupload'])) {
            $del_upload = @json_decode($args['delupload']);
            if ($del_upload && is_file($tmp = WPFB_Core::UploadDir() . '/.tmp/' . str_replace(array('../', '.tmp/'), '', $del_upload->tmp_name)))
                echo (int)@unlink($tmp);

            // delete other old temp files
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $tmp_files = list_files(WPFB_Core::UploadDir() . '/.tmp');
            foreach ($tmp_files as $tmp) {
                if ((time() - filemtime($tmp)) >= TMP_FILE_MAX_AGE)
                    @unlink($tmp);
            }
            exit;
        }

        if (empty($_FILES['async-upload']))
            wpfb_ajax_die(__('No file was uploaded.', 'wp-filebase') . ' (ASYNC)');


        if (!is_uploaded_file($_FILES['async-upload']['tmp_name'])) {
            wpfb_ajax_die(sprintf(__('&#8220;%s&#8221; has failed to upload due to an error (%s)', 'wp-filebase'), esc_html($_FILES['async-upload']['name']), 'not-uploaded'));
        }

        if (!($tmp = WPFB_Admin::GetTmpFile($_FILES['async-upload']['name']))) {
            WPFB_Core::LogMsg("Upload Error: cannot create tmp file!");
            wpfb_ajax_die(sprintf(__('&#8220;%s&#8221; has failed to upload due to an error (%s)', 'wp-filebase'), esc_html($_FILES['async-upload']['name']), 'no-tmp'));
        }

        if (!move_uploaded_file($_FILES['async-upload']['tmp_name'], $tmp)) {
            WPFB_Core::LogMsg("Upload Error: cannot move file to $tmp");
            wpfb_ajax_die(sprintf(__('&#8220;%s&#8221; has failed to upload due to an error (%s)', 'wp-filebase'), esc_html($_FILES['async-upload']['name']), 'move-failed'));
        }

        $_FILES['async-upload']['tmp_name'] = trim(substr($tmp, strlen(WPFB_Core::UploadDir())), '/');

        $json = json_encode($_FILES['async-upload']);

        if ($file_add_now) {

            $file_data = array('file_flash_upload' => $json, 'file_category' => 0);
            if (!empty($args['presets'])) {
                $presets = array();
                parse_str($args['presets'], $presets);
                WPFB_Admin::AdaptPresets($presets);
                $file_data = array_merge($file_data, $presets);
                if (isset($embedded_form)) {
                    $embedded_form->ProcessPostVars($file_data);
                    $file_data['form'] = $embedded_form;
                }
            }

            $result = WPFB_Admin::InsertFile($file_data, false);
            if (empty($result['error'])) {
                $resp = array_merge((array)$result['file'], array(
                    'file_thumbnail_url' => $result['file']->GetIconUrl(),
                    'file_edit_url' => $result['file']->GetEditUrl(),
                    'file_cur_user_can_edit' => $result['file']->CurUserCanEdit(),
                    'file_download_url' => $result['file']->GetUrl(),
                    'nonce' => wp_create_nonce(WPFB . '-updatefile' . $result['file_id'])
                ));

                if (isset($args['tpl_tag'])) {
                    $tpl_tag = $args['tpl_tag'];
                    if ($tpl_tag === 'false')
                        $tpl_tag = null;
                    $resp['tpl'] = $result['file']->GenTpl2($tpl_tag);
                }
            } else {
                wpfb_ajax_die($result['error']);
            }

            $json = json_encode($resp);
        }


        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        //header('Content-Length: ' . strlen($json));
        echo $json;
    }


    private static function uploadChunked($args)
    {
        // TODO: front-end upload check! ( see upload() )
        if (!WPFB_Core::CurUserCanUpload())
            throw new RuntimeException(__('You do not have permission to upload files.', 'wp-filebase'));

        if (!is_numeric($args['chunkIndex']) || $args['chunkIndex'] < -1) {
            throw new InvalidArgumentException("chunkIndex invalid");
        }

        if (empty($args['name'])) {
            throw new InvalidArgumentException("name is empty");
        }
        if (empty($args['token']) || strlen($args['token']) < 5) {
            throw new InvalidArgumentException("token is empty");
        }
        if (!is_numeric($args['size']) || $args['size'] <= 0) {
            throw new InvalidArgumentException("size is out of range");
        }
        if (!is_numeric($args['lastModified']) || $args['lastModified'] <= 0) {
            throw new InvalidArgumentException("lastModified is out of range");
        }

        $key = implode('/', array($args['name'], $args['token'], +$args['size'], +$args['lastModified']));
        $key = hash('sha256', $key);
        $uploaded_file = WPFB_Core::UploadDir() . "/.tmp/ul-$key";

        $resp = array(
            'maxChunkSize' => min(1024 * 1024 * 30, WPFB_Core::GetMaxUlSize() - 1024 * 4),
            'offset' => 0,
        );

        $chunkIndex = +$args['chunkIndex'];
        $start = ($chunkIndex == -1);
        $exists = is_file($uploaded_file);
        $localOffset = $exists ? filesize($uploaded_file) : 0;

        if (!$exists && !$start)
            throw new RuntimeException("need to start download first (set i = -1)");

        // restart?
        if ($exists && $start && !empty($args['restart'])) {
            unlink($uploaded_file);
            $exists = false;
            $localOffset = 0;
        }

        if ($exists)
            $resp['offset'] = $localOffset;
        else {
            if (!is_dir(dirname($uploaded_file)))
                WPFB_Admin::Mkdir(dirname($uploaded_file));
            if (!touch($uploaded_file))
                throw new RuntimeException("Cannot create uploaded file!");
        }

        if($start) {
            wp_send_json($resp);
            return;
        }

        if(empty($_FILES['upload'])) {
            throw new RuntimeException("missing upload!");
        }

        $uploadOffset = +$args['offset'];
        $uploadSize = +$_FILES['upload']['size'];
        if($localOffset != $uploadOffset) {
            throw new RuntimeException("wrong offset ({$localOffset} vs {$uploadOffset})!");
        }

        $chunkFile = "$uploaded_file-chunk";

        if(file_exists($chunkFile))
            throw new RuntimeException("chunk file already exists!");

        if (!move_uploaded_file($_FILES['upload']['tmp_name'], $chunkFile)) {
            @unlink($chunkFile);
            WPFB_Core::LogMsg("Upload Error: cannot move chunk file to $chunkFile");
            throw new RuntimeException("Cannot move chunk file!");
        }

        $res = file_put_contents($uploaded_file, file_get_contents($chunkFile), FILE_APPEND | LOCK_EX);
        @unlink($chunkFile);

        if($res === false || $res != $uploadSize) {
            throw new RuntimeException("failed to append chunk!");
        }

        if(filesize($uploaded_file) != $localOffset + $uploadSize) {
            throw new RuntimeException("wrong file size after appending chunk!");
        }

        $resp['offset'] = $localOffset + $uploadSize;

        wp_send_json($resp);
    }

    private static function sideload($args)
    {
        if (!WPFB_Core::CurUserCanUpload())
            throw new RuntimeException(__('You do not have permission to upload files.', 'wp-filebase'));

        if(empty($args['uris']) || !is_array($args['uris']))
            throw new InvalidArgumentException("no uris given!");

        //WPFB_Admin::SideloadFile()
    }


    private static function parseFilename($args)
    {
        wpfb_loadclass('Admin');
        wp_send_json(WPFB_Admin::ParseFileNameVersion($args['filename']));
        exit;
    }
}

function wpfb_ajax_die($msg, $title = '', $args = '')
{
    if (empty($msg))
        die();
    echo '<div class="error-div">
	<strong>' . $title . ' ' . $msg . '</strong></div>';
    exit;
}

