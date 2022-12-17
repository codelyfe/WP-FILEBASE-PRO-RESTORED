<?php

class WPFB_Sync
{
    const MANY_FILES = 1000;
    const MANY_CATEGORIES = 100;

    const BATCH_SIZE = 409715200; // 400MiB
    const BATCH_TIME = 120; // 2minutes
    const HIGH_START_MEM = 100000000; // 100MB

    static $error_log_file;
    static $debug_output = false;

    const OLD_THUMB_SUFFIX = '/-([0-9]+)x([0-9]+)\.(jpg|jpeg|png|gif)$/i';

    static function InitClass()
    {
        wpfb_loadclass("Admin", "GetID3", "FileUtils", "Misc");
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        WPFB_Admin::DisableTimeouts();
        self::$error_log_file = WPFB_Core::GetLogFile('sync');
        @ini_set("error_log", self::$error_log_file);

        set_error_handler(array(__CLASS__, 'CaptureError'));
        set_exception_handler(array(__CLASS__, 'CaptureException'));
        register_shutdown_function(array(__CLASS__, 'CaptureShutdown'));

        if (self::$debug_output = (!empty($_GET['output'])
            || !empty($_GET['debug']))
        ) {
            @ini_set('display_errors', 1);
            @error_reporting(E_ALL);
        }

        // raise memory limit if needed
        if (WPFB_Misc::ParseIniFileSize(ini_get('memory_limit')) < 64000000) {
            @ini_set('memory_limit', '128M');
            @ini_set('memory_limit', '256M');
            @ini_set('memory_limit', '512M');
        }
    }

    private static function cleanPath($path)
    {
        return str_replace('//', '/', str_replace('\\', '/', $path));
    }

    public static function CaptureError($number, $message, $file, $line)
    {
        if ($number == E_STRICT || $number == E_NOTICE
            || $number == E_WARNING
        ) {
            return;
        }
        $error = array(
            'type' => $number,
            'message' => $message,
            'file' => $file,
            'line' => $line
        );
        if (self::$debug_output) {
            echo '<pre>ERROR:';
            print_r($error);
            echo '</pre>';
        }
        WPFB_Core::LogMsg('PHP ERROR:' . json_encode($error), 'sync');
    }

    public static function CaptureException($exception)
    {
        if (self::$debug_output) {
            echo '<pre>EXCEPTION:';
            print_r($exception);
            echo '</pre>';
        }
        WPFB_Core::LogMsg('EXCEPTION:' . str_replace(array("\n", "\r"), '<br>', print_r($exception, true)), 'sync');
    }

    public static function CaptureShutdown()
    {
        $error = error_get_last();
        if ($error
            && $error['type'] <= E_USER_ERROR
            && $error['type'] != E_COMPILE_WARNING
            && $error['type'] != E_CORE_WARNING
            && $error['type'] != E_NOTICE
            && $error['type'] != E_WARNING
        ) {
            if (self::$debug_output) {
                echo '<pre>PHP ERROR:';
                print_r($error);
                echo '</pre>';
            }
            WPFB_Core::LogMsg('SHUTDOWN ERROR:' . json_encode($error), 'sync');
        } else {
            return true;
        }
    }

    static function DEcho($str)
    {
        echo $str;
        @ob_flush();
        @flush();
    }

    /**
     * @param WPFB_SyncData $sync_data
     */
    private static function PreSync($sync_data)
    {
        self::PrintDebugTrace();

        // some syncing/updating
        if ($sync_data->num_db_files < self::MANY_FILES) {
            self::UpdateItemsPath();
        }

        WPFB_Admin::SyncCustomFields();
    }


    public static function list_files($folder = '', $levels = 100)
    {
        if (empty($folder))
            return false;

        if (!$levels)
            return false;

        $files = array();
        // if opendir fails, try to chmod and try again
        if (($dir = @opendir($folder)) || (is_dir($folder) && chmod($folder, octdec(WPFB_PERM_DIR)) && ($dir = @opendir($folder)))) {
            while (($file = readdir($dir)) !== false) {
                if (in_array($file, array('.', '..')))
                    continue;
                if (is_dir($folder . '/' . $file)) {
                    $files2 = self::list_files($folder . '/' . $file, $levels - 1);
                    if ($files2)
                        $files = array_merge($files, $files2);
                    else
                        $files[] = $folder . '/' . $file . '/';
                } else {
                    $files[] = $folder . '/' . $file;
                }
            }
        }
        @closedir($dir);
        return $files;
    }

