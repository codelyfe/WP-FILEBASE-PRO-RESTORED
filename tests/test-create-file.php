<?php

class CreateFileTest extends WP_UnitTestCase
{

    function test_new_file_remote()
    {

        $usr = get_user_by('login', 'test_admin');
        if (!$usr || !$usr->exists()) {
            $usr = wp_create_user('test_admin', 'test_admin');
            $this->assertNotWPError($usr);
        } else {
            $usr = $usr->ID;
        }

        wp_set_current_user($usr);

        // hashes for https://wpfilebase.com/wp-content/blogs.dir/2/files/2015/03/banner_023.png
        $hash_md5 = 'beb7c08d36bd4905f55ac4e60bc6abd0';
        $hash_sha256 = '9d7cea30c8445186da0c85f925e12c44c8416cd25434a5789c3ae6e79233ceca';

        wpfb_loadclass('Admin');
        $res = WPFB_Admin::InsertFile(array(
            /* must be an image ! */
            'file_remote_uri' => 'https://wpfilebase.com/wp-content/blogs.dir/2/files/2015/03/banner_023.png'
        ));
        $this->assertEmpty($res['error'], $res['error']);

        /** @var WPFB_File $file */
        $file = $res['file'];

        $this->assertTrue($file->IsLocal(), 'IsLocal false');
        $this->assertFileExists($file->GetLocalPath());

        $this->assertEmpty($file->GetRemoteUri());
        $this->assertEmpty($file->GetRemoteUri(true));
        $this->assertEmpty($file->GetRemoteUri(false));

        $this->assertNotEmpty($file->file_thumbnail);
        $this->assertFileExists($file->GetThumbPath());

        $this->assertEquals($hash_md5, $file->file_hash);
        $this->assertEquals($hash_sha256, $file->file_hash_sha256);

        $this->assertTrue($file->Remove());
    }
}

