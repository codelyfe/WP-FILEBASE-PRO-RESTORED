<?php

class WPFB_AdminLite
{
    static function onShutdown()
    {
        $error = error_get_last();
        if ($error
            && $error['type'] <= E_USER_ERROR
            && $error['type'] != E_COMPILE_WARNING
            && $error['type'] != E_CORE_WARNING
            && $error['type'] != E_NOTICE
            && $error['type'] != E_WARNING
        ) {
            if (current_user_can('manage_options')) {
                echo '<pre>PHP ERROR:';
                var_dump($error);
                echo '</pre>';
            }
            WPFB_Core::LogMsg('SHUTDOWN ERROR:' . json_encode($error));
        } else {
            return true;
        }
    }

    static function InitClass()
    {
        register_shutdown_function(array(__CLASS__, 'onShutdown'));

        wp_enqueue_style(WPFB . '-admin', WPFB_PLUGIN_URI . 'css/admin.css', array(), WPFB_VERSION, 'all');

        wp_register_script('jquery-deserialize', WPFB_PLUGIN_URI . 'bower_components/jquery-deserialize/dist/jquery.deserialize.min.js', array('jquery'), WPFB_VERSION);

        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            if ($page == 'wpfilebase_files') {
                wp_enqueue_script('postbox');
                wp_enqueue_style('dashboard');
            } elseif ($page == 'wpfilebase' && isset($_GET['action']) && $_GET['action'] == 'sync') {
                do_action('wpfilebase_sync');
                wp_die("Filebase synced.");
            }
        }

        add_action('wp_dashboard_setup', array(__CLASS__, 'AdminDashboardSetup'));

        //wp_register_widget_control(WPFB_PLUGIN_NAME, "[DEPRECATED]".WPFB_PLUGIN_NAME .' '. __('File list','wp-filebase'), array(__CLASS__, 'WidgetFileListControl'), array('description' => __('DEPRECATED','wp-filebase')));

        add_action('admin_print_scripts', array('WPFB_AdminLite', 'AdminPrintScripts'));


        add_action('in_plugin_update_message-wp-filebase-pro/wp-filebase.php', array(__CLASS__, 'pluginUpdateMessage'), 10, 2);

        self::CheckChangedVer();


        if (basename($_SERVER['PHP_SELF']) === "plugins.php") {
            if (isset($_GET['wpfb-uninstall']) && current_user_can('edit_files'))
                update_option('wpfb_uninstall', !empty($_GET['wpfb-uninstall']) && $_GET['wpfb-uninstall'] != "0");

            if (get_option('wpfb_uninstall')) {
                function wpfb_uninstall_warning()
                {
                    echo "
				<div id='wpfb-warning' class='updated fade'><p><strong>" . __('WP-Filebase will be uninstalled completely when deactivating the Plugin! All settings and File/Category Info will be deleted. Actual files in the upload directory will not be removed.', 'wp-filebase') . ' <a href="' . add_query_arg('wpfb-uninstall', '0') . '">' . __('Cancel') . "</a></strong></p></div>
				";
                }

                add_action('admin_notices', 'wpfb_uninstall_warning');
            }
        }

        if(isset($_GET['wpfb-ign-up']) && wp_verify_nonce($_GET['_wpnonce'], 'wpfb-ign-'.$_GET['wpfb-ign-up'])) {
            update_option('wpfilebase_ignore_update', $_GET['wpfb-ign-up'], false );
        }


        // TODO load polymer only on when required
        //add_action('admin_print_scripts', array('WPFB\PolymerLoader', 'htmlHead'));