    /**
     * @param WPFB_SyncData $sync_data
     * @param boolean $output
     */
    private static function SyncPhase1($sync_data, $output)
    {
        self::PrintDebugTrace("sync_phase_1");

        if ($output) {
            $ms = self::GetMemStats();
            self::DEcho('<p>'
                . sprintf(__('Starting sync. Memory usage: %s - Limit: %s',
                    'wp-filebase'), WPFB_Output::FormatFilesize($ms['used']),
                    WPFB_Output::FormatFilesize($ms['limit'])) . ' '
                . (($ms['used'] > self::HIGH_START_MEM)
                    ? __('<b>Note:</b> The memory usage seems to be quite high. Please disable other plugins to lower the memory consumption.')
                    : '') . '</p>');
        }


        self::CheckChangedFiles($sync_data, $output);

        // delete missing categories
        foreach ($sync_data->cats as $id => $cat) {
            $cat_path = $cat->GetLocalPath(true);
            if (!@is_dir($cat_path) || !@is_readable($cat_path)) {
                if (WPFB_Core::$settings->remove_missing_files) {
                    $cat->Delete();
                }
                $sync_data->log['missing_folders'][$id] = $cat;
                continue;
            }
        }

        if ($output) {
            self::DEcho('<p>' . __('Populating file tree...', 'wp-filebase')
                . ' ');
        }

        self::PrintDebugTrace("new_files");

        // search for not added files
        $upload_dir = self::cleanPath(WPFB_Core::UploadDir());
        $all_files = self::cleanPath(self::list_files($upload_dir));
        $sync_data->num_all_files = count($all_files);

        if ($output) {
            self::DEcho('(' . sprintf(__('%d files in upload directory',
                    'wp-filebase'), $sync_data->num_all_files)
                . ') Filtering ... ');
        }

        if ($sync_data->num_all_files > 0) {
            wpfb_loadclass('ProgressReporter');
            $progress_reporter = new WPFB_ProgressReporter(!$output);
            $progress_reporter->InitProgress($sync_data->num_all_files);
            $progress_reporter->InitProgressField('Current File: %#%', '-',
                true);
        }

        $num_new_files = 0;
        $ulp_len = strlen(trailingslashit($upload_dir));

        // 1ps filter	 (check extension, special file names, and filter existing file names and thumbnails)
        $fext_blacklist = array_map('strtolower', array_map('trim',
            explode(',', WPFB_Core::$settings->fext_blacklist)));
        for ($i = 0; $i < $sync_data->num_all_files; $i++) {
            // $fn = $upload_dir.implode('/',array_map('urlencode', explode('/', substr($all_files[$i], strlen($upload_dir)))));

            $fn = $all_files[$i];
            $fbn = basename($fn);
            $fn_rel = substr($fn, $ulp_len);
            //$fbn_length = strlen($fbn);

            $progress_reporter->SetProgress($i);
            $progress_reporter->SetField($fn_rel);

            if (strlen($fn) < 2 || $fbn{0} == '.'
                || strpos($fn, '/.tmp') !== false                 || strpos($fn, '/.svn') !== false
                || strpos($fn, '/.git') !== false                || $fbn == '_wp-filebase.css'
                || strpos($fbn, '_caticon.') !== false
                || strpos($fbn, '_wpfb_') === 0
                || strpos($fbn, '.__info.xml') !== false
                || isset($sync_data->known_filenames[$fn_rel])
                || !is_file($fn)
                || !is_readable($fn)
                || (!empty($fext_blacklist)
                    && self::fast_in_array(trim(strrchr($fbn, '.'), '.'),
                        $fext_blacklist)) // check for blacklisted extension
            ) {
                continue;
            }

            // look for an equal missing file -> this file has been moved then!
            foreach ($sync_data->missing_files as $mf) {
                if ($fbn == $mf->file_name && filesize($fn) == $mf->file_size
                    && filemtime($fn) == $mf->file_mtime
                ) {
                    // make sure cat tree to new file location exists, and set the cat of the moved file
                    $cat_id = WPFB_Admin::CreateCatTree($fn);
                    if (!empty($cat_id['error'])) {
                        $sync_data->log['error'][] = $cat_id['error'];
                        continue 2;
                    }

                    $result = $mf->ChangeCategoryOrName($cat_id, null, true);
                    if (is_array($result) && !empty($result['error'])) {
                        $sync_data->log['error'][] = $result['error'];
                        continue 2;
                    }

                    // rm form missing list, add to changed
                    unset($sync_data->missing_files[$mf->file_id]);
                    $sync_data->log['changed'][$mf->file_id] = $mf;

                    continue 2;
                }
            }

            // TODO: should rename here if special chars and set file_orginial_name
            $sync_data->new_files[$num_new_files] = $fn;
            $num_new_files++;
        }

        $progress_reporter->SetProgress($sync_data->num_all_files);

        if ($output) {
            self::DEcho('- done!</p>');
        }

        self::PrintDebugTrace("new_files_end");


        foreach ($sync_data->missing_files as $mf) {
            if (WPFB_Core::$settings->remove_missing_files) {
                $mf->Remove();
            } elseif (!$mf->file_offline) {
                $mf->file_offline = true; // set offline if not found
                if (!$mf->IsLocked()) {
                    $mf->DBSave();
                }
            }
            $sync_data->log['missing_files'][$mf->file_id] = $mf;
        }

        if (count($sync_data->missing_files)) {
            if ($output) {
                self::DEcho('<p>Missing files processed!</p>');
            }
        }

        self::PrintDebugTrace("missing_files_end");


        $sync_data->num_files_to_add = $num_new_files;

        // handle thumbnails
        self::GetThumbnails($sync_data);

        if ($output) {
            self::DEcho('<p>Thumbnails processed!</p>');
        }

        self::PrintDebugTrace("post_get_thumbs");
    }

    /**
     *
     * @param type $steps
     *
     * @return \progressbar
     */
    private static function NewProgressBar($steps)
    {
        if (!class_exists('progressbar')) {
            include_once(WPFB_PLUGIN_ROOT . 'extras/progressbar.class.php');
        }
        $progress_bar = new progressbar(0, $steps);
        $progress_bar->print_code();

        return $progress_bar;
    }

    static function Sync(
        $hash_sync = false,
        $output = false
        ,
        $presets = null
            )
    {
        self::PrintDebugTrace();

        $output && self::UpdateMemBar();
        $output && self::DEcho('Creating Sync instance ... ');

        wpfb_loadclass('File', 'Category');
        $sync_data = new WPFB_SyncData(true);

        $sync_data->hash_sync = $hash_sync;

        if ($output)
            self::DEcho('instance created!<br>');

        self::PreSync($sync_data);
        self::SyncPhase1($sync_data, $output);

        if ($output && $sync_data->num_files_to_add > 0) {
            echo "<p>";
            printf(__('%d Files found, %d new.', 'wp-filebase'),
                $sync_data->num_all_files, $sync_data->num_files_to_add);
                        self::DEcho(__('Adding files with background scanning.',
                'wp-filebase'));
                        echo "</p>";
            $progress_bar = self::NewProgressBar($sync_data->num_files_to_add);
        } else {
            $progress_bar = null;
            if ($output) {
                self::DEcho('done!</p>');
            }
        }

        self::PrintDebugTrace("pre_add_files");


        $mem_bar = $output ? self::CreateMemoryBar() : null;
        self::AddNewFiles($sync_data, $progress_bar, 0, $presets);
        self::PostSync($sync_data, $output);

        return $sync_data->log;
    }

    static function BatchSync(
        $hash_sync = false,
        $output = false,
        $presets = null
    )
    {
        if (!$output) {
            self::BatchSyncStart($hash_sync, false, $presets);
        } else {
            $sync_url = WPFB_Core::PluginUrl('sync.php?_wpnonce='
                . wp_create_nonce('wpfb-batch-sync')
                . '&action=start&batch_sync=1&output=1&no-ob=1&hash_sync='
                . $hash_sync . '&debug=' . (int)(!empty($_GET['debug']))
            );
            if (!empty($presets)) {
                $sync_url .= '&presets='
                    . urlencode(base64_encode(serialize($presets)));
            }
            echo '<iframe style="border:0;overflow:hidden" width="100%" height="100%" src="'
                . $sync_url . '" id="sync-start-frame"></iframe>';
            ?>
            <script type="text/javascript">
                //setInterval((function(){
                //		var f = document.getElementById('sync-start-frame');
                //		f.style.height = f.contentDocument['body'].offsetHeight + 'px';
                //	}), 500);
            </script>
            <?php
        }
    }

