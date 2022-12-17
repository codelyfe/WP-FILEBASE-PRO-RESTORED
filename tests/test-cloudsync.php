<?php

class CloudSyncTest extends WP_UnitTestCase {

    /**
     * @return WPFB_RemoteSync[]
     */
    function loadCloudSyncs()
    {
        $plugins_dir = dirname(dirname( dirname( __FILE__ ) ) );
        $this->assertEquals($plugins_dir, WP_PLUGIN_DIR, 'Running test from wrong sources!');

        $dir = getenv('CLOUDSYNC_IMPORT_DIR');
        $this->assertNotEmpty($dir, 'env var CLOUDSYNC_IMPORT_DIR not set!');
        $this->assertTrue(is_dir($dir), "Not a dir $dir");
        $files = list_files($dir);

        $this->assertNotEmpty($files, "No files in $dir");

        $cloud_syncs_serialized = [];

        foreach($files as $file) {
            if(substr($file,-5) != ".json")
                continue;

            $json = file_get_contents($file);
            $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
            $import_set = json_decode($json);
            $this->assertNotEmpty($import_set, "invalid json $json");
            foreach($import_set as $key => $import)
            {
                if(strpos($key, '__meta') !== false)
                    continue;

                $meta_key = $key.'__meta';
                $meta = $import_set->$meta_key;
                $this->assertNotEmpty($meta);
                $this->assertFileExists(WP_PLUGIN_DIR.'/'.$meta->plugin_slug);
                $plugins = get_plugins('/'.$meta->plugin_slug);
                $this->assertNotEmpty($plugins, 'Plugin no active: '.$meta->plugin_slug);

                $plugin_file = WP_PLUGIN_DIR.'/'.$meta->plugin_slug.'/'.array_keys($plugins)[0];
                $this->assertFileExists($plugin_file);

                require_once $plugin_file;

                $cloud_syncs_serialized[] = base64_decode($import);
            }
        }

        // this does action 'wpfilebase_register_rsync_service'
        // registers all classes
        wpfb_loadclass('RemoteSync');

        $cloud_syncs = array_map('unserialize', $cloud_syncs_serialized);

        foreach($cloud_syncs as $cs)
            $this->assertNotEmpty($cs);

        return $cloud_syncs;
    }

