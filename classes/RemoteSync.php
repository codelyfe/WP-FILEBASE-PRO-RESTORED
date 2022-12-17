<?php

abstract class WPFB_RemoteSync
{

    static function InitClass()
    {
        $service_classes = array("FTPSync");
        self::RegisterServiceClasses($service_classes);

        do_action('wpfilebase_register_rsync_service');
    }

    private $id;
    private $title;
    private $remote_path;
    private $root_cat_id;
    private $no_file_scan = true; // deprecated, always true
    private $last_sync_time;
    private $num_files;
    //private $is_syncing;

    protected $uris_invalidated;
    private $no_remote_delete;

    private $file_previews;

    private $keep_files_locally;

    private $disabled = false;

    /**
     *
     * @var WPFB_ProgressReporter
     */
    protected $progress_reporter;

    function __construct($title)
    {
        $this->title = $title;
        $this->id = uniqid();
    }

    function __wakeup()
    {
        $this->progress_reporter = null;
    }

    // Manage API
    protected function PrepareEditForm()
    {
        return true;
    }

    protected function DisplayFormFields()
    {

    }

    protected function PreSerialize()
    {
    }

    protected function PostDeserialize()
    {
    }

    function GetAccountName()
    {
        return '-';
    }

    function GetServiceSlug()
    {
        return strtolower(substr(get_class($this), 5));
    }

    function GetDeletionState()
    {
        return !$this->no_remote_delete;
    }

    final function TryLock()
    {
        return isset($_REQUEST['MEMPROF']) || !$this->GetCat() || $this->GetCat()->TryScanLock();
    }

    private $lastLockRefresh = 0;

    final function RefreshLock()
    {
        if((time() - $this->lastLockRefresh) < 1) return;

        if (!isset($_REQUEST['MEMPROF']) && ($c = $this->GetCat()) && !$c->TryScanLock()) {
            throw new RemoteSyncException("Lost lock of $c, this should not happen. Try again later. "
                . $c->GetScanLockDebug());
        }

        $this->lastLockRefresh = time();
    }

    function Edited($data, $invalidate_uris = false)
    {
        if (!$this->TryLock()) {
            return array('err' => 'Category is locked!');
        }

        // check for existing remote syncs in category root
        $cat_id = intval($data['root_cat_id']);
        $cat = WPFB_Category::GetCat($cat_id);
        if (is_null($cat)) {
            return array('err' => 'Category does not exists!');
        }

        if (!$cat->TryScanLock()) {
            return array('err' => 'Category is locked!');
        }

        $pc = $cat;
        do {
            $ex = WPFB_RemoteSync::GetByCat($pc->GetId());
            if (!is_null($ex) && $ex->id != $this->id) {
                return array(
                    'err' => sprintf('A Remote Sync with root category <b>%s</b> already exists. Please choose another category.',
                        $pc->GetTitle())
                );
            }
        } while (!is_null($pc = $pc->GetParent()));

        if ($this->last_sync_time > 0
            && !is_null($old_cat = WPFB_Category::GetCat($this->root_cat_id))
            && !$old_cat->Equals($cat)
        ) {
            foreach (
                array_merge($old_cat->GetChildFiles(), $old_cat->GetChildCats())
                as $item
            ) {
                /** @var WPFB_Item $item */
                $item->ChangeCategoryOrName($cat_id);
            }
        }

        if (empty($data['title'])) {
            return array('err' => 'Please enter a name!');
        }

        $this->title = $data['title'];
        $this->root_cat_id = $cat->GetId();
        if (isset($data['remote_path'])) {
            $this->remote_path = untrailingslashit($data['remote_path']);
        }

        $this->no_file_scan = true || !empty($data['no_file_scan']);

        $this->file_previews = !empty($data['file_previews']);

        $this->keep_files_locally = !empty($data['keep_files_locally']);

        $this->disabled = !empty($data['disabled']);

        if ($invalidate_uris) {
            $this->uris_invalidated = true;
        }

        $this->no_remote_delete = empty($data['remote_delete']);

        $this->Save();

        $this->cacheFlush();

        return array('err' => false);
    }

    protected function setFilePreviews($preview)
    {
        $this->file_previews = !!$preview;
    }

    public function GetFilePreviews()
    {
        return $this->file_previews;
    }

    // Sync API
    function IsReady()
    {
        return true;
    }

    protected function OpenConnection($for_sync = true)
    {
        return true;
    }

    protected function CloseConnection()
    {

    }

    abstract protected function GetFileList($path, $names_only = false);

    /**
     * Can be overridden, must throw RemoteSyncException on error
     *
     * @param WPFB_RemoteFileInfo $file_info
     * @param string $local_path
     * @param callable $progress_changed_callback
     * @throws RemoteSyncException
     */
    protected function DownloadFile(
        $file_info,
        $local_path,
        $progress_changed_callback = null
    )
    {
        $url = $this->GetFileUri(empty($file_info->guid) ? $file_info->path
            : $file_info->guid);
        if (is_array($url)) $url = reset($url);
        $res = WPFB_Download::SideloadFile($url, $local_path, $progress_changed_callback);
        if (!empty($res['error'])) {
            throw new RemoteSyncException($res['error'] . " file $local_path");
        }
    }

    abstract protected function GetFileUri($path_or_guid, &$expires = null);

    private function getFileUrl($path_or_guid)
    {

    }

    /**
     * @param  string $local_path
     * @param   string $remote_path
     * @param callable $progress_changed_callback
     *
     * @return WPFB_RemoteFileInfo
     */
    protected function UploadFile(
        $local_path,
        $remote_path,
        $progress_changed_callback = null
    )
    {
        return null;
    }

    protected function CanUpload()
    {
        return false;
    }

    /**
     * @param WPFB_RemoteFileInfo $file_info
     * @throws RemoteSyncException
     */
    protected function DeleteFile($file_info)
    {
        throw new RemoteSyncException(get_class($this) . "::DeleteFile() not implemented!");
    }

    protected function CanDelete()
    {
        return false;
    }