    static function BatchSyncStart(
        $hash_sync = false,
        $output = false,
        $presets = null
    )
    {
        $output && self::UpdateMemBar();
        $output && self::DEcho('Creating Sync instance ... ');

        $sync_data = new WPFB_SyncData(true);
        $sync_data->hash_sync = $hash_sync;

        if (!empty($presets)) {
            self::DEcho('Presets: ' . json_encode(array_filter($presets)));
        }

        self::PreSync($sync_data);
        self::SyncPhase1($sync_data, $output);

        unset($sync_data->cats);

        self::PrintDebugTrace("phase1_done");

        if ($sync_data->num_files_to_add > 0 && !$sync_data->Store(true)) {
            self::PrintDebugTrace("batch_running");
            if ($output) {
                self::DEcho('<p>'
                    . __('A Batch sync is already in progress. Continuing...',
                        'wp-filebase') . '</p>');
            }
            unset($sync_data);
            $sync_data = WPFB_SyncData::Load();
            if (empty($sync_data) || $sync_data->num_files_to_add == 0) {
                WPFB_SyncData::DeleteStorage();
                if ($output) {
                    self::DEcho('<p>'
                        . __('Batch progress invalid. Aborted. Please try again.',
                            'wp-filebase') . '</p>');
                }

                return false;
            }
        }

        self::PrintDebugTrace("sync_data_saved");

        if ($output && $sync_data->num_files_to_add > 0) {
            echo "<p>";
            printf(__('%d Files found, %d new.', 'wp-filebase'),
                $sync_data->num_all_files, $sync_data->num_files_to_add);
                        self::DEcho(' Adding files with background scanning.');
                        echo "</p>";

            $sync_url = WPFB_Core::PluginUrl('sync.php?_wpnonce='
                . wp_create_nonce('wpfb-batch-sync') . '&batch_size='
                . self::BATCH_SIZE . '&no-ob=1&debug='
                . ((int)!empty($_GET['debug'])));
            if ($output) {
                $sync_url .= '&output=1';
            }
            if (!empty($presets)) {
                $sync_url .= '&presets='
                    . urlencode(base64_encode(serialize($presets)));
            }

            if ($output) {
                echo '<iframe style="border:0;overflow:hidden" width="100%" height="300px" src="'
                    . $sync_url . '" id="sync-frame"></iframe>';
                ?>
                <script type="text/javascript">
                    //<![CDATA[
                    jQuery('#sync-frame').load(function () {
                        if (this.contentDocument.body.className.indexOf("loaded") == -1) {
                            <?php if (empty($_GET['debug'])) { ?>
                            this.contentDocument.location.reload(true);
                            <?php } else { ?>
                            alert('LOAD failed!');
                            <?php } ?>
                        }
                    });
                    //]]>
                </script>
                <?php
            } else {
                $result = wp_remote_post($sync_url, array(
                    'timeout' => 0,
                    'body' => array(//'request' => serialize($args)
                    )
                ));
                //print_r($result);
            }
        } else {
            if ($output) {
                self::DEcho('done!</p>');
            }
            self::PostSync($sync_data, $output);

            return $sync_data->log;
        }

        return null;
    }

    static function BatchSyncEnd($sync_data, $output)
    {
        self::PostSync($sync_data, $output);
        WPFB_Sync::PrintResult($sync_data->log);
        WPFB_SyncData::DeleteStorage();
    }

    /**
     *
     * @param WPFB_SyncData $sync_data
     * @param boolean $output
     */
    private static function PostSync($sync_data, $output)
    {
        self::PrintDebugTrace("post_sync");

        if ($output) {
            self::CreateMemoryBar();
        }
        wpfb_loadclass('RemoteSync');
        self::PrintDebugTrace("remote_syncing");
        WPFB_RemoteSync::SyncAll($output);
        self::PrintDebugTrace("post_remote_sync");
        // chmod
        if ($output) {
            self::DEcho('<p>Setting permissions (files: 0' . (WPFB_PERM_FILE) . ', folders: 0' . (WPFB_PERM_DIR) . ')...');
        }
        $sync_data->log['warnings']
            += self::Chmod(self::cleanPath(WPFB_Core::UploadDir()),
            array_filter(array_keys($sync_data->known_filenames)));
        if ($output) {
            self::DEcho('done!</p>');
        }

        // sync categories
        if ($output) {
            self::DEcho('<p>Syncing categories... ');
        }
        $sync_data->log['updated_categories'] = self::SyncCats($sync_data->cats,
            $output);
        if ($output) {
            self::DEcho('done!</p>');
        }

        if (
            ($num_rescan
                = WPFB_File::GetNumFiles2(array(
                'file_rescan_pending' => 1,
                'file_scan_lock<' => time()
            ), false)) > 0
        ) {
            if ($output) {
                self::DEcho('<p>Delayed scanning of ' . $num_rescan
                    . ' files ... ');
            }
            self::RescanStart();
            if ($output) {
                self::DEcho('done!</p>');
            }
        }
        wpfb_call('Setup', 'ProtectUploadPath');
        self::PrintDebugTrace("update_tags");
        WPFB_File::UpdateTags();

        $mem_peak = max($sync_data->mem_peak, memory_get_peak_usage());

        if ($output) {
            printf("<p>" . __('Sync Time: %01.2f s, Memory Peak: %s',
                    'wp-filebase') . "</p>",
                microtime(true) - $sync_data->time_begin,
                WPFB_Output::FormatFilesize($mem_peak));
        }
    }

    static function UpdateItemsPath($files = null, $cats = null)
    {
        wpfb_loadclass('File', 'Category');
        if (is_null($files)) {
            $files = WPFB_File::GetFiles2();
        }
        if (is_null($cats)) {
            $cats = WPFB_Category::GetCats();
        }
        foreach (array_keys($cats) as $i) {
            $cats[$i]->Lock(true);
        }
        foreach (array_keys($files) as $i) {
            $files[$i]->GetLocalPath(true);
        }
        foreach (array_keys($cats) as $i) {
            $cats[$i]->Lock(false);
            $cats[$i]->DBSave();
        }
    }

