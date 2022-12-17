<?php

class WpfbSortingTest extends WP_UnitTestCase
{
    function testParseSorting()
    {
        WPFB_Core::$settings->filelist_sorting = 'file_name';

        $this->assertArraySubset(WPFB_Item::ParseSorting('<file_name'), ['file_name', 'ASC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting('>file_size'), ['file_size', 'DESC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting('+file_size'), ['file_size', 'ASC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting('-file_size'), ['file_size', 'DESC']);


        $this->assertArraySubset(WPFB_Item::ParseSorting(esc_html('<file_mtime')), ['file_mtime', 'ASC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting(esc_html('>file_mtime')), ['file_mtime', 'DESC']);

        $this->assertArraySubset(WPFB_Item::ParseSorting(esc_html('<file_not_a_field')), ['file_name', 'ASC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting(esc_html('>file_not_a_field')), ['file_name', 'DESC']);

        $this->assertArraySubset(WPFB_Item::ParseSorting('<file_size', true), ['cat_name', 'ASC']);
        $this->assertArraySubset(WPFB_Item::ParseSorting('>file_size', true), ['cat_name', 'DESC']);
    }


    function testSort()
    {
        $testFiles = new TestFileSet();
        $files = [];
        $nameFmt = 'testSortingFile%09d';

        for ($i = 0; $i < 10; $i++) {
            $res = WPFB_Admin::InsertFile(array(
                'file_remote_uri' => 'file://' . $testFiles->getImageBanner(),
                'file_category' => 0,
                'file_display_name' => sprintf($nameFmt, $i)));
            $this->assertEmpty($res['error'], $res['error']);
            $files[] = $res['file'];
        }

        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals(sprintf($nameFmt, $i), $files[$i]->file_display_name);
        }

        shuffle($files);
        shuffle($files);


        WPFB_Item::Sort($files, "file_display_name ASC");

        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals(sprintf($nameFmt, $i), $files[$i]->file_display_name);
        }
    }

}