    /**
     * @param $cs WPFB_RemoteSync
     */
    function _testCloudSync($cs)
    {
        wpfb_loadclass('Admin','ProgressReporter');

        $files = new TestFileSet();

        $this->assertTrue(set_transient("wpfb_rsync_ttest", 'test', 100));
        $this->assertTrue(set_transient("wpfb_rsync_ttest", 'test2', 100));

        $progress_reporter = new WPFB_ProgressReporter(false);
        $progress_reporter->enableThrowExceptions();
        $progress_reporter->enableTextOutput();

        $cat_id = WPFB_Admin::CreateCatTree(WPFB_Core::UploadDir().'/cloudsyncs/'.sanitize_file_name($cs->GetTitle()).'_'.$cs->GetId().'/file');

        $cat = WPFB_Category::GetCat($cat_id);
        WPFB_Admin::Mkdir($cat->GetLocalPath());

        $this->assertGreaterThan(0, $cat_id);
        $this->assertFileExists(WPFB_Category::GetCat($cat_id)->GetLocalPath());

        $usr = get_user_by('login', 'test_admin');
        if(!$usr || !$usr->exists()) {
            $usr = wp_create_user('test_admin', 'test_admin');
            $this->assertNotWPError($usr);
        }
        wp_set_current_user($usr);

        // delete any existing files
        foreach($cat->GetChildFiles(true) as $f) {
            $f->Remove();
        }

        foreach($cat->GetChildCats(true) as $c) {
            $c->Delete();
        }

        $cat->DBReload();
        $cs->SetCat($cat_id);


        // to a sync first to clean
        $cs->cacheFlush();
        $result = $cs->Sync(true, $progress_reporter);
        $this->assertTrue($result, 'Sync() did not return true!');

        $res = WPFB_Admin::InsertFile(array(
            'file_remote_uri' => 'https://wpfilebase.com/wp-content/blogs.dir/2/files/2015/03/banner_023.png',
            'file_category' => $cat_id
        ));
        /** @var WPFB_File $file01 */
        $file01 = $res['file'];
        $this->assertEmpty($res['error'], $res['error']);
        WPFB_Sync::ScanFile($file01);

		$res = WPFB_Admin::InsertCategory(array(
            'cat_folder' => 'subcat_00',
            'cat_parent' => $cat_id,
        ));
		
		
        $res = WPFB_Admin::InsertCategory(array(
            'cat_folder' => 'subcat01',
            'cat_parent' => $cat_id,
        ));
        $subcat_id = $res['cat_id'];
        $subcat = $res['cat'];

        $this->assertEmpty($res['error'], $res['error']);
        $this->assertGreaterThan(0, $subcat_id);


        $res = WPFB_Admin::InsertFile(array(
            'file_remote_uri' => 'file://'.$files->getSmallTxt(),
            'file_category' => $subcat_id
        ));
        $this->assertEmpty($res['error'],$res['error']);
        /** @var WPFB_File $file01 */
        $file02 = $res['file'];
        WPFB_Sync::ScanFile($file02);

        $cat->DBReload();
        $subcat->DBReload();

        $all_files = $cat->GetChildFiles(true);
		//if(2 != count($all_files)) {
		//	echo ;
		//}
        $this->assertEquals(2, count($all_files), "expected only $file01 and $file02 in $cat, but have:\n".print_r($all_files, true));

        echo "\nfile list of $cat:\n";
        foreach($cat->GetChildFiles(true) as $f) {
            echo "$f\n";
        }
        echo "\n";




        // sync for first time. this should upload the 2 test files
        $result = $cs->Sync(true, $progress_reporter);
        echo "1. sync done\n";
        $this->assertTrue($result, 'Sync() did not return true!');


        foreach($cat->GetChildFiles(true) as $f) {
            $f->DBReload();

            $this->assertTrue($f->IsRemote(), "File $f is not remote after sync!");

            $this->assertFileNotExists($f->GetLocalPath());

            $url = $f->GetRemoteUri(false);
            $this->assertNotEmpty($url);
            $rfi = WPFB_Admin::GetRemoteFileInfo($url);
            $this->assertNotWPError($rfi);
            $this->assertNotEmpty($rfi['type']);

            // download test
            echo "test download file $f (from $url)\n";
            $res = WPFB_Admin::SideloadFile($f, $f->GetLocalPath());
            $this->assertEmpty($res['error'],$res['error'].' '.$f->GetRemoteUri(false));
            $this->assertFileExists($f->GetLocalPath());
            $this->assertEquals($f->file_size, filesize($f->GetLocalPath()));

            $this->assertEquals($f->file_hash, md5_file($f->GetLocalPath()), 'File hashes do not match!');

            unlink($f->GetLocalPath());
            
            
            // check preview url
            if($cs->SupportFilePreviews()) {
                $preview_url = $f->GetRemoteUri(true);
                $this->assertNotEmpty($url);
                $this->assertNotEquals($preview_url, $url, 'Preview and download links are equal');

                $rfi = WPFB_Admin::GetRemoteFileInfo($preview_url);
                $this->assertNotWPError($rfi);
                $this->assertNotEmpty($rfi['type']);

                echo "checking preview".$rfi['type'];
            }

            $this->assertEquals($cs->GetId(), $f->GetRemoteSyncMeta()->rsync_id);
        }

        // delete all local file entries
        foreach($cat->GetChildFiles(true) as $f) {
            $f->Remove();
        }



        // sync again. this should remove the files from cloud
        $result = $cs->Sync(true, $progress_reporter);
        $this->assertTrue($result, 'Sync() did not return true!');
    }


    function testAllSyncs() {
        $syncs = $this->loadCloudSyncs();

        foreach($syncs as $sync) {
            $this->_testCloudSync($sync);
        }
    }

}

