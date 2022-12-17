<?php

class AdminTest extends WP_UnitTestCase
{
    function testStatic()
    {
        wpfb_loadclass('Admin');

        // this is risky:
        //WPFB_Admin::DisableOutputBuffering();
        // WPFB_Admin::DisableOutputBuffering(true);


        $settings = WPFB_Admin::SettingsSchema();
        $this->assertNotEmpty($settings);

        $res = WPFB_Admin::ParseFileNameVersion('wpfb-v4.3.zip');
        $this->assertEquals('4.3', $res['version']);
        $this->assertEquals('Wpfb', $res['title']);
    }
}