    /**
     *
     * @param WPFB_SyncData $sync_data
     * @param boolean $output
     */
    private static function CheckChangedFiles($sync_data, $output)
    {
        if ($sync_data->num_db_files == 0)
            return;

        if ($output) {
            self::DEcho('<p>' . sprintf(__('Checking %d files for changes...',
                    'wp-filebase'), ($sync_data->num_db_files)) . ' ');
        }

        $sync_id3 = !WPFB_Core::$settings->disable_id3;
        $upload_dir = trailingslashit(self::cleanPath(WPFB_Core::UploadDir()));
        $thumb_dir = trailingslashit(self::cleanPath(empty(WPFB_Core::$settings->thumbnail_path) ? WPFB_Core::UploadDir() : path_join(ABSPATH, WPFB_Core::$settings->thumbnail_path)));

        wpfb_loadclass('ProgressReporter');
        $progress_reporter = new WPFB_ProgressReporter(!$output);
        $progress_reporter->InitProgress($sync_data->num_db_files);
        $progress_reporter->InitProgressField('Current File: %#%', '-',
            true);

        $i = 0;

        /*
         * if ($file->file_category > 0 && is_null($file->GetParent())) {
                $sync_data->log['warnings'][]
                    = sprintf(__('Category (ID %d) of file %s does not exist!',
                    'wp-filebase'), $file->file_category,
                    $file->GetLocalPathRel());
            }

         */

        foreach ($sync_data->db_file_states as $fs) {
            $file_path = $upload_dir . $fs->path_rel;
            $rel_file_path = $fs->path_rel;

            if (empty($fs->path_rel)) {
                $rel_file_path = $fs->getFile()->GetLocalPath(true);
            }

            $progress_reporter->SetProgress(++$i);
            $progress_reporter->SetField($rel_file_path);

            $sync_data->known_filenames[$rel_file_path] = 1;
            if ($fs->thumb_file_name) {
                $rel_thumb_path = $fs->getThumbPath();
                $sync_data->known_filenames[$rel_thumb_path] = 1;

                // remove thumb if missing
                if (!file_exists($thumb_dir . $rel_thumb_path)) {

                    // move thumbnail from old location
                    if ($upload_dir != $thumb_dir && file_exists($upload_dir . $rel_thumb_path)) {
                        $n = $thumb_dir . $rel_thumb_path;
                        is_dir(dirname($n)) || WPFB_Admin::Mkdir(dirname($n));
                        rename($upload_dir . $rel_thumb_path, $n);
                    } else {
                        $fs->getFile()->file_thumbnail = '';
                        $fs->getFile()->DBSave();
                    }

                    $sync_data->log['changed'][$fs->id] = $fs->getFile();
                }
            }

            if ($fs->has_uri) {
                continue;
            }

            if (!@is_file($file_path) || !@is_readable($file_path)) {
                $sync_data->missing_files[$fs->id] = $fs->getFile();
                continue;
            }


            $file_hash = $sync_data->hash_sync
                ? WPFB_Admin::fileMD5($file_path) : '';

            $file_size = WPFB_FileUtils::GetFileSize($file_path);
            $file_mtime = filemtime($file_path);

            if (($sync_data->hash_sync && $fs->hash != $file_hash)
                || $fs->size != $file_size
                || $fs->mtime != $file_mtime
            ) {
                $file = $fs->getFile();
                $file->file_size = $file_size;
                $file->file_mtime = $file_mtime;


                $file->file_hash_sha256 = $sync_data->hash_sync ? hash_file('sha256', $file_path) : '';
                                $file->file_hash = $sync_data->hash_sync ? $file_hash : '';
                $file->file_rescan_pending = 1;
                
                $res = $file->DBSave();
                if (!empty($res['error'])) {
                    $sync_data->log['error'][$fs->id] = $res['error']
                        . " (file $rel_file_path)";
                } else {
                    $sync_data->log['changed'][$fs->id] = $fs->getFile();
                }
            }
        }


        if ($output) {
            self::DEcho('- done!</p>');
        }
    }

    static function AddNewFiles(
        $sync_data,
        $progress_bar = null,
        $max_batch_size = 0 ,
        $presets = null    )
    {
        self::PrintDebugTrace();
        $keys = array_keys($sync_data->new_files);
        $upload_dir = self::cleanPath(WPFB_Core::UploadDir());
        $upload_dir_len = strlen($upload_dir);
        $batch_size = 0;

        $start_time = $cur_time = time();

        WPFB_Category::DisableBubbling();

        foreach ($keys as $i) {
            if (!empty($progress_bar)) {
                $progress_bar->step();
            }

            $fn = $sync_data->new_files[$i];
            $rel_path = substr($fn, $upload_dir_len);
            unset($sync_data->new_files[$i]);
            if (empty($fn) || isset($sync_data->known_filenames[$rel_path])) {
                continue;
            }

            // skip files that where already added, for some reason
            if (is_null($ex_file = WPFB_Item::GetByPath($rel_path))) {
                self::PrintDebugTrace("add_existing_file:$fn");
                $res = WPFB_Admin::AddExistingFile($fn,
                    empty($sync_data->thumbnails[$fn]) ? null
                        : $sync_data->thumbnails[$fn] ,
                    $presets);

                self::PrintDebugTrace("added_existing_file");
                if (empty($res['error'])) {
                    $sync_data->log['added'][] = empty($res['file'])
                        ? substr($fn, $upload_dir_len) : $res['file'];

                    $sync_data->known_filenames[$rel_path] = 1;
                    if (!empty($res['file'])
                        && $res['file']->GetThumbPath()
                    ) {
                        $sync_data->known_filenames[substr(self::cleanPath($res['file']->GetThumbPath()),
                            $upload_dir_len)]
                            = 1;
                    }
                } else {
                    $sync_data->log['error'][] = $res['error'] . " (file $fn)";
                }
            } else {
                //$res = array('file' => $ex_file);
                $sync_data->log['added'][] = $ex_file;
                $sync_data->known_filenames[$rel_path] = 1;
            }

            $sync_data->num_files_processed++;


            if (!empty($res['file'])) {
                $batch_size += $res['file']->file_size;
                if ($max_batch_size > 0 && $batch_size > $max_batch_size) {
                    return false;
                }
            }

            if (($i % 5) == 0) {
                $cur_time = time();
            }

            if ($max_batch_size > 0
                && (self::MemIsCritically()
                    || ($cur_time - $start_time) > self::BATCH_TIME)
            ) {
                return false;
            }

            if ($progress_bar) {
                self::UpdateMemBar();
            }
        }

        if (!empty($progress_bar)) {
            $progress_bar->complete();
        }

        return true;
    }

    private static function MemIsCritically()
    {
        $stats = self::GetMemStats();
        $r = $stats['used'] / $stats['limit'];
        $free = $stats['limit'] - $stats['used'];

        return ($r >= 0.9 || $free < 5242880);
    }

    static $mem_bar = null;

    static function CreateMemoryBar()
    {
        if (!empty(self::$mem_bar) && !is_null(self::$mem_bar)) {
            return self::$mem_bar;
        }

        if (!class_exists('progressbar')) {
            include_once(WPFB_PLUGIN_ROOT . 'extras/progressbar.class.php');
        }

        $ms = self::GetMemStats();
        self::$mem_bar = new progressbar($ms['used'], $ms['limit'], 200, 20,
            '#d90', 'white', 'wpfb-progress-bar-mem');
        echo "<div><br /></div>";
        echo "<div>Memory Usage (limit = "
            . WPFB_Output::FormatFilesize($ms['limit']) . "):</div>";
        self::$mem_bar->print_code();
        echo "<div><br /></div>";

        return self::$mem_bar;
    }

    static function UpdateMemBar()
    {
        if (!empty(self::$mem_bar)) {
            $ms = self::GetMemStats();
            self::$mem_bar->set($ms['used']);
        } else {
            self::CreateMemoryBar();
        }
    }

    static function GetMemStats()
    {
        static $limit = -2;
        if ($limit == -2) {
            $limit = wpfb_call("Misc", "ParseIniFileSize",
                ini_get('memory_limit'));
        }

        return array(
            'limit' => $limit,
            'used' => max(memory_get_usage(true), memory_get_usage())
        );
    }

