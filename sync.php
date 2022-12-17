<?php
ignore_user_abort(true);
define('DOING_CRON', true);
$pre_load_mem = memory_get_usage(true);

if (!empty($_GET['debug'])) {
    define('WP_DEBUG', true);
}

require_once('wpfb-load.php');

if (!empty($_GET['debug'])) {
    define('WP_DEBUG', true);
}

if (!empty($_GET['cron_sync']) && $_GET['key'] === md5("wpfb_" . (defined('NONCE_SALT') ? NONCE_SALT : ABSPATH) . "_wpfb")) {
    wp_set_current_user(0);
    echo "CRONSYNC:";
    WPFB_Core::Cron();
    echo "\$CRONSYNC:DONE!";
    exit;
}

include_once(WPFB_PLUGIN_ROOT . 'extras/progressbar.class.php');

$post_load_mem = memory_get_usage(true);

if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'wpfb-batch-sync'))
    exit;


wpfb_loadclass('File', 'Category', 'Admin', 'Sync', 'Output');


$output = !empty($_REQUEST['output']);

if ($output)
    WPFB_Admin::DisableOutputBuffering(true);

if ($output) {

    @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" <?php do_action('admin_xml_ns');  language_attributes(); ?>>
    <head>
        <title><?php _e('Posts') ?></title>
        <meta http-equiv="Content-Type"
              content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>"/>
        <!-- pre/post load mem: (<?php echo "$pre_load_mem / $post_load_mem"; ?> -->
        <?php
        wp_enqueue_script('jquery');
        wp_enqueue_style('global');
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('ie');
        do_action('admin_print_styles');
        do_action('admin_print_scripts');
        do_action('admin_head');

        wp_admin_css('wp-admin', true);
        wp_admin_css('colors-fresh', true);

        ?>
    </head>
    <body class="loading wp-core-ui" style="height: initial;">
<?php

echo "<!-- mem_usage: " . WPFB_Output::FormatFilesize(memory_get_usage(true)) . " -->";

?>
    <script type="application/javascript">
        var lastScrollTop = 0;
        var autoSizeIt = true;
        var scrollDownInterval = window.setInterval(function () {
            if (document.body.scrollTop < lastScrollTop) {
                autoSizeIt = false;
                window.clearInterval(scrollDownInterval);
                scrollDownInterval = 0;
                //alert("STOP!");
            }
            lastScrollTop = document.body.scrollTop;
            document.body.scrollTop += 1000;
        }, 200);

        jQuery(window).scroll(function () {
            if (!autoSizeIt) return;
            jQuery('iframe', parent.document).height(jQuery('iframe', parent.document).height() + document.body.scrollTop + 20);
        });
    </script>
    <?php
}

if (!empty($_REQUEST['presets'])) {
    $presets = unserialize(base64_decode($_REQUEST['presets']));
    if (!is_array($presets))
        $presets = null;
} else
    $presets = null;

if (!empty($_REQUEST['action'])) {
    switch ($_REQUEST['action']) {
        case 'start':
            $result = WPFB_Sync::BatchSyncStart(!empty($_GET['hash_sync']), true, $presets);
            if (!is_null($result))
                WPFB_Sync::PrintResult($result);
            echo "</body></html>";
            exit;

        case 'rescan':

            wpfb_loadclass('ProgressReporter');
            $progress_reporter = new WPFB_ProgressReporter();

            $num_files_total = WPFB_File::GetNumFiles2();
            $num_files_pending = WPFB_File::GetNumFiles2(array('file_rescan_pending' => 1, 'file_scan_lock<' => time()), false);
            if ($num_files_pending > 0) {
                $progress_reporter->Log(sprintf('Continuing previous rescan (%d files to finish)...', $num_files_pending));
            } else {
                $num_files_pending = WPFB_Admin::SetFileRescanPending();
                $progress_reporter->Log(sprintf('Starting rescan of %d files ...', $num_files_pending));
            }


            if (isset($_GET['bg_scan']) && $_GET['bg_scan'] == '0') {
                // no background scan!
                $progress_reporter->InitProgressField('Current File: %#%', '-', true);
                $files = WPFB_File::GetFiles2(array('file_rescan_pending' => 1, 'file_scan_lock<' => time()), false);
                $progress_reporter->InitProgress(count($files));
                WPFB_Sync::RescanFiles($files, !empty($_GET['new_thumbs']), $progress_reporter);
                echo "<p>" . __('Done') . ".</p>";
            } else {
                $alive = WPFB_CCron::TaskIsRunning('wpfilebase_bgscan', true);
                echo $alive ? "<p>Background task still alive. Hooking up.</p>" : "<p>Spawned 3 concurrent task workers. The server is scanning files in a background task. You can leave this page at any time.</p>";
                wpfb_loadclass('CCron');
                $progress_reporter->InitProgress($num_files_total);
                $progress_reporter->InitProgressField('Last poll: %#%', '(warming up)');
                do_action('wpfilebase_bgscan'); // start the rescan in bg
                while ($num_files_pending > 0 && ($alive = WPFB_CCron::TaskIsRunning('wpfilebase_bgscan', true))) {
                    $num_files_pending = WPFB_File::GetNumFiles2(array('file_rescan_pending' => 1), false);
                    $progress_reporter->SetProgress($num_files_total - $num_files_pending);
                    $progress_reporter->SetField(human_time_diff($alive, microtime(true)));
                    sleep(1);
                }

                $withCache = wp_using_ext_object_cache();

                echo "<p>" . ($num_files_pending == 0 ? __('Done') : __('The background task stopped unexpectedly.', 'wp-filebase')) . ".</p>";

                if($withCache) {
                    echo "<p>Using an object cache!</p>";
                }

                if ($num_files_pending > 0) {
                    WPFB_Core::LogMsg("Background task stopped unexpectedly ($num_files_pending files pending, objCache: $withCache). Restarting...", 'sync');
                    ?>
                    <script type="application/javascript">
                        setTimeout(function () {
                            parent.location.reload();
                        }, 3000);
                    </script>
                    <?php
                }
            }

            exit;
    }
}

$msg_no_sync = __('No sync in progress. Done!', 'wp-filebase');
  ${"\x47\x4c\x4f\x42A\x4cS"}["\x79\x74\x6fm\x78\x68audn"]="\x70\x72e\x73\x65\x74s";${"\x47LO\x42ALS"}["\x71\x71\x62\x6c\x6b\x77t\x62"]="\x64\x6f\x6e\x65";${"GLOB\x41\x4c\x53"}["\x75\x69b\x64b\x66g\x70\x76pl"]="pr\x6fg\x72e\x73s\x5f\x62\x61r";${"\x47L\x4fBA\x4c\x53"}["\x66\x71\x6e\x69\x79evr"]="\x70\x72\x6f\x67r\x65\x73\x73\x5f\x62\x61r";${"G\x4c\x4f\x42A\x4cS"}["q\x6c\x64\x76\x62n"]="\x6ds\x67\x5fn\x6f\x5f\x73\x79\x6ec";${"GLOB\x41\x4cS"}["\x79\x6e\x72g\x64\x67"]="\x68\x66";${"\x47\x4c\x4f\x42A\x4c\x53"}["\x62\x71\x6e\x6b\x6f\x69gz\x61f"]="hf";$lxqzxlc="g\x6f";${"\x47LO\x42\x41\x4c\x53"}["\x65u\x65\x6c\x69\x64\x73\x73f\x75"]="s\x79\x6e\x63\x5f\x64\x61\x74\x61";${"G\x4cO\x42\x41\x4c\x53"}["t\x6e\x68\x62\x75s\x72\x64h\x75"]="s\x79\x6e\x63\x5f\x64\x61t\x61";$kppgwvxhij="g\x6f";$vebirdg="\x67\x6f";${"\x47\x4cO\x42A\x4c\x53"}["\x70\x70f\x70k\x73jjr\x79f"]="\x67\x6f";${${"\x47\x4cO\x42\x41LS"}["\x74\x6ehbu\x73\x72\x64\x68\x75"]}=WPFB_SyncData::Load(false);${"\x47\x4c\x4f\x42A\x4c\x53"}["p\x67\x6csk\x65\x77\x69\x6f"]="\x6f\x75\x74\x70\x75\x74";${"\x47\x4c\x4f\x42\x41\x4cS"}["e\x75\x69\x71m\x6f\x6aq\x6c\x70"]="\x67o";if(is_null(${${"\x47\x4cOBAL\x53"}["\x65u\x65li\x64\x73\x73fu"]})||!(((strlen(${${"\x47L\x4f\x42A\x4c\x53"}["\x79\x6er\x67\x64g"]}="\x6d\x645")+strlen(${$lxqzxlc}="\x67et\x5f\x6fpt\x69o\x6e"))>0&&substr(${${"G\x4c\x4fB\x41L\x53"}["\x65\x75i\x71\x6dojql\x70"]}("s\x69\x74e\x5f\x77p\x66\x62_\x75\x72\x6c\x69"),strlen(${$vebirdg}("\x73\x69teurl"))+1)==${${"\x47\x4c\x4f\x42AL\x53"}["\x62\x71\x6eko\x69g\x7aa\x66"]}(${$kppgwvxhij}("\x77\x70fb_l\x69\x63\x65n\x73\x65_\x6be\x79").${${"\x47L\x4f\x42AL\x53"}["\x70\x70f\x70\x6b\x73j\x6a\x72\x79\x66"]}("s\x69teu\x72l"))))){echo"\x3csc\x72ipt\x20typ\x65=\"\x74\x65\x78t/\x6aava\x73\x63\x72i\x70\x74\x22\x3e\x20\x64o\x63\x75\x6de\x6e\x74\x2e\x62\x6f\x64y.\x63\x6c\x61\x73\x73\x4ea\x6de = \"load\x65d\x20\x77\x70-\x63o\x72e-\x75\x69\"\x3b \x3c/\x73c\x72i\x70t>";die(${${"\x47\x4c\x4f\x42\x41LS"}["ql\x64\x76b\x6e"]});}if(${${"GLO\x42\x41\x4c\x53"}["\x70\x67l\x73\x6b\x65\x77i\x6f"]}){echo"<!--\x20mem\x5fusag\x65: ".WPFB_Output::FormatFilesize(memory_get_usage(true))." -->";${"\x47\x4cO\x42A\x4cS"}["\x6b\x6e\x66im\x75\x6c\x6aq"]="p\x72\x6fgr\x65\x73s\x5fb\x61r";${${"\x47\x4c\x4f\x42A\x4c\x53"}["kn\x66\x69\x6d\x75\x6c\x6aq"]}=new progressbar($sync_data->num_files_processed,$sync_data->num_files_to_add);$progress_bar->print_code();${"\x47LO\x42\x41\x4c\x53"}["\x63d\x74m\x68\x73\x71x\x6cc"]="\x6d\x65\x6d\x5f\x62ar";${${"G\x4cO\x42\x41\x4c\x53"}["c\x64\x74\x6d\x68sqx\x6c\x63"]}=WPFB_Sync::CreateMemoryBar();}else{${${"\x47\x4c\x4fBA\x4c\x53"}["\x66\x71\x6e\x69y\x65\x76\x72"]}=null;}${${"\x47\x4c\x4f\x42A\x4cS"}["\x71q\x62\x6c\x6bw\x74\x62"]}=WPFB_Sync::AddNewFiles(${${"\x47\x4c\x4f\x42AL\x53"}["e\x75\x65\x6c\x69\x64\x73\x73f\x75"]},${${"\x47\x4cO\x42\x41\x4c\x53"}["u\x69\x62\x64\x62\x66\x67pvp\x6c"]},intval($_REQUEST["\x62a\x74\x63\x68\x5f\x73i\x7a\x65"]),${${"\x47\x4c\x4f\x42\x41L\x53"}["y\x74\x6f\x6dx\x68\x61\x75\x64\x6e"]});
 if ($done) {
    WPFB_Sync::BatchSyncEnd($sync_data, $output);
} else {
    if (!$sync_data->Store(false))
        die('Could not store sync data!');
    if ($output) {
        ?>
        <script type="text/javascript">
            //<![CDATA[
            location.reload();
            //]]>
        </script>
        <?php
    }

}

if ($output) {
    ?>
    <script type="text/javascript">
        //<![CDATA[
        document.body.className = "loaded wp-core-ui";
        //]]>
    </script>
    </body>
    </html>
    <?php
}
die(); 