<?php
/**
 * Created by PhpStorm.
 * User: msn
 * Date: 01.09.2017
 * Time: 14:15
 */

namespace WPFB;


class FilePicker
{
    public static function getEmbedUrl()
    {
        return admin_url('admin.php?wpfilebase-screen=editor-plugin&pick-file=1');
    }
}