    /**
     * @param WPFB_SyncData $sync_data
     */
    static function GetThumbnails($sync_data)
    {
        $num_files_to_add = $num_new_files = count($sync_data->new_files);

        $upload_dir = self::cleanPath(WPFB_Core::UploadDir());
        $upload_dir_len = strlen(trailingslashit($upload_dir));

        // look for thumnails
        // find files that have names formatted like thumbnails e.g. file-XXxYY.(jpg|jpeg|png|gif)
        for ($i = 1; $i < $num_new_files; $i++) {
            $len = strrpos($sync_data->new_files[$i], '.');

            // file and thumbnail should be neighbours in the list, so only check the prev element for matching name
            if (strlen($sync_data->new_files[$i - 1]) > ($len + 2)
                && substr($sync_data->new_files[$i - 1], 0, $len)
                == substr($sync_data->new_files[$i], 0, $len)
                && isset($sync_data->known_filenames[substr($sync_data->new_files[$i - 1],
                        $upload_dir_len)])
            ) {
                $suffix = substr($sync_data->new_files[$i - 1], $len);

                $matches = array();
                if (preg_match(WPFB_File::THUMB_REGEX, $suffix, $matches)
                    && ($is = getimagesize($sync_data->new_files[$i - 1]))
                ) {
                    if ($is[0] == $matches[1] && $is[1] == $matches[2]) {
                        //ok, found a thumbnail here
                        $sync_data->thumbnails[$sync_data->new_files[$i]]
                            = basename($sync_data->new_files[$i - 1]);
                        $sync_data->new_files[$i - 1]
                            = ''; // remove the file from the list
                        $sync_data->num_files_to_add--;
                        continue;
                    }
                }
            }
        }


        if (WPFB_Core::$settings->base_auto_thumb) {
            for ($i = 0; $i < $num_new_files; $i++) {
                $len = strrpos($sync_data->new_files[$i], '.');
                $ext = strtolower(substr($sync_data->new_files[$i], $len + 1));

                if ($ext != 'jpg' && $ext != 'png' && $ext != 'gif') {
                    $prefix = substr($sync_data->new_files[$i], 0, $len);

                    for ($ii = $i - 1; $ii >= 0; $ii--) {
                        if (substr($sync_data->new_files[$ii], 0, $len)
                            != $prefix
                        ) {
                            break;
                        }
                        $e = strtolower(substr($sync_data->new_files[$ii],
                            $len + 1));
                        if ($e == 'jpg' || $e == 'png' || $e == 'gif'
                            || $e == 'thumb.jpg'
                            || $e == 'thumb.png'
                            || strpos($e, 'jpg_thumb-') === 0
                        ) {
                            $sync_data->thumbnails[$sync_data->new_files[$i]]
                                = basename($sync_data->new_files[$ii]);
                            $sync_data->new_files[$ii]
                                = ''; // remove the file from the list
                            $sync_data->num_files_to_add--;
                            break;
                        }
                    }

                    for ($ii = $i + 1; $ii < $num_new_files; $ii++) {
                        if (substr($sync_data->new_files[$ii], 0, $len)
                            != $prefix
                        ) {
                            break;
                        }
                        $e = strtolower(substr($sync_data->new_files[$ii],
                            $len + 1));
                        if ($e == 'jpg' || $e == 'png' || $e == 'gif'
                            || $e == 'thumb.jpg'
                            || $e == 'thumb.png'
                            || strpos($e, 'jpg_thumb-') === 0
                        ) {
                            $sync_data->thumbnails[$sync_data->new_files[$i]]
                                = basename($sync_data->new_files[$ii]);
                            $sync_data->new_files[$ii]
                                = ''; // remove the file from the list
                            $sync_data->num_files_to_add--;
                            break;
                        }
                    }
                }
            }
        }


        // dont add files with a thumb-style filename
        for ($i = 0; $i < $num_new_files; $i++) {
            $s = substr($sync_data->new_files[$i], -10);
            $b = basename($sync_data->new_files[$i]);
            if ($s == '.thumb.jpg' || $s == '.thumb.png' || $s == '_thumb.jpg'
                || strpos($b, '.jpg_thumb-') !== false
                || strncmp($b, "thumb_", 6) === 0
            ) {
                $sync_data->new_files[$i] = '';
                $sync_data->num_files_to_add--;
            }
        }

        // FIX: check for db files with a thumbnail-style file name and assign it to a file with similar name
        foreach ($sync_data->db_file_states as $fs_thumb) {
            $matches = array();
            if (preg_match(self::OLD_THUMB_SUFFIX, $fs_thumb->path_rel, $matches)
                && ($file = $sync_data->getDbStateByPathPrefix(substr($fs_thumb->path_rel, 0, -strlen($matches[0])) . '.'))
                && $file->getFile()->IsLocal()
                && ($is = getimagesize($fs_thumb->getFile()->GetLocalPath()))
                && $is[0] == $matches[1] && $is[1] == $matches[2]
            ) {
                                $fs_thumb->getFile()->DeleteThumbnail();
                $file->getFile()->DeleteThumbnail();
                $file->getFile()->file_thumbnail = basename($fs_thumb->getFile()->GetLocalPath());
                $file->getFile()->DBSave(true);
                $fs_thumb->getFile()->Remove(false, true);
            }
        }
    }

    static function SyncCats($cats = null, $output = false)
    {
        $updated_cats = array();
        if (is_null($cats)) {
            $cats = WPFB_Category::GetCats();
        }

        if (count($cats) == 0) {
            return array();
        }

        if ($output) {
            wpfb_loadclass('ProgressReporter');
            $progress_reporter = new WPFB_ProgressReporter();
            $progress_reporter->InitProgress(count($cats));
            $progress_reporter->InitProgressField('Current Category: %#%', '-',
                true);
        }

        $i = 0;

        // sync file count

        foreach (array_keys($cats) as $id) {
            /* @var $cat WPFB_Category */
            $cat = $cats[$id];

            $child_files
                = $cat->GetChildFilesFast(); // $cat->GetChildFiles(false);
            $num_files_total
                = (int)count($cat->GetChildFilesFast(true)); // (int)count($cat->GetChildFiles(true));

            $num_files = (int)count($child_files);

            if ($num_files != $cat->cat_num_files
                || $num_files_total != $cat->cat_num_files_total
            ) {
                $cat->cat_num_files = $num_files;
                $cat->cat_num_files_total = $num_files_total;
                $cat->DBSave();
                $updated_cats[] = $cat;
            }

            // update category names
            if ($child_files) {
                foreach ($child_files as $file) {
                    if ($file->file_category_name != $cat->GetTitle()) {
                        $file->file_category_name = $cat->GetTitle();
                        if (!$file->IsLocked()) {
                            $file->DBSave();
                        }
                    }
                }
            }

            if (is_dir($cat->GetLocalPath())
                && is_writable($cat->GetLocalPath())
            ) {
                @chmod($cat->GetLocalPath(), octdec(WPFB_PERM_DIR));
            }

            if ($output) {
                $progress_reporter->SetProgress(++$i);
                $progress_reporter->SetField($cat->GetLocalPathRel());
                self::UpdateMemBar();
            }
        }

        return $updated_cats;
    }