    public function SupportFilePreviews()
    {
        return false;
    }

    //protected function IsSyncing() { return !empty($this->is_syncing); }


    final function GetId()
    {
        return $this->id;
    }

    final function GetTitle()
    {
        return $this->title;
    }

    /**
     * Get the root category
     *
     * @return id The category.
     */
    final function GetCatId()
    {
        return $this->root_cat_id;
    }


    final function SetCat($cat)
    {
        $this->root_cat_id = is_object($cat) ? $cat->cat_id : (0 + $cat);
    }

    final function GetCat()
    {
        wpfb_loadclass('Category');

        return WPFB_Category::GetCat($this->root_cat_id);
    }

    final function GetRemotePath()
    {
        return $this->remote_path;
    }

    final function GetLastSyncTime()
    {
        return $this->last_sync_time;
    }

    final function GetNumFiles()
    {
        return $this->num_files;
    }


    final function getKeepFilesLocally()
    {
        return $this->keep_files_locally;
    }

    private static $service_classes;

    static function RegisterServiceClass($class_name)
    {
        if (!class_exists($class_name)) { // if class not found, try to load it
            if (substr($class_name, 0, 5) == "WPFB_") {
                $class_name = substr($class_name, 5);
            }
            wpfb_loadclass($class_name);
            $class_name = "WPFB_" . $class_name;
        }

        if (empty(self::$service_classes)) {
            self::$service_classes = array();
        }
        self::$service_classes[] = $class_name;

        return true;
    }

    static function RegisterServiceClasses($class_names)
    {
        array_map(array(__CLASS__, 'RegisterServiceClass'), $class_names);
    }

    static function GetServiceClasses()
    {
        $classes = array();
        foreach (self::$service_classes as $sc) {
            $classes[$sc] = call_user_func(array($sc, 'GetServiceName'));
        }

        return $classes;
    }

    static function IsServiceClass($class)
    {
        return is_object($class) ? (self::IsServiceClass(get_class($class)) && !empty($class->id))
            : in_array($class, self::$service_classes, true);
    }


    /**
     *
     * @return WPFB_RemoteSync[]
     */
    static function GetSyncs()
    {
        wp_cache_flush();
        is_array($syncs = get_option(WPFB_OPT_NAME . '_rsyncs'))
        || ($syncs
            = array());

        /** @var WPFB_RemoteSync[] $syncs */
        $syncs = array_filter($syncs, array(__CLASS__, 'IsServiceClass'));

        foreach ($syncs as $sync)
            $sync->PostDeserialize();

        return $syncs;
    }

    /**
     * @param WPFB_RemoteSync $sync
     *
     * @throws RemoteSyncException
     */
    static function AddSync($sync)
    {
        if (!$sync->TryLock()) {
            throw new RemoteSyncException("Cannot save sync with locked category!");
        }

        if (method_exists($sync, 'PreSerialize')) {
            $sync->PreSerialize();
        }

        wp_cache_flush();
        is_array($syncs = get_option(WPFB_OPT_NAME . '_rsyncs'))
        || ($syncs
            = array());
        $syncs[$sync->id] = $sync;
        update_option(WPFB_OPT_NAME . '_rsyncs', $syncs);
    }

    final function Save()
    {
        self::AddSync($this);
    }

    final function Serialize()
    {
        if (method_exists($this, 'PreSerialize')) {
            $this->PreSerialize();
        }
        return serialize($this);
    }

    static function DeleteSync($id)
    {
        global $wpdb;

        wp_cache_flush();

        /* @var $syncs WPFB_RemoteSync[] */
        $syncs = get_option(WPFB_OPT_NAME . '_rsyncs');
        foreach ($syncs as $i => $r) {
            // make sure we dont compare against incomplete classes
            if (self::IsServiceClass($r) && $r->id === $id) {
                if (!$r->TryLock()) {
                    return false;
                }
                $r->cacheFlush();
                unset($syncs[$i]);
                update_option(WPFB_OPT_NAME . '_rsyncs', $syncs);

                $r->removeLocalFiles($r->getLocalFileIds());
                $r->removeEmptyCategories();
                $wpdb->query("DELETE FROM $wpdb->wpfilebase_rsync_meta WHERE rsync_id = '$id'");

                return true;
            }
        }
    }

