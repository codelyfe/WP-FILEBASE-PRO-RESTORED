<?php

class WpfbTemplatesTest extends WP_UnitTestCase {
    function test01()
    {
        $files = new TestFileSet();

        $res = WPFB_Admin::InsertFile(array(
            'file_remote_uri' => 'file://'.$files->getImageBanner(),
            'file_category' => 0));
        $this->assertEmpty($res['error'],$res['error']);
        /** @var WPFB_File $file01 */
        $file01 = $res['file'];

        $width = $file01->get_tpl_var('file_info/video/resolution_x');
        $height = $file01->get_tpl_var('file_info/video/resolution_y');

        $this->assertGreaterThan(1, $width);
        $this->assertGreaterThan(1, $height);
    }

}