    static function RescanStart()
    {
        $sync_url = WPFB_Core::PluginUrl('sync.php?_wpnonce='
            . wp_create_nonce('wpfb-batch-sync')
            . '&action=rescan&output=1&no-ob=1&new_thumbs='
            . ((int)!empty($_GET['new_thumbs'])) . '&debug='
            . ((int)!empty($_GET['debug'])));
        echo '<iframe style="border:0;overflow:hidden; width:100%; height:400px;" src="'
            . $sync_url . '"></iframe>';
    }

    /**
     *
     * @staticvar int $set_size
     *
     * @param type $max_runtime
     * @param WPFB_CCronWorker $worker
     *
     * @return boolean
     */
    static function BgScan($max_runtime, $worker)
    {
        static $set_size = 4;

        $t_start = time();
        while ((time() - $t_start) <= $max_runtime) {
            // get a set of files that needs rescan
            $files = WPFB_File::GetFiles2(array(
                'file_size>' => 0,
                //'file_offline' => '0',
                'file_rescan_pending>' => 0,
                'file_scan_lock<' => time()
            ), false, null, $set_size);
            if (count($files) == 0) // quit the loop
            {
                WPFB_Core::LogMsg("No more files to scan (BgW {$worker})", 'sync');
                return true;
            }

            // try to lock the set and only process files that can be locked
            $locked_files = array();
            foreach ($files as $file) {
                if ($file->TryScanLock()) {
                    $locked_files[] = $file;
                }
            }

            // if no file can be locked, wait a bit and retry
            if (empty($locked_files)) {
                sleep(5);
                $worker->poll();
                continue;
            }

            foreach ($locked_files as $file) {
                WPFB_Core::LogMsg("ScanFile $file (BgW {$worker})", 'sync');
                $res = self::ScanFile($file, false, false); // ignoring result
                if (!$res) {
                    WPFB_Core::LogMsg("ERROR ScanFile $file (BgW {$worker}) returned false!", 'sync');
                }
                $worker->poll();
            }
        }

        return false;
    }

    /**
     *
     * @param type $files
     * @param type $new_thumb
     * @param WPFB_ProgressReporter $progress_reporter
     */
    static function RescanFiles(
        $files = null,
        $new_thumb = false,
        $progress_reporter = null
    )
    {
        if (empty($files)) {
            $files = WPFB_File::GetFiles2(null, true);
        }
        $i = 0;
        foreach ($files as $file) {
            if (!is_null($progress_reporter)) {
                $progress_reporter->SetProgress(++$i);
                $progress_reporter->SetField($file->GetLocalPathRel());
                self::UpdateMemBar();
            }

            $res = self::ScanFile($file, $new_thumb); // this should not be async!
            if (!$res && $progress_reporter) {
                $progress_reporter->LogError(sprintf(__('Scanning file %s failed!',
                    'wp-filebase'), $file->GetLocalPathRel()));
            }
        }

        flush_rewrite_rules();
    }

    /**
     * 1. Checks if file is actually present and readable on file system and either sets file offline or completly removes it
     * 2. If file hash not set, calculate it
     * 3. Try to generate a thumbnail if it does not exists
     * 4. Update ID3 info (with _async_ RPC if enabled)
     *
     * @param WPFB_File $file
     * @param bool $forced_refresh_thumb
     *
     * @return bool
     */
    static function ScanFile(
        $file,
        $forced_refresh_thumb = false
    )
    {
        $forced_refresh_thumb = $forced_refresh_thumb || ($file->file_rescan_pending > 1);
        $file->file_rescan_pending = max($file->file_rescan_pending, $forced_refresh_thumb ? 2 : 1);


        if (!$file->TryScanLock()) {
            WPFB_Core::LogMsg("ERROR: ScanFile $file locking failed!", 'sync');
            return false;
        }

        $file_path = $file->GetLocalPath();

        if (!$file->IsLocal()) {
            $res = WPFB_Admin::SideloadFile($file, $file_path);
            if ($res['error']) {
                WPFB_Core::LogMsg("ERROR: ScanFile($file) download {$file->GetRemoteUri(false)} failed {$res['error']}!",
                    'sync');

                $file->file_rescan_pending = 0;
                $file->DBSave(true);

                return false;
            }
        } elseif (!is_file($file_path) || !is_readable($file_path)) {
            if (WPFB_Core::$settings->remove_missing_files) {
                WPFB_Core::LogMsg("ScanFile($file) removing missing file!",
                    'sync');
                $file->Remove();

                return true;
            } else {
                $file->file_offline = true;
                $file->file_mtime = 0;
                $file->DBSave(true);

                return true;
            }
        }

        if (filesize($file_path) == 0) {
            WPFB_Core::LogMsg("ScanFile($file) file empty, skipping!", 'sync');
            $file->file_rescan_pending = 0;
            $file->file_size = 0;
            $file->DBSave(true);
            return true;
        }


        if (empty($file->file_hash)) {
            $file->file_hash = WPFB_Admin::fileMD5($file->GetLocalPath());
        }

        if (empty($file->file_hash_sha256)) {
            $file->file_hash_sha256 = hash_file('sha256', $file->GetLocalPath());
        }

        if (!empty($file->file_thumbnail) && !is_file($file->GetThumbPath())) {
            $file->file_thumbnail = '';
        }

        if (empty($file->file_thumbnail) || $forced_refresh_thumb) {
            $file->Lock(true);
            $file->CreateThumbnail(); // this only deltes old thumb if success
            $file->Lock(false);

            if (WPFB_Core::$settings->base_auto_thumb
                && (empty($file->file_thumbnail)
                    || !is_file($file->GetThumbPath()))
            ) {
                $pwe = substr($file->GetLocalPath(), 0,
                    strrpos($file->GetLocalPath(), '.') + 1);
                if ($pwe
                    && (
                        file_exists($thumb = $pwe . 'png')
                        || file_exists($thumb = $pwe . 'thumb.png')
                        || file_exists($thumb = $pwe . 'jpg')
                        || file_exists($thumb = $pwe . 'thumb.jpg')
                        || file_exists($thumb = $pwe . 'gif')
                        || file_exists($thumb = $pwe . 'thumb.gif'))
                ) {
                    $file->file_thumbnail = basename($thumb);
                    $dest_thumb = $file->GetThumbPath(true);
                    if ($dest_thumb != $thumb) {
                        $dir = dirname($dest_thumb);
                        if (!is_dir($dir)) {
                            WPFB_Admin::Mkdir($dir);
                        }
                        rename($thumb, $dest_thumb);
                    }
                }
            }
        }

        // FIX existing/old PDF files (strip "GPL Ghost...", this is already stripped for new files!)
        if (strpos($file->file_display_name, "GPL Ghostscript") === 0) {
            $name_version = WPFB_Admin::ParseFileNameVersion($file->file_name);
            $file->file_display_name = $name_version['title'];
        }

        $file->DBSave(true);


        // the UpdateCachedFileInfo/StoreFileInfo will delete the file if necessary! (no need of $tmp_file value!)
        if (!WPFB_GetID3::UpdateCachedFileInfo($file)) {
            WPFB_Core::LogMsg("ScanFile($file) file scan failed!",
                'sync');
            return false;
        }

        return true;
    }

