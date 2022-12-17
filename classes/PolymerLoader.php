<?php

namespace WPFB;

class PolymerLoader
{

    static function htmlHead()
    {
        ?>
        <script src="<?php echo WPFB_PLUGIN_URI; ?>bower_components/webcomponentsjs/webcomponents-loader.js"></script>
        <link rel="import" href="<?php echo WPFB_PLUGIN_URI; ?>bower_components/polymer/polymer.html">
        <link rel="import" href="<?php echo WPFB_PLUGIN_URI; ?>elements/index.html">
    <?php
    }
}