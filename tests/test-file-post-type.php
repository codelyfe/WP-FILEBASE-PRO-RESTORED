<?php
class FilePostTypeTest extends WP_UnitTestCase {

	function test_cat_tree() {
        wpfb_loadclass('Admin');

        $depth = 4;

        /** @var WPFB_Category $parent */
        $parent = null;

        $cats = array();

        for($d = 0; $d < $depth; $d++) {
            $res = WPFB_Admin::InsertCategory(array('cat_name' => "layer $d", 'cat_parent' => $parent ? $parent->GetId() : 0));
            $this->assertEmpty($res['error']);
            /** @var WPFB_Category $cat */
            $cat = $res['cat'];

            $this->assertTrue($parent ? $cat->GetParent()->Equals($parent) : (is_null($cat->GetParent())));

            $cats[] = $cat;
        }
	}

    function test_date() {
        $t = random_int(time()/2, time());
        date_default_timezone_set('America/Los_Angeles');
        $this->assertEquals($t, mysql2date('G', gmdate('Y-m-d H:i:s', $t)));
    }
}

