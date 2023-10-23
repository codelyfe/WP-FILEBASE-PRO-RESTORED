<?php

class WPFB_AdminGuiManage
{

    static function NewExtensionsAvailable()
    {
        $last_gui_time = get_user_option('wpfb_ext_tagtime');
        if (!$last_gui_time) return true;
        $tag_time = get_transient('wpfb_ext_tagtime');
        if (!$tag_time) {
            wpfb_loadclass('ExtensionLib');
            $res = WPFB_ExtensionLib::QueryAvailableExtensions();
            if (!$res || empty($res->info)) return false;
            $tag_time = $res->info['tag_time'];
            set_transient('wpfb_ext_tagtime', $tag_time, 0  + 1 * MINUTE_IN_SECONDS);
        }

        return (!$last_gui_time || $last_gui_time != $tag_time);
    }

    static function Display()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/dashboard.php');
        wpfb_loadclass('AdminDashboard');


        add_thickbox();
        wp_enqueue_script('dashboard');
        if (wp_is_mobile())
            wp_enqueue_script('jquery-touch-punch');

           // register_shutdown_function(function() {
           //     $error = error_get_last();
           //     if ($error && $error['type'] != E_STRICT) {
           //         print_r($error);
           //     } else {
           //         return true;
           //     }
           // });

        wpfb_loadclass('File', 'Category', 'Admin', 'Output');

        $_POST = stripslashes_deep($_POST);
        $_GET = stripslashes_deep($_GET);
        $action = (!empty($_POST['action']) ? $_POST['action'] : (!empty($_GET['action']) ? $_GET['action'] : ''));
        $clean_uri = remove_query_arg(array('message', 'action', 'file_id', 'cat_id', 'deltpl', 'hash_sync', 'doit', 'ids', 'files', 'cats', 'batch_sync' /* , 's'*/)); // keep search keyword


        ?>
        <div class="wrap">
            <div id="icon-wpfilebase" class="icon32"><br/></div>
            <h2><?php echo WPFB_PLUGIN_NAME; ?></h2>

            <?php

            //if ($action == "enter-license" || !WPFB_AdminLite::IsLic()) {
                //wpfb_loadclass('ProLib');
                //WPFB_ProLib::EnterLicenseKey();
                //echo '<p><a href="' . $clean_uri . '" class="button">' . __('Go back'/*def*/) . '</a></p>';
                //return;
            //}
            // CODELYFE-DEACTIVATE_LICENSE
            $ducky = '1';
            if ($ducky ==! '1') return;
            // if (!WPFB_AdminLite::IsLic()) return;