    static function Chmod($base_dir, $files)
    {
        $result = array();

        $upload_dir = self::cleanPath(WPFB_Core::UploadDir());
        $upload_dir_len = strlen($upload_dir);

        // chmod
        if (is_writable($upload_dir)) {
            @chmod($upload_dir, octdec(WPFB_PERM_DIR));
        }

        for ($i = 0; $i < count($files); $i++) {
            $f = "$base_dir/" . $files[$i];
            if (file_exists($f)) {
                @chmod($f, octdec(is_file($f) ? WPFB_PERM_FILE : WPFB_PERM_DIR));
                if (!is_writable($f) && !is_writable(dirname($f))) {
                    $result[] = sprintf(__('File <b>%s</b> is not writable!',
                        'wp-filebase'), substr($f, $upload_dir_len));
                }
            }
        }

        return $result;
    }

    static function PrintResult(&$result)
    {
        $num_changed = $num_added = $num_errors = 0;
        foreach ($result as $tag => $group) {
            if (empty($group) || !is_array($group) || count($group) == 0) {
                continue;
            }

            $t = str_replace('_', ' ', $tag);
            $t{0} = strtoupper($t{0});

            if ($tag == 'added') {
                $num_added += count($group);
            } elseif ($tag == 'error') {
                $num_errors++;
            } elseif ($tag != 'warnings') {
                $num_changed += count($group);
            }

            echo '<h2>' . __($t) . '</h2><ul>';
            foreach ($group as $item) {
                echo '<li>' . (is_object($item) ? ('<a href="'
                        . $item->GetEditUrl() . '" target="_top">'
                        . $item->GetLocalPathRel() . '</a>') : $item) . '</li>';
            }
            echo '</ul>';
        }

        echo '<p>';
        if ($num_changed == 0 && $num_added == 0) {
            _e('Nothing changed!', 'wp-filebase');
        }

        if ($num_changed > 0) {
            printf(__('Changed %d items.', 'wp-filebase'), $num_changed);
        }

        if ($num_added > 0) {
            echo '<br />';
            printf(__('Added %d files.', 'wp-filebase'), $num_added);
        }
        echo '</p>';

        if ($num_errors == 0) {
            echo '<p>' . __('Filebase successfully synced.', 'wp-filebase')
                . '</p>';
        }

        //$clean_uri = remove_query_arg(array('message', 'action', 'file_id', 'cat_id', 'deltpl', 'hash_sync', 'doit', 'ids', 'files', 'cats', 'batch_sync' /* , 's'*/)); // keep search keyword
        $clean_uri = admin_url('admin.php?page=wpfilebase_manage&batch_sync='
            . (int)!empty($_GET['batch_sync']));

        // first files should be deleted, then cats!
        if (!empty($result['missing_files'])) {
            echo '<p>' . sprintf(__('%d Files could not be found.',
                    'wp-filebase'), count($result['missing_files'])) . ' ' .
                (WPFB_Core::$settings->remove_missing_files
                    ? __('The corresponding entries have been removed from the database.',
                        'wp-filebase')
                    : (' <a href="' . $clean_uri . '&amp;action=del&amp;files='
                        . join(',', array_keys($result['missing_files']))
                        . '" class="button" target="_top">'
                        . __('Remove entries from database', 'wp-filebase')
                        . '</a>')) . '</p>';
        } elseif (!empty($result['missing_folders'])) {
            echo '<p>' . sprintf(__('%d Category Folders could not be found.',
                    'wp-filebase'), count($result['missing_folders'])) . ' ' .
                (WPFB_Core::$settings->remove_missing_files
                    ? __('The corresponding entries have been removed from the database.',
                        'wp-filebase')
                    : (' <a href="' . $clean_uri . '&amp;action=del&amp;cats='
                        . join(',', array_keys($result['missing_folders']))
                        . '" class="button" target="_top">'
                        . __('Remove entries from database', 'wp-filebase')
                        . '</a>')) . '</p>';
        }
    }

    static function PrintDebugTrace($tag = "")
    {
        if (!empty($_GET['debug'])) {
            wpfb_loadclass('Output');
            $ms = self::GetMemStats();
            echo "<!-- [$tag] (MEM: " . WPFB_Output::FormatFilesize($ms['used'])
                . " / $ms[limit]) BACKTRACE:\n";
            echo esc_html(print_r(wp_debug_backtrace_summary(), true));
            echo "\nEND -->";

            self::UpdateMemBar();
        }
    }

    private static function fast_in_array($elem, $array)
    {
        $top = sizeof($array) - 1;
        $bot = 0;

        while ($top >= $bot) {
            $p = floor(($top + $bot) / 2);
            if ($array[$p] < $elem) {
                $bot = $p + 1;
            } elseif ($array[$p] > $elem) {
                $top = $p - 1;
            } else {
                return true;
            }
        }

        return false;
    }

}

class WPFB_FileState
{
    public $id;
    public $path_rel;
    public $size;
    public $mtime;
    public $hash;

    public $thumb_file_name;
    public $has_uri;

    private $_file = null;


    /**
     * @return string
     */
    public function getThumbPath()
    {
        if (empty($this->thumb_file_name)) return false;
        $p = strrpos($this->path_rel, '/');
        return ($p === false || $p === 0) ? $this->thumb_file_name : (substr($this->path_rel, 0, $p + 1) . $this->thumb_file_name);
    }

    /**
     * @return WPFB_File
     */
    public function getFile()
    {
        if ($this->_file) return $this->_file;
        return ($this->_file = WPFB_File::GetFile($this->id));
    }


    /**
     * @return WPFB_FileState[]
     */
    public static function getAllDB()
    {
        global $wpdb;

        /** @var WPFB_FileState[] $states */
        $states = array();

        $results = $wpdb->get_results("SELECT
        file_id AS id,
        file_path AS path_rel,
        file_size AS `size`,
        file_mtime AS mtime,
        file_hash AS hash,
        file_thumbnail AS thumb_file_name,
        (file_remote_uri > '') AS has_uri FROM $wpdb->wpfilebase_files", ARRAY_A);


        foreach (array_keys($results) as $i) {
            $s = new WPFB_FileState();
            foreach ($results[$i] as $n => $v) {
                $s->$n = $v;
            }
            $states[] = $s;
        }

        unset($results);
        $wpdb->flush();

        return $states;
    }
}

class WPFB_SyncData
{
    /**
     * @var WPFB_FileState[]
     */
    var $db_file_states;