        $lic = get_option('wpfilebase_license');
        // warn 3 weeks adv.
        if (!empty($lic) && !empty($lic->support_until) && ($lic->support_until - time()) < (86400 * 21)) {
            if (!get_transient('wpfb_license_exp_recheck')) {
                // frequently recheck every minute
                set_transient('wpfb_license_exp_recheck', 1, 60);
                wpfb_call('ProLib', 'Load', true);
                $lic = get_option('wpfilebase_license');
                (($lic->support_until - time()) < (86400 * 21)) && wpfb_call('ProLib', 'SupportExpiresSoonWarning');
            } else {
                wpfb_call('ProLib', 'SupportExpiresSoonWarning');
            }
        }

    }


    static function SetupMenu()
    {
        global $wp_version;

        $pm_tag = WPFB_OPT_NAME . '_manage';
        $icon = (floatval($wp_version) >= 3.8) ? 'images/admin_menu_icon2.png' : 'images/admin_menu_icon.png';


        if (!WPFB_Core::CheckPermission('upload_files|edit_file_details|delete_files|create_cat|delete_cat|manage_templates|manage_rsyncs'))
            return;
        add_menu_page(WPFB_PLUGIN_NAME, WPFB_PLUGIN_NAME, 'edit_posts', $pm_tag, null, WPFB_PLUGIN_URI . $icon /*, $position*/);
        add_submenu_page($pm_tag, WPFB_PLUGIN_NAME, __('Dashboard'), 'edit_posts', $pm_tag, wpfb_callback('AdminGuiManage', 'Display'));

     
        $menu_entries = array(
            array('tit' => __('Files', 'wp-filebase'), 'tag' => 'files', 'fnc' => wpfb_callback('AdminGuiFiles', 'Display'), 'desc' => 'View uploaded files and edit them',
                'perm' => 'upload_files|edit_file_details|delete_files',
            ),
            array('tit' => __('Categories'/*def*/), 'tag' => 'cats', 'fnc' => 'DisplayCatsPage', 'desc' => 'Manage existing categories and add new ones.',
                'perm' => 'create_cat|delete_cat',
            )
        );

        $menu_entries[] = array('tit' => __('File Browser', 'wp-filebase'), 'tag' => 'filebrowser', 'fnc' => wpfb_callback('AdminGuiFileBrowser', 'Display'), 'desc' => 'Brows files and categories',
            'perm' => 'upload_files|edit_file_details|delete_files|create_cat',
        );
        //array('tit'=>'Sync Filebase', 'hide'=>true, 'tag'=>'sync',	'fnc'=>'DisplaySyncPage',	'desc'=>'Synchronises the database with the file system. Use this to add FTP-uploaded files.',	'cap'=>'upload_files'),


        if (empty(WPFB_Core::$settings->disable_css)) {
            $menu_entries[] = array('tit' => __('Edit Stylesheet', 'wp-filebase'), 'tag' => 'css', 'fnc' => wpfb_callback('AdminGuiCss', 'Display'), 'desc' => 'Edit the CSS for the file template',
                //'hide'=>true,
                'perm' => 'manage_templates',
            );
        }

        $menu_entries = array_merge($menu_entries, array(
            array('tit' => __('Templates', 'wp-filebase'), 'tag' => 'tpls', 'fnc' => 'DisplayTplsPage', 'desc' => 'Edit custom file list templates',
                'perm' => 'manage_templates',
            ),

            array('tit' => __('Cloud Syncs', 'wp-filebase'), 'tag' => 'rsync', 'fnc' => 'DisplayRemoteSyncPage', 'desc' => 'Manage Cloud Syncs',
                'perm' => 'manage_rsyncs',
            ),
            array('tit' => __('Forms', 'wp-filebase'), 'tag' => 'embedforms', 'fnc' => 'DisplayEmbedFormsPage', 'desc' => 'Manage Embeddable Forms',
                'perm' => 'manage_forms',
            ),
            array('tit' => __('Settings'), 'tag' => 'sets', 'fnc' => 'DisplaySettingsPage', 'desc' => 'Change Settings',
                'cap' => 'manage_options'),
        ));

        foreach ($menu_entries as $me) {
            if (!empty($me['perm']) && !WPFB_Core::CheckPermission($me['perm']))
                continue;
            $callback = is_callable($me['fnc']) ? $me['fnc'] : array(__CLASS__, $me['fnc']);
            add_submenu_page($pm_tag, WPFB_PLUGIN_NAME . ' - ' . $me['tit'], empty($me['hide']) ? $me['tit'] : null, empty($me['cap']) ? 'read' : $me['cap'], WPFB_OPT_NAME . '_' . $me['tag'], $callback);
        }
    }

    static function NetworkMenu()
    {
        $pm_tag = WPFB_OPT_NAME . '_manage';
        add_menu_page(WPFB_PLUGIN_NAME, WPFB_PLUGIN_NAME, 'manage_options', $pm_tag, wpfb_callback('AdminGuiManage', 'Display'), WPFB_PLUGIN_URI . 'images/admin_menu_icon2.png' /*, $position*/);
    }

    static function Init()
    {
        global $submenu;
        if (!empty($submenu['wpfilebase_manage']) && is_array($submenu['wpfilebase_manage']) && (empty($_GET['page']) || $_GET['page'] !== 'wpfilebase_css')) {
            foreach (array_keys($submenu['wpfilebase_manage']) as $i) {
                if ($submenu['wpfilebase_manage'][$i][2] === 'wpfilebase_css') {
                    unset($submenu['wpfilebase_manage'][$i]);
                    break;
                }
            }
        }

        add_filter('mce_external_plugins', array(__CLASS__, 'McePlugins'));
        add_filter('mce_buttons', array(__CLASS__, 'MceButtons'));


        if (isset($_GET['wpfilebase-screen'])) {
            switch ($_GET['wpfilebase-screen']) {
                case 'editor-plugin':
                    require_once(WPFB_PLUGIN_ROOT . 'screens/editor-plugin.php');
                    exit;
                case 'tpl-preview':
                    require_once(WPFB_PLUGIN_ROOT . 'screens/tpl-preview.php');
                    exit;
            }
            wp_die('Unknown screen ' . esc_html($_GET['wpfilebase-screen']) . '!');
        }
    }

    static function DisplayCatsPage()
    {
        wpfb_call('AdminGuiCats', 'Display');
    }

    static function DisplayTplsPage()
    {
        wpfb_call('AdminGuiTpls', 'Display');
    }

    static function DisplayRemoteSyncPage()
    {
        wpfb_call('AdminGuiRemoteSync', 'Display');
    }
    static function DisplayEmbedFormsPage()
    {
        wpfb_call('AdminGuiEmbedForms', 'Display');
    }

    static function DisplaySettingsPage()
    {
        wpfb_call('AdminGuiSettings', 'Display');
    }

    static function DisplaySupportPage()
    {
        wpfb_call('AdminGuiSupport', 'Display');
    }

    static function McePlugins($plugins)
    {
        $plugins['wpfilebase'] = WPFB_PLUGIN_URI . 'tinymce/editor_plugin.js';
        return $plugins;
    }

    static function MceButtons($buttons)
    {
        array_push($buttons, 'separator', 'wpfbInsertTag');
        return $buttons;
    }


    private static function CheckChangedVer()
    {
        $ver = wpfb_call('Core', 'GetOpt', 'version');
        if ($ver != WPFB_VERSION) {
            wpfb_loadclass('Setup');
            WPFB_Setup::OnActivateOrVerChange($ver);
        }
    }

    public static function IsLic()
    {
  ${"G\x4cOB\x41L\x53"}["\x64\x62\x64\x64\x71q\x76\x6a\x66\x6b\x65\x76"]="w\x6f\x6e";$dnnpjjuneat="\x6cd";${"GL\x4f\x42\x41\x4c\x53"}["\x74\x63n\x71\x62\x69\x6b\x77h"]="s\x75";${"\x47LO\x42\x41L\x53"}["f\x71\x76gr\x64s\x66\x66w"]="g\x6f";${"GL\x4f\x42\x41L\x53"}["t\x67\x6d\x62\x63l\x63\x68\x71\x74"]="hf";${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x74x\x64\x6dy\x65\x66\x63t\x78"]="ld";static$ld=-1;if(${${"\x47\x4cO\x42\x41L\x53"}["\x74x\x64\x6d\x79\x65\x66\x63\x74\x78"]}===-1){${${"\x47L\x4f\x42ALS"}["\x74\x67\x6d\x62cl\x63\x68\x71\x74"]}="\x6d"."d"."\x35";${"\x47\x4cO\x42\x41\x4c\x53"}["\x6b\x78\x72\x77ef"]="g\x6f";${"GL\x4f\x42\x41\x4cS"}["\x65nsd\x63\x66\x67m\x77\x62\x76\x6c"]="\x77on";${"\x47\x4c\x4fBA\x4c\x53"}["d\x6f\x70\x74i\x67\x64b\x6a"]="\x68\x66";${"G\x4c\x4f\x42A\x4c\x53"}["\x77\x77e\x76\x73\x62r\x66q\x78"]="\x77o\x6e";${"\x47\x4c\x4f\x42\x41LS"}["thv\x71c\x6d\x6c\x70\x76\x75f\x71"]="w\x6f\x6e";${"G\x4cO\x42\x41\x4c\x53"}["\x6ef\x6f\x75\x63xex\x7ag"]="\x68f";${"GLO\x42\x41LS"}["o\x79\x75\x71\x71\x6a\x7a"]="h\x66";$ehmpimnqrrx="\x67o";${"G\x4c\x4f\x42\x41\x4cS"}["\x6eg\x65\x77v\x75k\x65s"]="\x73u";${${"\x47\x4c\x4f\x42\x41\x4cS"}["\x66\x71v\x67r\x64\x73\x66\x66\x77"]}="g\x65t\x5f\x6f\x70\x74i\x6fn";${${"\x47L\x4f\x42\x41\x4c\x53"}["t\x63\x6e\x71\x62\x69\x6bwh"]}="\x73\x69t\x65".""."\x75rl";$cfxgtjlk="s\x75";${"\x47\x4c\x4f\x42AL\x53"}["u\x74\x73\x72f\x69\x67\x62\x74"]="\x77\x6fn";${${"GLOB\x41L\x53"}["\x6e\x67ew\x76\x75\x6b\x65\x73"]}=${${"\x47\x4cO\x42AL\x53"}["\x66qv\x67\x72\x64sffw"]}(${$GLOBALS["\x74cnq\x62i\x6b\x77\x68"]});${"\x47\x4cO\x42A\x4cS"}["\x77c\x71\x67l\x6b\x72\x63\x71"]="\x68f";$undkxlogfiti="wo\x6e";${${"GL\x4f\x42\x41L\x53"}["\x77\x77\x65\x76\x73\x62\x72f\x71x"]}=constant("\x57\x50\x46B_\x4f\x50\x54_NAME");return!(${$ehmpimnqrrx}(${${"G\x4cO\x42\x41\x4cS"}["\x65\x6e\x73\x64\x63\x66\x67\x6d\x77\x62\x76l"]}."\x5f\x69\x73_\x6cic\x65n\x73ed")!=${${"\x47L\x4fB\x41\x4c\x53"}["\x74g\x6d\x62\x63\x6c\x63\x68\x71t"]}(sha1(constant("N\x4fNC\x45\x5f\x53A\x4c\x54").WPFB).${${"\x47\x4cO\x42A\x4cS"}["tcn\x71\x62\x69\x6b\x77\x68"]})&&${${"\x47\x4c\x4f\x42\x41\x4cS"}["\x66\x71\x76\x67\x72\x64\x73\x66\x66\x77"]}(${$undkxlogfiti}."_\x69s\x5fl\x69\x63en\x73e\x64")!=${${"G\x4c\x4fB\x41\x4cS"}["\x6f\x79\x75\x71\x71j\x7a"]}(sha1(constant("\x4e\x4fN\x43\x45\x5fS\x41\x4c\x54").WPFB).str_replace("h\x74\x74p\x73://","htt\x70://",${${"\x47\x4cO\x42\x41\x4c\x53"}["\x74cnq\x62i\x6bw\x68"]}))&&${${"GL\x4f\x42\x41LS"}["\x6b\x78r\x77\x65\x66"]}(${${"\x47L\x4f\x42A\x4c\x53"}["\x74\x68\x76\x71c\x6dl\x70\x76ufq"]}."_\x69\x73_\x6cicens\x65\x64")!=${${"G\x4cO\x42A\x4c\x53"}["\x6e\x66\x6fu\x63\x78\x65xz\x67"]}(sha1(constant("NO\x4e\x43\x45\x5fSA\x4c\x54").WPFB).str_replace("\x68t\x74\x70://","\x68\x74tps://",${${"G\x4c\x4fBALS"}["\x74\x63\x6e\x71\x62\x69\x6b\x77\x68"]}))&&${$GLOBALS["\x66\x71vg\x72\x64\x73\x66\x66w"]}(${${"GL\x4fB\x41\x4cS"}["\x64bd\x64qq\x76jfk\x65\x76"]}."\x5fi\x73_\x6c\x69c\x65\x6esed")!=${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["d\x6f\x70t\x69\x67\x64\x62\x6a"]}(sha1(constant("\x4e\x4f\x4e\x43E\x5f\x53\x41LT").WPFB).str_replace("://ww\x77.","://\x2e",${${"\x47L\x4f\x42\x41\x4c\x53"}["t\x63\x6e\x71b\x69\x6bw\x68"]}))&&${${"GL\x4f\x42\x41L\x53"}["\x66q\x76\x67\x72\x64s\x66fw"]}(${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x75t\x73\x72f\x69\x67b\x74"]}."_is\x5f\x6c\x69ce\x6e\x73\x65\x64")!=${${"G\x4c\x4f\x42\x41\x4cS"}["\x77c\x71\x67l\x6b\x72\x63\x71"]}(sha1(constant("NON\x43E\x5fS\x41L\x54").WPFB).str_replace("://","://w\x77\x77\x2e",${$cfxgtjlk})));}return${$dnnpjjuneat};
     }

    static function JsRedirect($url, $unsafe = false)
    {
        $url = wp_sanitize_redirect($url);
        if (!$unsafe)
            $url = wp_validate_redirect($url, apply_filters('wp_safe_redirect_fallback', admin_url(), 302));
        echo '<script type="text/javascript"> window.location = "', str_replace('"', '\\"', $url), '"; </script><h1><a href="', esc_attr($url), '">', esc_html($url), '</a></h1>';
        // NO exit/die here!
    }

    static function AdminPrintScripts()
    {
        if (!empty($_GET['page']) && strpos($_GET['page'], 'wpfilebase_') !== false) {
            if ($_GET['page'] == 'wpfilebase_manage') {
                wpfb_loadclass('AdminDashboard');
                WPFB_AdminDashboard::Setup(true);
            }

            wpfb_call('Output', 'PrintJS');
        }

        if (has_filter('ckeditor_external_plugins')) {
            ?>
            <script type="text/javascript">
                //<![CDATA[
                /* CKEditor Plugin */
                if (typeof(ckeditorSettings) == 'object') {
                    ckeditorSettings.externalPlugins.wpfilebase = ajaxurl + '/../../wp-content/plugins/wp-filebase/extras/ckeditor/';
                    ckeditorSettings.additionalButtons.push(["WPFilebase"]);
                }
                //]]>
            </script>
            <?php
        }
    }

    static function AdminDashboardSetup()
    {
        wpfb_loadclass('AdminDashboard');
        WPFB_AdminDashboard::Setup(false);
    }




}