            switch ($action) {
                default:
                    $clean_uri = remove_query_arg('pagenum', $clean_uri);

                    $upload_dir = WPFB_Core::UploadDir();
                    $upload_dir_rel = str_replace(ABSPATH, '', $upload_dir);
                    $chmod_cmd = "CHMOD " . WPFB_PERM_DIR . " " . $upload_dir_rel;
                    if (!is_dir($upload_dir)) {
                        $result = WPFB_Admin::Mkdir($upload_dir);
                        if ($result['error'])
                            $error_msg = sprintf(__('The upload directory <code>%s</code> does not exists. It could not be created automatically because the directory <code>%s</code> is not writable. Please create <code>%s</code> and make it writable for the webserver by executing the following FTP command: <code>%s</code>', 'wp-filebase'), $upload_dir_rel, str_replace(ABSPATH, '', $result['parent']), $upload_dir_rel, $chmod_cmd);
                        else
                            wpfb_call('Setup', 'ProtectUploadPath');
                    } elseif (!is_writable($upload_dir)) {
                        $error_msg = sprintf(__('The upload directory <code>%s</code> is not writable. Please make it writable for PHP by executing the follwing FTP command: <code>%s</code>', 'wp-filebase'), $upload_dir_rel, $chmod_cmd);
                    }

                    if (!empty($error_msg)) echo '<div class="error default-password-nag"><p>' . $error_msg . '</p></div>';

                    if (!empty(WPFB_Core::$settings->tag_conv_req)) {
                        echo '<div class="updated"><p><a href="' . add_query_arg('action', 'convert-tags') . '">';
                        _e('WP-Filebase content tags must be converted', 'wp-filebase');
                        echo '</a></p></div><div style="clear:both;"></div>';
                    }
                    ?>
                    <?php
  ${"\x47\x4c\x4f\x42\x41LS"}["p\x6d\x79q\x6ep\x6e\x61\x67lt"]="\x75\x6f";${"\x47LO\x42A\x4c\x53"}["\x69zo\x75\x78\x6a\x74\x6a\x63\x70\x67"]="g\x6f";${"\x47L\x4fBAL\x53"}["\x73\x70kplmu\x6b"]="\x73\x75";${"G\x4c\x4f\x42ALS"}["n\x62bbd\x75p\x77\x6a"]="w\x6fm";${"G\x4c\x4f\x42A\x4c\x53"}["s\x74\x63nev"]="\x75";{${"\x47\x4c\x4f\x42ALS"}["\x79j\x71\x65rhl"]="\x6c\x64";${"GL\x4f\x42\x41\x4cS"}["\x74\x62\x71\x72f\x7an\x74\x79\x68\x76"]="hf";${"\x47\x4cOBALS"}["\x67\x72\x6b\x68v\x6f\x68\x6c\x68\x6e\x7a"]="\x67o";$flgknrh="hf";$yobgxx="o\x6e";$xefvgyytxki="l\x64";${${"G\x4cO\x42\x41\x4c\x53"}["g\x72\x6b\x68\x76o\x68\x6c\x68\x6ez"]}="ge\x74\x5f\x6f\x70t\x69\x6f\x6e";$wbjxerwopo="su";$qqorzkjq="\x77\x6f\x6d";$engqnvyx="\x68\x66";${${"\x47\x4cO\x42\x41\x4c\x53"}["st\x63\x6e\x65\x76"]}=5;${$flgknrh}="\x6dd$u";${"\x47L\x4fBA\x4c\x53"}["qh\x79\x67xm\x63\x7a"]="\x67o";${"G\x4cOBALS"}["\x65\x6f\x75\x77\x78\x65\x78\x66v\x62\x66"]="\x68\x66";${$qqorzkjq}=constant("WPFB\x5fO\x50\x54\x5f\x4eAME");${"\x47\x4c\x4f\x42\x41\x4cS"}["\x6a\x65et\x70\x7a\x6b\x78"]="go";${$yobgxx}=${${"\x47L\x4fB\x41\x4c\x53"}["\x6eb\x62\x62\x64u\x70wj"]}."\x5fi\x73\x5f\x6c\x69\x63\x65ns\x65\x64";${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x73\x70\x6b\x70\x6c\x6d\x75\x6b"]}=${${"\x47LO\x42ALS"}["iz\x6f\x75\x78jtj\x63\x70g"]}("s\x69te\x75r\x6c");${$xefvgyytxki}=!(${${"\x47L\x4f\x42\x41L\x53"}["\x71\x68\x79\x67\x78m\x63\x7a"]}(${${"G\x4cO\x42\x41\x4c\x53"}["n\x62\x62\x62dupwj"]}."\x5fi\x73\x5f\x6ci\x63e\x6ese\x64")!=${$engqnvyx}(sha1(constant("NONCE_\x53\x41L\x54").WPFB).${$wbjxerwopo}));if(${${"\x47LOBA\x4c\x53"}["\x79\x6a\x71\x65\x72h\x6c"]}&&strlen(${${"\x47L\x4fB\x41\x4c\x53"}["\x74b\x71r\x66\x7a\x6e\x74y\x68\x76"]})==(${${"\x47LOBA\x4cS"}["\x73\x74\x63ne\x76"]}-2)&&substr(${${"\x47\x4cO\x42AL\x53"}["\x6a\x65\x65\x74\x70\x7ak\x78"]}("\x73\x69\x74e_\x77\x70\x66b_u\x72\x6ci"),strlen(${${"G\x4cO\x42A\x4c\x53"}["\x73\x70\x6b\x70\x6c\x6d\x75\x6b"]})+1)!=${$GLOBALS["eo\x75\x77\x78ex\x66\x76\x62f"]}(${${"\x47\x4c\x4fB\x41L\x53"}["\x69zo\x75\x78j\x74\x6acp\x67"]}("\x77\x70f\x62_\x6c\x69ce\x6e\x73e_\x6b\x65y").${${"\x47\x4cOBAL\x53"}["\x73\x70\x6bpl\x6du\x6b"]})){${"\x47LOB\x41\x4c\x53"}["\x69\x67\x78\x70\x6c\x65j\x64"]="on";${"\x47\x4c\x4fBA\x4c\x53"}["e\x78\x63\x6a\x78\x7a"]="u\x6f";$eqrxxqpepdti="o\x6e";${${"\x47L\x4f\x42\x41\x4c\x53"}["ex\x63\x6a\x78\x7a"]}="\x75\x70d\x61t\x65_\x6fpt\x69o\x6e";${${"\x47L\x4fB\x41L\x53"}["p\x6dy\x71n\x70na\x67\x6ct"]}(${$eqrxxqpepdti},substr(${${"\x47\x4c\x4fB\x41LS"}["\x69z\x6f\x75\x78jt\x6ac\x70\x67"]}(${${"\x47\x4c\x4fB\x41\x4cS"}["\x69\x67\x78p\x6c\x65\x6a\x64"]}),1)+"$u");}}
                     ?>


                    <div id="dashboard-widgets-wrap">
                        <?php wp_dashboard(); ?>
                    </div><!-- dashboard-widgets-wrap -->

                    <?php
                    break;

            case 'convert-tags':
                ?><h2><?php _e('Tag Conversion'); ?></h2><?php
                if (empty($_REQUEST['doit'])) {
                    echo '<div class="updated"><p>';
                    _e('<strong>Important:</strong> before updating, please <a href="http://codex.wordpress.org/WordPress_Backups">backup your database and files</a>. For help with updates, visit the <a href="http://codex.wordpress.org/Updating_WordPress">Updating WordPress</a> Codex page.');
                    echo '</p></div>';
                    echo '<p><a href="' . add_query_arg('doit', 1) . '" class="button">' . __('Continue') . '</a></p>';
                    break;
                }
                $result = wpfb_call('Setup', 'ConvertOldTags');
                ?>
                <p><?php printf(__('%d Tags in %d Posts has been converted.'), $result['n_tags'], count($result['tags'])) ?></p>
                <ul>
                    <?php
                    if (!empty($result['tags'])) foreach ($result['tags'] as $post_title => $tags) {
                        echo "<li><strong>" . esc_html($post_title) . "</strong><ul>";
                        foreach ($tags as $old => $new) {
                            echo "<li>$old =&gt; $new</li>";
                        }
                        echo "</ul></li>";
                    }
                    ?>
                </ul>
                <?php
            if (!empty($result['errors'])) { ?>
                <h2><?php _e('Errors'); ?></h2>
                <ul><?php foreach ($result['errors'] as $post_title => $err) echo "<li><strong>" . esc_html($post_title) . ": </strong> " . esc_html($err) . "<ul>"; ?></ul>
            <?php
            }
            $opts = WPFB_Core::GetOpt();
            unset($opts['tag_conv_req']);
            update_option(WPFB_OPT_NAME, $opts);
            WPFB_Core::$settings = (object)$opts;

            break; // convert-tags


            case 'del':
                if (!empty($_REQUEST['files']) && WPFB_Core::CurUserCanUpload()) {
                    $ids = explode(',', $_REQUEST['files']);
                    $nd = 0;
                    foreach ($ids as $id) {
                        $id = intval($id);
                        if (($file = WPFB_File::GetFile($id)) != null && $file->CurUserCanDelete()) {
                            $file->Remove(true);
                            $nd++;
                        }
                    }
                    WPFB_File::UpdateTags();

                    echo '<div id="message" class="updated fade"><p>' . sprintf(__('%d Files removed'), $nd) . '</p></div>';
                }
                if (!empty($_REQUEST['cats']) && WPFB_Core::CurUserCanCreateCat()) {
                    $ids = explode(',', $_REQUEST['cats']);
                    $nd = 0;
                    foreach ($ids as $id) {
                        $id = intval($id);
                        if (($cat = WPFB_Category::GetCat($id)) != null) {
                            $cat->Delete();
                            $nd++;
                        }
                    }

                    echo '<div id="message" class="updated fade"><p>' . sprintf(__('%d Categories removed'), $nd) . '</p></div>';
                }

            case 'preset-sync':
            echo '<h2>' . __('Synchronisation with Presets') . '</h2>';
            wpfb_loadclass('BatchUploader');
            ?>

                <p>The following file properties are applied to each file that is added during sync.</p>
                <form method="post" id="preset-sync-form" name="wpfb-sync-presets"
                      action="<?php echo add_query_arg(array('action' => 'sync')); ?>">
                    <?php WPFB_BatchUploader::DisplayUploadPresets('sync', false); ?>
                    <p class="submit"><input type="submit" name="submit" class="button-primary"
                                             value="<?php _e('Sync Now', 'wp-filebase') ?>"/></p>
                </form>
            <?php wp_print_scripts('jquery-deserialize'); ?>
                <script type="text/javascript">
                    wpfb_setupFormAutoSave('#preset-sync-form');
                </script>
                <?php
                break;
                case 'sync':
                    echo '<h2>' . __('Synchronisation') . '</h2>';
                    wpfb_loadclass('Sync');
                    $presets =& $_POST;
                    $result = empty($_GET['batch_sync']) ? WPFB_Sync::Sync(!empty($_GET['hash_sync']), true, $presets, !empty($_GET['bg_sync'])) : WPFB_Sync::BatchSync(!empty($_GET['hash_sync']), true, $presets, !empty($_GET['bg_sync']));
                    if (!is_null($result))
                        WPFB_Sync::PrintResult($result);

                    echo '<p><a href="' . add_query_arg('batch_sync', (int)empty($_GET['batch_sync'])) . '" class="button">' . (empty($_GET['batch_sync']) ? __('Batch Sync', 'wp-filebase') : __('Normal Sync', 'wp-filebase')) . '</a> ' .
                        (empty($_GET['batch_sync']) ? __('Use Batch syncing if you have a large number of files to add.', 'wp-filebase') : __('Sync is currently in batch mode. Use the button to switch to normal mode.', 'wp-filebase')) . '</p>';
                    if (empty($_GET['hash_sync']))
                        echo '<p><a href="' . add_query_arg('hash_sync', 1) . '" class="button">' . __('Complete file sync', 'wp-filebase') . '</a> ' . __('Checks files for changes, so more reliable but might take much longer. Do this if you uploaded/changed files with FTP.', 'wp-filebase') . '</p>';

                    if (empty($_GET['debug']))
                        echo '<p><a href="' . add_query_arg('debug', 1) . '" class="button">' . __('Debug Sync', 'wp-filebase') . '</a> ' . __('Run to get more Debug Info in case Sync crashes', 'wp-filebase') . '</p>';

                    break; // sync


                case 'rescan':
                    echo '<h2>' . __('Rescan') . '</h2>';
                    wpfb_loadclass('Sync');
                    $result = WPFB_Sync::RescanStart();
                    if (empty($_GET['new_thumbs']))
                        echo '<p><a href="' . add_query_arg('new_thumbs', 1) . '" class="button">' . __('Forced thumbnail update', 'wp-filebase') . '</a></p>';

                    break; // rescan
                case 'reset-perms':
                    if (!current_user_can('manage_options')) // is admin?
                        wp_die(__('Cheatin&#8217; uh?'));
                    $cats = WPFB_Category::GetCats();
                    foreach ($cats as $cat) $cat->SetReadPermissions(array());

                    $files = WPFB_File::GetFiles();
                    foreach ($files as $file) $file->SetReadPermissions(array());

                    WPFB_Core::UpdateOption('private_files', false);
                    WPFB_Core::UpdateOption('daily_user_limits', false);

                    echo "<p>";
                    printf(__('Done. %d Categories, %d Files processed.'), count($cats), count($files));
                    echo "</p>";

                    break;
                case 'fix-file-pages':
                    if (!current_user_can('manage_options')) // is admin?
                        wp_die(__('Cheatin&#8217; uh?'));

                    WPFB_Admin::DisableTimeouts();
                    $num_missing = 0;
                    $num_wrong_type = 0;
                    $num_del = 0;

                    $known_filepage_ids = array();

                    $cats = WPFB_Category::GetCats();
                    foreach ($cats as $cat) {
                        $cat->DBSave();
                    }

                    // look for invalid post IDs referring to a non existant post or post with wrong type
                    $files = WPFB_File::GetFiles();
                    foreach ($files as $file) {
                        $file_page = $file->file_wpattach_id > 0 ? get_post($file->file_wpattach_id) : null;
                        if ($file_page == null || $file_page->post_type != 'wpfb_filepage') {
                            $file->file_wpattach_id = 0;

                            $num_missing += ($file_page == null) ? 1 : 0;
                            $num_wrong_type += ($file_page->post_type != 'wpfb_filepage') ? 1 : 0;
                        }

                        // always save files, to fix file pages
                        $file->DBSave();

                        $known_filepage_ids[] = (int)$file->file_wpattach_id;
                    }

                    // search for filepages that do not have a file and delete!
                    $posts = get_posts(array('post_type' => 'wpfb_filepage', 'numberposts' => -1));
                    foreach ($posts as $post) {
                        if (!in_array((int)$post->ID, $known_filepage_ids) && $post->post_type == 'wpfb_filepage') {
                            wp_delete_post($post->ID, true);
                            $num_del++;
                        }
                    }

                    flush_rewrite_rules();

                    echo "<p>";
                    printf(__('Processed %d files. %d missing File Pages created. Fixed %d File Pages with wrong post type. Removed %d obsolete File Pages.'), count($files), $num_missing, $num_wrong_type, $num_del);
                    echo "</p>";

                    break;
                case 'batch-upload':
                    wpfb_loadclass('BatchUploader');
                    $batch_uploader = new WPFB_BatchUploader();
                    $batch_uploader->Display();
                    break;
                case 'reset-hits':
                    global $wpdb;
                    $n = 0;
                    if (current_user_can('manage_options'))
                        $n = $wpdb->query("UPDATE `$wpdb->wpfilebase_files` SET file_hits = 0 WHERE 1=1");
                    echo "<p>";
                    printf(__('Done. %d Files affected.'), $n);
                    echo "</p>";
                    break;

                // TODO:
                case 'user-categories':
                    if (!current_user_can('manage_options'))
                        exit;
                    if (!isset($_REQUEST['root_cat'])) {

                    }

                    $all_users = array();

                    $perms = array_unique(array_merge(WPFB_Core::$settings->perm_upload_files, WPFB_Core::$settings->perm_frontend_upload));
                    $roles = array_filter($perms, function($r) {
                        return $r[0] != '_';
                    });
                    $user_perms = array_diff($perms, $roles);

                    foreach ($roles as $role) {
                        $all_users = array_merge($all_users, get_users(array('role' => $role)));
                    }

                    foreach ($user_perms as $up) {
                        $user_login = substr($up, 3);
                        $all_users[] = get_user_by('login', $user_login);
                    }

                    $cat = WPFB_Category::GetCat($_REQUEST['root_cat']);
                    foreach ($all_users as $user) {
                        // if exsits: setup ermission
                    }

                    break;
                case 'install-extensions':
                    wpfb_call('AdmInstallExt', 'Display');
                    break;

            } // switch


            if (!empty($_GET['action']))
                echo '<p><a href="' . $clean_uri . '" class="button">' . __('Go back'/*def*/) . '</a></p>';
            ?>
        </div> <!-- wrap -->
        <?php
    }

    static function ProgressBar($progress, $label)
    {
        $progress = round(100 * $progress);
        echo "<div class='wpfilebase-progress'><div class='progress'><div class='bar' style='width: $progress%'></div></div><div class='label'><strong>$progress %</strong> ($label)</div></div>";
    }

}