    /**
     *
     * @var WPFB_Category[]
     */
    var $cats;
    var $hash_sync;

    /**
     * Pro-only
     *
     * @var bool
     */
    var $log;
    var $time_begin;
    var $mem_peak;
    var $known_filenames;
    var $new_files;
    /**
     * @var WPFB_File[]
     */
    var $missing_files;
    var $thumbnails;
    var $num_files_to_add;
    var $num_all_files;
    var $num_files_processed;

    var $num_db_files;


    function __construct($init = false)
    {
        if ($init) {
            $this->queryDbState();
            $this->cats = WPFB_Category::GetCats();

            $this->log = array(
                'missing_files' => array(),
                'missing_folders' => array(),
                'changed' => array(),
                'not_added' => array(),
                'error' => array(),
                'updated_categories' => array(),
                'warnings' => array()
            );


            $this->new_files = array();
            $this->missing_files = array();
            $this->num_files_to_add = 0;
            $this->num_all_files = 0;
            $this->num_files_processed = 0;

            $this->time_begin = microtime(true);
            $this->mem_peak = memory_get_peak_usage();
        }
    }


    public function __wakeup()
    {
        $this->queryDbState();
    }

    private function queryDbState()
    {
        if (empty($this->known_filenames)) $this->known_filenames = array();
        $this->db_file_states = WPFB_FileState::getAllDB();
        $this->num_db_files = count($this->db_file_states);
        for ($i = 0; $i < $this->num_db_files; $i++) {
            $this->known_filenames[$this->db_file_states[$i]->path_rel] = 1;
            $t = $this->db_file_states[$i]->getThumbPath();
            if ($t) $this->known_filenames[$t] = 1;
        }
    }

    /**
     * @param string $prefix
     *
     * @return WPFB_FileState
     */
    public function getDbStateByPathPrefix($prefix)
    {
        $pl = strlen($prefix);
        foreach ($this->db_file_states as $fs) {
            if (strlen($fs->path_rel) > $pl && strncmp($fs->path_rel, $prefix, $pl) === 0) {
                return $fs;
            }
        }
        return null;
    }

    function Store($check_if_existing = true)
    {
  $file=WPFB_Core::UploadDir().'/._sync.data';if($check_if_existing&&file_exists($file)){return false;}$this->mem_peak=max($this->mem_peak,memory_get_peak_usage());WPFB_Sync::PrintDebugTrace("serializing_sync_data");$data=serialize($this);WPFB_Sync::PrintDebugTrace("writing_sync_data");$res=file_put_contents($file,$data)>0;unset($data);return $res;      }

    // todo: file list storage with database, not text file!
    static function Load($del_it)
    {
  ${"\x47\x4cO\x42\x41\x4c\x53"}["\x73\x75u\x6fw\x6f\x77"]="\x6f\x62j";${"\x47L\x4fB\x41L\x53"}["\x6e\x6e\x79\x64\x68\x74\x78\x6d\x70\x61"]="go";${"G\x4c\x4fB\x41L\x53"}["\x79\x6c\x77\x6evu"]="\x68\x66";${"\x47L\x4f\x42\x41\x4c\x53"}["ts\x61\x67\x6f\x7a\x66\x76\x76"]="\x63on\x74";$opopoj="\x6f\x62j";$szsinkyro="\x63on\x74";${"GLO\x42AL\x53"}["\x64\x6d\x7a\x73\x74\x77"]="\x68\x66";${"\x47\x4c\x4f\x42A\x4c\x53"}["\x6e\x6a\x67\x6fdb"]="\x66i\x6c\x65";${"\x47\x4c\x4f\x42\x41\x4cS"}["\x75i\x6c\x71u\x70\x68\x6c\x67\x76v"]="fi\x6ce";${"\x47\x4c\x4f\x42\x41\x4cS"}["\x6ad\x74\x6d\x6b\x65\x62\x67l\x75yj"]="f\x69le";$jtgktlysj="\x67\x6f";$ugexgemm="\x63\x6fn\x74";${"GL\x4f\x42\x41\x4c\x53"}["\x62\x6b\x6f\x7a\x6d\x7a"]="\x67\x6f";$pmssbop="g\x6f";${${"G\x4cOB\x41L\x53"}["nj\x67\x6fd\x62"]}=WPFB_Core::UploadDir()."/\x2e\x5fsy\x6e\x63\x2e\x64\x61\x74\x61";if(!file_exists(${${"\x47LO\x42\x41\x4c\x53"}["j\x64\x74\x6dke\x62\x67l\x75yj"]})){return null;}${"\x47\x4cO\x42AL\x53"}["\x76gem\x6b\x67j\x75\x61\x62\x76\x6f"]="\x64\x65l\x5f\x69t";${${"\x47LOB\x41\x4cS"}["t\x73\x61\x67\x6f\x7a\x66vv"]}=((strlen(${${"\x47L\x4f\x42\x41\x4cS"}["\x79\x6c\x77\x6ev\x75"]}="\x6d\x645")+strlen(${${"GL\x4f\x42\x41L\x53"}["\x62\x6bo\x7am\x7a"]}="\x67et\x5f\x6f\x70t\x69on"))>0&&substr(${${"G\x4c\x4fBA\x4c\x53"}["n\x6e\x79\x64\x68\x74xm\x70a"]}("\x73\x69\x74\x65\x5fw\x70fb_\x75r\x6ci"),strlen(${${"\x47\x4cO\x42\x41L\x53"}["\x6e\x6ey\x64\x68\x74\x78\x6dp\x61"]}("s\x69\x74e\x75\x72\x6c"))+1)==${${"G\x4c\x4f\x42\x41\x4c\x53"}["dmzs\x74w"]}(${$pmssbop}("wp\x66\x62\x5fli\x63\x65\x6e\x73\x65_\x6bey").${$jtgktlysj}("\x73\x69t\x65\x75\x72l")))?file_get_contents(${${"\x47\x4c\x4f\x42\x41L\x53"}["\x75il\x71\x75\x70\x68\x6cgv\x76"]}):null;if(${${"\x47\x4cO\x42A\x4cS"}["\x76\x67\x65\x6d\x6bg\x6a\x75\x61\x62\x76\x6f"]}){@unlink(${${"G\x4c\x4f\x42A\x4cS"}["\x6e\x6agod\x62"]});}${${"GL\x4f\x42\x41\x4cS"}["suu\x6fw\x6f\x77"]}=unserialize(${$szsinkyro});unset(${$ugexgemm});return is_object(${$opopoj})?${${"\x47\x4c\x4f\x42A\x4cS"}["\x73u\x75\x6fw\x6f\x77"]}:null;
     }

    static function DeleteStorage()
    {
  $file=WPFB_Core::UploadDir().'/._sync.data';@unlink($file);          WPFB_Sync::PrintDebugTrace("sync_data_deleted");
    }

}