    /**
     * Get RemoteSync
     *
     * @return WPFB_RemoteSync The Sync.
     */
    static function GetSync($id)
    {
        $rs = self::GetSyncs();
        foreach ($rs as $r) {
            if ($r->id == $id) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Get RemoteSync by category id
     *
     * @param int $cat_id ID of category
     *
     * @return WPFB_RemoteSync The Sync.
     */
    static function GetByCat($cat_id)
    {
        $rs = self::GetSyncs();
        foreach ($rs as $r) {
            if ($r->root_cat_id == $cat_id) {
                return $r;
            }
        }

        return null;
    }

    static function SyncAll($output = true)
    {
        $syncs = self::GetSyncs();
        wpfb_loadclass('Sync', 'ProgressReporter');
        $progress_reporter = new WPFB_ProgressReporter(!$output);
        if ($output) {
            $progress_reporter->InitMemBar();
        }
        foreach ($syncs as $rsync) {
            try {
                if ($rsync->IsReady() && !is_null($rsync->GetCat()) && !$rsync->disabled) {
                    $rsync->Sync(true, $progress_reporter);
                }
            } catch (Exception $e) {
                $progress_reporter->LogException($e);
            }
            if ($output) {
                WPFB_Sync::UpdateMemBar();
            }
        }

        // start the file crawler
        do_action('wpfilebase_bgscan');

        if ($output) {
            $progress_reporter->ChangedFilesReport();
        }
    }

    final public function GetFiles($path)
    {
        $this->OpenConnection(false);
        $files = $this->GetFileList($path, true);
        $this->CloseConnection();

        return $files;
    }

    final public function GetRemoteFileInfo($remote_path)
    {
        // TODO caching!

        $remote_path = rtrim($remote_path, '/');
        try {
            foreach ($this->GetFileList(self::dirname($remote_path)) as $fi) {
                if (trim($fi->path, '/') == trim($remote_path, '/')) {
                    return $fi;
                }
            }
        } catch (Exception $e) {

        }

        return null;
    }

    /**
     * @param string $path
     * @param int $depth
     * @param bool $progress_callback
     * @param bool $random_walk
     *
     * @return WPFB_RemoteFileInfo[]
     */
    final private function &getFileTree(
        $path,
        $depth = 0,
        $progress_callback = false,
        $random_walk = false
    )
    {
        static $files;
        if ($depth == 0) {
            $files = array();
        }
        if ($progress_callback != null) {
            call_user_func($progress_callback, count($files));
        }

        try {
            $fs = $this->GetFileList($path);
        } catch (Exception $ex) {
            $this->progress_reporter && $this->progress_reporter->LogError("GetFileList($path) failed: {$ex->getMessage()}");
            return $files;
        }

        $random_walk && shuffle($fs);

        foreach ($fs as $f) {
            if (!$f->is_dir) {
                // if entires with same path exists, take the newer one (Google Drive!)
                if (!isset($files[$f->path])
                    || ($f->mtime > $files[$f->path]->mtime)
                ) {
                    $files[$f->path] = $f;
                }
            } else {
                $this->getFileTree($f->path, $depth + 1, $progress_callback, $random_walk);
            }
        }

        return $files;
    }


    function PrimeCache()
    {
        $this->progress_reporter = null;
        $this->OpenConnection(false);
        // randomly walk through file tree
        $this->getFileTree($this->remote_path, 0, false, true);
        $this->CloseConnection();
    }

    private final function createDirStructure($remote_path)
    {
        $fullpath = "";
        foreach (array_filter(explode("/", $remote_path)) as $part) {
            $fullpath .= "/" . $part;
            if ($this->GetRemoteFileInfo($fullpath) == null) {
                $this->CreateDirectory($fullpath);
            }
        }
    }

    protected function GetMaxConcurrentConnections()
    {
        return 1;
    }

    /**
     * Get RemoteSyncMeta of all files that have been deleted locally since last sync
     *
     * @return WPFB_RemoteSyncMeta[]
     */
    final function GetLocallyDeletedFiles($keep_meta = false)
    {
        global $wpdb;
        $deleted = array();
        foreach (
            $wpdb->get_results("SELECT * FROM $wpdb->wpfilebase_rsync_meta WHERE rsync_id = '"
                . esc_sql($this->id) . "' AND deleted_path <> ''") as $rmeta
        ) {
            /* @var $rmeta WPFB_RemoteSyncMeta */
            $deleted[$rmeta->deleted_path] = $rmeta;
        }
        if (!$keep_meta) {
            $wpdb->query("DELETE FROM $wpdb->wpfilebase_rsync_meta WHERE rsync_id = '"
                . esc_sql($this->id) . "' AND deleted_path <> ''");
        }

        return $deleted;
    }

    static function GetServiceName()
    {
        return null;
    }

    /**
     * @param bool $batch
     * @param WPFB_ProgressReporter $progress_reporter
     *
     * @return bool
     * @throws RemoteSyncException
     */
    final function Sync($batch, $progress_reporter)
    {
        wpfb_call('Admin', 'DisableTimeouts');
        wpfb_loadclass('GetID3', 'Sync', 'Output');

        //$this->is_syncing = true;
        $this->progress_reporter = $progress_reporter;

        if (!$this->IsReady()) {
            $name = $this->GetTitle();
            $progress_reporter->LogError("Cloud sync $name not ready, please check its settings!");
            return false;
        }

        $cat = $this->GetCat();
        if (is_null($cat)) {
            $progress_reporter->LogError('Category does not exists or is not set in RemoteSync settings!');

            return false;
        }

        if (!$this->TryLock()) {
            $progress_reporter->LogError(sprintf('Category %s is locked. A sync is already running, please try again in %s!',
                $cat->GetName(), human_time_diff(time(), $cat->cat_scan_lock)));

            return false;
        }

        $cat_path = $cat->GetLocalPath();
        $cat_path_rel = $cat->GetLocalPathRel();

        $progress_reporter->Log(sprintf(__('Remote Sync <b>%s</b> on service <b>%s</b> with account <b>%s</b>.',
            'wp-filebase'), $this->GetTitle(), $this->GetServiceName(),
            $this->GetAccountName()));

        $this->OpenConnection(true);

        $cache_key = "rfi:$this->remote_path";
        /** @var WPFB_RemoteFileInfo $remote_files */
        $remote_files = $this->cacheGet($cache_key);

        if (!$remote_files) {
            $progress_reporter->Log('Retrieving remote file tree, this can take some time...',
                true);
            $progress_reporter->InitProgressField('Files found: %#%');

            $progress_reporter->StopwatchStart();


            if ($this->GetMaxConcurrentConnections() > 1
                && (empty($this->num_files) || $this->num_files > 100)
            ) {
                wpfb_loadclass('CCron');
                $n = min($this->GetMaxConcurrentConnections(), 5);
                $progress_reporter->Log("Starting random cache primer on $n background workers...");
                WPFB_CCron::TaskStart('wpfilebase_rsync_bg', $n,
                    array('prime', $this->id));
            }

            $remote_files = array_values($this->getFileTree($this->remote_path, 0,
                array($progress_reporter, 'SetField')));


            $progress_reporter->StopwatchEnd(sprintf('File tree complete, %d files after %%s',
                count($remote_files)));


            $this->cacheSet($cache_key, $remote_files, 60 * 120); // TODO lower cache
        } else {
            $progress_reporter->InitProgressField('Files found: %#%');
        }


        $this->num_files = count($remote_files);
        $progress_reporter->SetField($this->num_files);

        //print_r($remote_files);

        $this->RefreshLock();

        $local_file_ids = $this->getLocalFileIds();
        $local_files_left = array_combine($local_file_ids, $local_file_ids);
        $local_files_deleted = $this->no_remote_delete ? array()
            : $this->GetLocallyDeletedFiles(); // TODO: nor working!
        $deleted_files = 0;


        $progress_reporter->Log("Comparing files ...");
        $progress_reporter->StopwatchStart();
        /** @var WPFB_LocalFileState[] $changed_files */
        $changed_files = array();
        /** @var WPFB_File[] $uri_updates */
        $uri_updates = array();
        $local_remote_rel_paths = array();
        /** @var WPFB_LocalFileState[] $download_files */
        $download_files = array();
        $n_mod = $n_new = 0;
        foreach ($remote_files as $rf) {
            /** @var WPFB_RemoteFileInfo $rf */

            if (!WPFB_Admin::IsAllowedFileExt($rf->path)) {
                continue;
            }

            $remote_path_rel = substr($rf->path,
                strlen($this->remote_path));
            $local_path = str_replace('//', '/',
                $cat_path . '/' . $remote_path_rel);
            $local_path_rel = str_replace('//', '/',
                $cat_path_rel . '/' . $remote_path_rel);
            $local_remote_rel_paths[] = $local_path_rel;

            if (!$this->no_remote_delete && $this->CanDelete()
                && isset($local_files_deleted[$local_path_rel])
            ) {
                $progress_reporter->Log('Deleting ' . $local_path_rel . ' from cloud.');
                $this->DeleteFile($rf);
                $deleted_files++;
                continue;
            }

            $local_state = $this->getLocalFileState($local_path_rel, $rf);


            if ($local_state->changed()) {
                // echo "!!!changed detected"; print_r($local_state);
                $changed_files[] = $local_state;
                $local_state->exists() ? $n_mod++ : $n_new++;
            }

            if ($local_state->exists()) {
                unset($local_files_left[$local_state->file_id]);
            } else {
                //$progress_reporter->Log('  locally deleted: ' . $local_state->local_path_rel); // or new!
            }

            if ($this->keep_files_locally && (!file_exists($local_path) || filesize($local_path) != $rf->size) || filemtime($local_path) != $rf->mtime) {
                $download_files[] = new WPFB_LocalFileState($local_path_rel, $rf);
            }

            // TODO: uris_invalidated

            /* } elseif ($this->uris_invalidated) {
                $uri_updates[] = $local_file;
            }
            */
        } // end foreach ($remote_files as $rf)


        $progress_reporter->Log('done!');
        $progress_reporter->StopwatchEnd('Compared files in %s');

        $changes = $n_new + $n_mod + $deleted_files + count($local_files_left);

        if ($changes > 0) {
            $progress_reporter->Log(sprintf(__('Number of Files [new/modified/deleted local/deleted cloud]: %d / %d / %d / %d',
                'wp-filebase'), $n_new, $n_mod,
                $deleted_files, count($local_files_left)));
        } else {
            $progress_reporter->Log(__('No changes since last sync.', 'wp-filebase'));
        }

        if (count($uri_updates) > 0) {
            $progress_reporter->Log(sprintf(__('Number of URI updates: %d'),
                count($uri_updates)));
        }


        if (count($changed_files) > 0) {
            $progress_reporter->Log(__('Adding files...', 'wpfilebase'));
            $progress_reporter->InitProgress(count($changed_files));
            $progress_reporter->InitProgressField(__('Current File: %#%', 'wpfilebase'), '-',
                true);

            WPFB_Sync::PrintDebugTrace("rsync_pre_loop");

            // if adding lots of files, lock all categories, to prevent db store on bubbling notifications
            if (($need_sync = ($n_new > 10))) {
                WPFB_Category::DisableBubbling(); // TODO need to sync cats afterwards!
                $progress_reporter->Log("Disabled notification bubbling for categories.");
            }

            foreach ($changed_files as $cf) {
                $progress_reporter->SetField($cf->info->path);
                $local_path = $cf->getLocalPath();
                is_file($local_path) && unlink($local_path); // file changed on cloud, delete local copy

                if (is_dir($local_path)) {
                    $inf = json_encode($cf);
                    $progress_reporter->LogError("Local path '{$cf->local_path_rel}' of remote file '{$cf->info->path}' is a directory. Skipping download! ($inf)");
                    continue;
                }

                try {

                    if (!$cf->exists()) { // file is new
                        //continue;

                        WPFB_Sync::PrintDebugTrace("rsync_add_file");

                        $result = WPFB_Admin::AddRemoteSyncFile($local_path,
                            $cf->info);

                        /** @var WPFB_File $file */
                        $file = $result['file'];

                        if (!empty($res['error']) || empty($result['file'])
                            || !is_object($result['file'])
                        ) {
                            $progress_reporter->LogError(empty($result['error'])
                                ? ("Skipping file " . $local_path . " - " . json_encode($result))
                                : $result['error']);
                        } elseif (!$file->SetRemoteSyncMeta($cf->info, $this)) {
                            $progress_reporter->LogError('Could not store rsync meta!');
                        } else {
                            $progress_reporter->FileChanged($result['file'],
                                'added');
                        }
                    } else { // file has changed
                        WPFB_Sync::PrintDebugTrace("rsync_update_file");

                        $file = WPFB_File::GetFile($cf->file_id);

                        $file->Lock(true);
                        // this is copied form Sync.php: (TODO: put this in a single function)
                        $file->SetRescanPending();
                        if (!$file->SetRemoteSyncMeta($cf->info, $this)
                        ) {
                            $progress_reporter->LogError('Could not store rsync meta!');
                        }
                        $file->Lock(false);
                        $file->DBSave();

                        $progress_reporter->FileChanged($cf->file, 'changed');
                    }
                } catch (Exception $e) {
                    $progress_reporter->LogException($e);
                }

                $progress_reporter->AddProgress(1);

                $this->RefreshLock();
            }
        }

        /** @var WPFB_File[] $upload_files */
        $upload_files = array();

        // check for files to upload
        if ($this->CanUpload()) {
            $progress_reporter->Log("Checking for pending uploads...");
            foreach ($cat->GetChildFiles(true, null, false, true) as $local_file) {
                if (!in_array($local_file->GetLocalPathRel(),
                        $local_remote_rel_paths)
                    && file_exists($local_file->GetLocalPath())
                ) {
                    $upload_files[] = $local_file;
                }
            }
        }

        if (count($upload_files) > 0) {
            WPFB_Sync::PrintDebugTrace("uploading_files");
            foreach ($upload_files as $local_file) {
                try {
                    if (!$local_file->TryScanLock()) {
                        throw new RemoteSyncException('File '
                            . $local_file->GetLocalPathRel() . ' is locked!');
                    }

                    $upload_path = str_replace('//', '/',
                        trailingslashit($this->remote_path)
                        . substr($local_file->GetLocalPathRel(),
                            strlen($cat_path_rel)));
                    $progress_reporter->Log('Uploading '
                        . $local_file->GetLocalPathRel() . ' to '
                        . $upload_path);

                    // check if dir. create function exists, and create structure if needed!
                    if (method_exists($this, 'CreateDirectory')) {
                        $upload_dir = self::dirname($upload_path);
                        if ($upload_dir != '/') {
                            $this->createDirStructure(self::dirname($upload_path));
                        }
                    }


                    $rf = $this->UploadFile($local_file->GetLocalPath(),
                        $upload_path);

                    if ($rf->size != $local_file->file_size) {
                        throw new RemoteSyncException("File size mismatch after upload, R({$rf->size}) != L({$local_file->file_size})");
                    }


                    $local_file->SetRemoteSyncMeta($rf, $this);


                    if (!$this->keep_files_locally && is_file($local_file->GetLocalPath()) && !unlink($local_file->GetLocalPath())) {
                        throw new RemoteSyncException("Failed to delete local copy of {$local_file->GetLocalPath()}! ");
                    }

                    // apply cloud mtime to local file
                    if ($this->keep_files_locally) {
                        touch($local_file->GetLocalPath(), $rf->mtime);
                    }

                } catch (Exception $e) {
                    $progress_reporter->LogException($e);
                }
                $this->RefreshLock();
            }
        } // upload_files


        WPFB_Sync::PrintDebugTrace("updating_uris");

        if (count($uri_updates) > 0) {
            $progress_reporter->Log(__('Updating URIs...', 'wp-filebase'));
            $progress_reporter->InitProgress(count($uri_updates));
            $i = 0;
            foreach ($uri_updates as $uf) {
                try {
                    $this->RefreshDownloadUri($uf);
                    $progress_reporter->SetProgress(++$i);
                } catch (Exception $e) {
                    $progress_reporter->LogError('Failed to refresh URI of '
                        . $uf->GetLocalPathRel());
                    $progress_reporter->LogException($e);
                }
            }
            $this->RefreshLock();
        }
        $this->uris_invalidated = false;

        // delete files
        if (count($local_files_left) > 0) {
            $progress_reporter->Log(__('Removing local files that have been remotely deleted...',
                'wp-filebase'));
            $this->removeLocalFiles($local_files_left);
            $this->RefreshLock();
        }


        // finally download files (this is only if users wants to, we dont touch local files)
        if ($this->keep_files_locally && count($download_files) > 0) {
            $totalDownloadSize = 0;
            foreach ($download_files as $cf) {
                $totalDownloadSize += $cf->info->size;
            }

            $progress_reporter->Log(sprintf(__('Downloading %d files to <code>%s</code> (total size: %s)', 'wpfilebase'), count($download_files), substr($cat_path, strlen(ABSPATH)),
                WPFB_Output::FormatFilesize($totalDownloadSize)));
            $progress_reporter->InitProgress($totalDownloadSize);
            $progress_reporter->InitProgressField(__('Current File: %#%', 'wpfilebase'), '-', true);

            wpfb_loadclass('Download');
            foreach ($download_files as $cf) {
                $progress_reporter->SetField($cf->info->path);
                try {
                    //var_dump($cf);
                    $this->DownloadFile($cf->info, $cf->getLocalPath(), function ($size) use ($progress_reporter) {
                        echo "size $size\n";
                        $progress_reporter->SetSubProgress($size);
                    });
                    if (!touch($cf->getLocalPath(), $cf->info->mtime))
                        throw new RemoteSyncException("Failed to touch file {$cf->getLocalPath()}!");
                } catch (RemoteSyncException $ex) {
                    $progress_reporter->LogException($ex);
                }
                $progress_reporter->AddProgress($cf->info->size);
                $this->RefreshLock();
            }
        }


        WPFB_Sync::PrintDebugTrace("rsync_post_loop");

        $this->CloseConnection();

        $this->progress_reporter = null;

        if ($deleted_files > 0 || count($upload_files) > 0) {
            $this->cacheFlush();
        }

        $this->last_sync_time = time();
        $this->RefreshLock();
        $this->Save();


        wpfb_loadclass('Sync');
        $sync_cats = $this->GetCat()->GetParents();
        if ($need_sync)
            $sync_cats = array_merge($sync_cats, $this->GetCat()->GetChildCats(true), array($this->GetCat()));
        $progress_reporter->Log(sprintf('Syncing %d categories', count($sync_cats)));
        WPFB_Sync::SyncCats($sync_cats);


        // empty categories must be removed _after_ a category sync! (notification bubbling disabled!)
        if (count($local_files_left) > 0) {
            $progress_reporter->Log(__('Removing empty categories ...',
                'wp-filebase'));
            $this->removeEmptyCategories();
        }

        if (!$batch) {
            // start the file crawler
            do_action('wpfilebase_bgscan');
        }

        $progress_reporter->Log('Done!');
        return true;
    }

    /**
     * @param WPFB_File $file
     * @param bool $connect
     * @return  WPFB_RemoteSyncMeta
     *
     * @throws RemoteSyncException
     */
    final function RefreshDownloadUri($file, $connect = false)
    {
        if (!$file) {
            throw new RemoteSyncException('RefreshDownloadUri: No file object given!');
        }
        if (!$this->GetCat()) {
            throw new RemoteSyncException('RefreshDownloadUri: Root category of cloud sync not found!');
        }
        $path = $this->remote_path . '/' . substr($file->GetLocalPathRel(),
                strlen($this->GetCat()->GetLocalPathRel()));
        $path = trim(str_replace('//', '/', $path), '/');
        $expires = time() + 300; // default link lifetime 5 mins
        if ($connect) {
            $this->OpenConnection(false);
        }
        $rf = $file->GetRemoteSyncMeta();
        $uri = $this->GetFileUri(empty($rf->guid) ? $path : $rf->guid, $expires);

        // always set to actual download link (no preview)
        if ($connect) {
            $this->CloseConnection();
        }

        if (empty($uri) || (is_array($uri) && count(array_filter($uri)) == 0)) {
            throw new RemoteSyncException('RefreshDownloadUri: empty URL returned!');
        }

        if (!$file->SetRemoteSyncUrl(is_array($uri) ? $uri[0] : $uri, $expires, is_array($uri) ? $uri[1] : ''))
            throw new RemoteSyncException('RefreshDownloadUri: failed to store remote URL!');

        return $file->GetRemoteSyncMeta();
    }

    /**
     * @return int[]
     */
    private final function getLocalFileIds()
    {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare(
            "SELECT `$wpdb->wpfilebase_files`.`file_id` FROM $wpdb->wpfilebase_files
            LEFT JOIN $wpdb->wpfilebase_rsync_meta as rmeta ON (rmeta.`file_id` = `$wpdb->wpfilebase_files`.`file_id`)
            WHERE rmeta.rsync_id = %s", $this->id));
    }

    /**
     * @param $path_rel
     * @param WPFB_RemoteFileInfo $rfi
     *
     * @return WPFB_LocalFileState
     * @throws RemoteSyncException
     */
    private final function getLocalFileState($path_rel, $rfi)
    {
        global $wpdb;

        $local_state = $wpdb->get_row($wpdb->prepare(
            "SELECT tf.file_id,tf.file_size,tf.file_mtime,tf.file_remote_uri,tm.rsync_id,tm.rev FROM $wpdb->wpfilebase_files as tf
            LEFT JOIN $wpdb->wpfilebase_rsync_meta as tm ON (tm.file_id = tf.file_id)
            WHERE tf.file_path = %s", $path_rel));

        if (!$local_state)
            return new WPFB_LocalFileState($path_rel, $rfi); // new

        if (!empty($local_state->rsync_id) && $local_state->rsync_id != $this->id) {
            throw new RemoteSyncException("Rsync id of local file $path_rel does not match with $this->id!");
        }

        if (empty($local_state->rev) || $local_state->rev != $rfi->rev || $local_state->file_size != $rfi->size
            || $local_state->file_mtime != $rfi->mtime || empty($local_state->file_remote_uri)
        ) {
            if (!empty($_GET['debug'])) {
                echo "  ($local_state->rev != $rfi->rev || $local_state->file_size != $rfi->size || $local_state->file_mtime != $rfi->mtime) <br> \n";
            }
            return new WPFB_LocalFileState($path_rel, $rfi, $local_state->file_id); // modified
        }

        return new WPFB_LocalFileState($path_rel, null, $local_state->file_id); // not-changed
    }

    /**
     * @param int[] $file_ids
     */
    final private function removeLocalFiles($file_ids)
    {
        $this->progress_reporter && $this->progress_reporter->InitProgress(count($file_ids));
        foreach ($file_ids as $id) {
            $file = WPFB_File::GetFile($id);
            $file && $file->Remove();
            $this->progress_reporter && $this->progress_reporter->AddProgress(1);
        }
    }

    final private function removeEmptyCategories()
    {
        if (($cat = $this->GetCat())) {
            $cats = $cat->GetChildCats(true);
            foreach ($cats as $cat) {
                if ($cat->cat_num_files_total <= 0) {
                    $cat->Delete();
                }
            }
        }
    }

    final function DisplayEditForm()
    {
        if (!$this->TryLock()) {
            echo '<h2>Locked</h2><p>'
                . sprintf(__('The category is locked for %s or until the sync process completes.',
                    'wp-filebase'),
                    human_time_diff(time(), $this->GetCat()->cat_scan_lock))
                . ' Please try again later.</p>';

            return;
        }
        ?><h2><?php _e('Remote Sync Settings', 'wp-filebase') ?></h2><?php
        if (method_exists($this, 'Reauth') && !empty($_GET['rsync_reauth'])) {
            $this->Reauth();
        }

        $res = false;
        try {
            $res = $this->PrepareEditForm();
        } catch (Exception $e) {
            WPFB_Core::LogMsg('CloudSync prepare error: ' . $e->getMessage());
            wp_die($e->getMessage());
        }

        if (!$res)
            return;

        ?>
        <form action="<?php echo admin_url('admin.php?page='
            . $_GET['page']); ?>" method="post">
            <input type="hidden" name="rsync_id"
                   value="<?php echo $this->id ?>"/>
            <input type="hidden" name="action" value="edited-rsync"/>
            <table class="form-table">
                <?php if (method_exists($this, 'Reauth')) { ?>
                    <tr class="form-field">
                        <th scope="row" valign="top"><label
                                    for="rsync-name"><?php _e('Authentication',
                                    'wp-filebase') ?></label></th>
                        <td width="100%">
                            <?php echo esc_html($this->GetAccountName()); ?>
                            <a href="<?php echo admin_url('admin.php?page='
                                . $_GET['page'] . '&action='
                                . $_GET['action'] . '&rsync_id='
                                . $_GET['rsync_id']
                                . '&rsync_reauth=1'); ?>" class="button">Re-Authenticate</a>
                        </td>
                    </tr>
                <?php } ?>

                <?php if ($this->IsReady() && $this->num_files > 0) { ?>
                    <tr>
                        <th scope="row" valign="top"></th>
                        <td>
                            <input id="rsync-disabled" name="disabled" type="checkbox" value="1"
                                <?php checked($this->disabled);
                                ?> />
                            <label for="rsync-disabled"><?php _e('Disabled'); ?></label>
                        </td>
                    </tr>
                <?php } ?>

                <tr class="form-field">
                    <th scope="row" valign="top"><label
                                for="rsync-name"><?php _e('Name',
                                'wp-filebase') ?></label></th>
                    <td width="100%">
                        <input id="rsync-name" name="title" type="text"
                               value="<?php echo esc_attr($this->title); ?>"/>
                    </td>
                </tr>

                <tr class="form-field">
                    <th scope="row" valign="top"><label
                                for="rsync-cat"><?php _e('Category',
                                'wp-filebase') ?></label></th>
                    <td width="100%">
                        <select name="root_cat_id" id="rsync-cat"
                                class="postform wpfb-cat-select">
                            <?php echo WPFB_Output::CatSelTree(array(
                                'selected' => $this->root_cat_id,
                                'add_cats' => true
                            )) ?>
                        </select>
                    </td>
                </tr>
                <!-- this is now default
					<tr>
						<th scope="row" valign="top"></th>
						<td>
							<input id="rsync-no-scan" name="no_file_scan" type="checkbox" value="1" <?php checked($this->no_file_scan) ?> />
							<label for="rsync-no-scan"><?php _e('Don\'t generate thumbnails and scan files for ID3 tags. This will make sync much faster, since files are not temporarily downloaded.',
                    'wp-filebase') ?></label>
						</td>
					</tr>
					-->
                <tr>
                    <th scope="row" valign="top"></th>
                    <td>
                        <input id="rsync-remote-delete" name="remote_delete"
                               type="checkbox"
                               value="1"
                            <?php checked(!@$this->no_remote_delete && $this->CanDelete()) ?>
                            <?php disabled(!$this->CanDelete()) ?>
                        />
                        <label
                                for="rsync-remote-delete"><?php _e('Delete files from Cloud if removed locally',
                                'wp-filebase') ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"></th>
                    <td>
                        <input id="rsync-preview" name="file_previews" type="checkbox" value="1"
                            <?php checked($this->file_previews && $this->SupportFilePreviews());
                            disabled(!$this->SupportFilePreviews()) ?> />
                        <label for="rsync-preview"><?php _e('Link to a File Preview Page', WPFB) ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row" valign="top"></th>
                    <td>
                        <input id="rsync-keep_files_locally" name="keep_files_locally" type="checkbox" value="1"
                            <?php checked($this->keep_files_locally);
                            ?> />
                        <label for="rsync-keep_files_locally"><?php printf(__('Keep file copies under <code>%s</code>', 'wpfilebase'), WPFB_Core::$settings->upload_path . '/' . ($this->GetCat() ? $this->GetCat()->GetLocalPathRel() : '')); ?></label>
                        <br/>
                        <?php _e('This is only useful if you want to use the files externally.', 'wpfilebase'); ?>
                    </td>
                </tr>


                <?php if ($this->IsReady()) { ?>
                    <tr class="form-field">
                        <th scope="row" valign="top"><label
                                    for="rsync-remote-path"><?php _e('Remote Path',
                                    'wp-filebase') ?></label></th>
                        <td width="100%">
                            <input id="rsync-remote-path" name="remote_path"
                                   type="text"
                                   value="<?php echo esc_attr($this->remote_path); ?>" <?php disabled($this->last_sync_time
                                > 0); ?> />
                            <?php if ($this->last_sync_time <= 0 || $this->num_files == 0) { ?>
                                <br/>Enter the remote path to sync or select a directory below.
                                <br/>
                                <div
                                        style="width: 300px; height: 200px; overflow: auto; border: 1px solid #ddd; background-color: #fff;"><?php $this->PrintBrowser('rsync-remote-path'); ?></div>
                                <br/><a href="" id="rsync-browser-refresh">Refresh</a>
                            <?php } ?>
                        </td>
                    </tr>
                <?php }
                $this->DisplayFormFields(); ?>
            </table>
            <p class="submit"><input type="submit" name="submit"
                                     class="button-primary"
                                     value="<?php echo esc_attr(__('Save Changes')); ?>"/>
            </p>
        </form>
        <?php

    }

    final protected function PrintBrowser($path_input_id)
    {
        wp_print_scripts('wpfb-treeview');
        wp_print_styles('wpfb-treeview');
        ?>

        <ul id="rsync-browser" class="filetree">
        </ul>
        <ul class="treeview" id="filetree-loading" style="margin: 30px;">
            <li class="placeholder"></li>
        </ul>
        <script type="text/javascript">
            //<![CDATA[
            function initRsyncBrowser() {
                jQuery("#rsync-browser").empty().treeview({
                    url: "<?php echo WPFB_Core::$ajax_url ?>", // TODO!!!
                    ajax: {
                        data: {
                            wpfb_action: "rsync-browser",
                            rsync_id: '<?php echo $this->id; ?>',
                            onclick: "selectDir('%s')",
                            dirs_only: false
                        },
                        type: "post", complete: browserAjaxComplete
                    },
                    animated: "medium"
                });
            }

            jQuery(document).ready(function () {
                initRsyncBrowser();
                jQuery('#rsync-browser-refresh').click(function (e) {
                    initRsyncBrowser();
                    return false;
                });
            });

            function selectDir(path) {
                jQuery('#<?php echo $path_input_id ?>').val(path);
            }

            function browserAjaxComplete(jqXHR, textStatus) {
                jQuery('#filetree-loading').hide();
                if (textStatus != "success") {
                    //alert("AJAX Request error: " + textStatus);
                }
            }
            //]]>
        </script>
        <?php
    }

    /**
     * This is a "thread-safe" cache implementation. On first cache miss the $key is locked for 30 seconds
     * Concurrent misses will block until data is available (or timeout after 30 seconds)
     *
     *
     *
     * @param $key
     *
     * @return mixed|bool Cache object or false on miss
     */
    protected function cacheGet($key)
    {
        $key = md5(is_string($key) ? $key : serialize($key));
        $res = get_transient("wpfb_rsync_{$this->id}_{$key}");
        if ($res === false) {
            $miss_key = "wpfb_rsync_{$this->id}_{$key}_miss";
            if (!($t = get_transient($miss_key))) {
                set_transient($miss_key, microtime(), 30);
                return false;
            }

            WPFB_Core::LogMsg("Spin wait for cache $key");

            do {
                usleep(1000 * (10 + 500 * mt_rand() / mt_getrandmax()));
                wp_cache_flush();
            } while (($t = get_transient($miss_key)));

            $res = get_transient("wpfb_rsync_{$this->id}_{$key}");

            if ($res !== false)
                WPFB_Core::LogMsg("Cache retrieve after block $key!");
        }

        return $res;
    }

    protected function cacheSet($key, $value, $expiration = 600)
    {
        $key = md5(is_string($key) ? $key : serialize($key));

        //WPFB_Core::LogMsg("$this->title cacheSet $key on request $_SERVER[REQUEST_TIME_FLOAT]");

        $k = "wpfb_rsync_{$this->id}_{$key}";
        $res = set_transient($k, $value,
            $expiration);
        delete_transient("wpfb_rsync_{$this->id}_{$key}_miss");

        if (!$res) {
            $ser_val = serialize($value);
            if ($ser_val === serialize(get_transient($k)) || is_null($value))
                return true;

            $c = strlen($ser_val);
            $d = substr($ser_val, 0, 20) . ' ...';
            $this->progress_reporter && $this->progress_reporter->LogError("Could not set transient for cache key $key (data len: $c, data: $d)!");
        }

        return $res;
    }

    public function cacheFlush()
    {
        global $wpdb;

        $transient_names
            = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpfb_rsync_{$this->id}%'");
        $p = strlen('_transient_');
        $res = array(0, 0);

        $n = count($transient_names);
        if ($this->progress_reporter)
            $this->progress_reporter->Log("Flushing cache ($n).");

        foreach ($transient_names as $tn) {
            $res[0 + delete_transient(substr($tn, $p))]++;
        }

        wp_cache_flush();

        $this->progress_reporter
        && $this->progress_reporter->Log("Flushed cache ( [error,ok] = "
            . json_encode($res) . " )");


        WPFB_Core::LogMsg("cache Flush for $this, $res[1] ok" . ($res[0]
                ? ", $res[0] errors" : ""), "sync");

        return $res[0] == 0;
    }

    protected static function dirname($path)
    {
        $path = rtrim($path, '/');
        $p = strrpos($path, '/');
        if ($p <= 0) {
            return '/';
        }

        return substr($path, 0, $p);
    }

    protected static function findBy($field_name, $field_value, $items)
    {
        //CODELYFE-CREATE-FUNCTION-FIX-SOLVE
        $r = array_filter($items, create_function('$o',
            'return $o' . (is_object(reset($items)) ? ('->' . $field_name)
                : ('[\'' . $field_name . '\']')) . ' == \'' . $field_value
            . '\';'));

        return reset($r);
    }

    function __toString()
    {
        return "{$this->title} (rsync:{$this->id})";
    }

    protected function log($msg)
    {
        if ($this->progress_reporter)
            $this->progress_reporter->Log($msg);
        else
            WPFB_Core::LogMsg("cloudsync $this: $msg", 'sync');
    }

    /**
     * @return WPFB_RemoteSync
     */
    final function cloneMe ()
    {
        $rsync2 = clone $this;
        $rsync2->root_cat_id = 0;
        $rsync2->num_files = 0;
        $rsync2->id = uniqid();
        $rsync2->title .= " copy";
        $rsync2->last_sync_time = 0;
        return $rsync2;
    }

}

/**
 * Sate used in CloudSync API
 */
class WPFB_RemoteFileInfo
{
    var $path;
    var $size;
    var $mtime;
    var $rev;
    var $is_dir;
    var $display_name;
    var $guid;
}

/**
 * States for DB storage
 */
class WPFB_RemoteSyncMeta
{
    var $file_id;
    var $rev;
    var $guid;
    var $rsync_id;

    /**
     * @var int
     */
    var $uri_expires;
    var $deleted_path;
    var $preview_url;

    /**
     * WPFB_RemoteSyncMeta constructor.
     * @param $rfi WPFB_RemoteFileInfo
     * @param $rsync WPFB_RemoteSync
     * @param $file WPFB_File
     */
    function __construct($rfi, $rsync, $file)
    {
        $this->rev = $rfi->rev;
        $this->guid = $rfi->guid;
        $this->rsync_id = $rsync->GetId();
        $this->file_id = $file->GetId();
    }
}

class WPFB_LocalFileState
{
    /**
     * @var WPFB_RemoteFileInfo
     */
    var $info;

    /**
     * Local path relative upload dir
     *
     * @var string
     */
    var $local_path_rel;

    /**
     * @var int
     */
    var $file_id;

    function __construct($path_rel, $info = null, $file_id = 0)
    {
        $this->info = $info;
        $this->local_path_rel = $path_rel;
        $this->file_id = $file_id;
    }

    function exists()
    {
        return !!$this->file_id;
    }

    function changed()
    {
        return !is_null($this->info);
    }

    function getLocalPath()
    {
        return str_replace('//', '/', WPFB_Core::UploadDir() . '/' . $this->local_path_rel);
    }

}

class RemoteSyncException extends Exception
{

    public function __construct($err = null, $isDebug = false)
    {
        if (is_null($err)) {
            $el = error_get_last();
            $this->message = $el['message'];
            $this->file = $el['file'];
            $this->line = $el['line'];
        } elseif (is_wp_error($err)) {
            /** @var WP_Error $err */
            $this->message = $err->get_error_message();
        } else {
            $this->message = strval($err);
        }

        WPFB_Core::LogMsg(__CLASS__ . ': ' . $this->message, 'sync');

        if ($isDebug) {
            self::display_error($err, true);
        }
    }

    public static function display_error($err, $kill = false)
    {
        print_r($err);
        if ($kill === false) {
            die();
        }
    }


}
