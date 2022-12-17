<?php
namespace WPFB;


class PostTypeLib
{
    /**
     * Sync changes from wpfb_filepage to WPFB_File
     *
     * @param int $post_id The post ID.
     * @param \WP_Post $post The post object.
     * @param bool $update Whether this is an existing post being updated or not.
     */
    static function savePost($post_id, $post, $update)
    {
        // post field name => file field name
        static $bindings = array(
            'post_title' => 'file_display_name',
            'post_author' => 'file_added_by',
            'menu_order' => 'file_attach_order',
            'post_date' => 'file_date',
            //'post_name' => 'file_name',
            'post_password' => 'file_password',
        );


        $post_type = get_post_type($post_id);
        if ('wpfb_filepage' != $post_type) return;

        wpfb_loadclass('File');
        $file = \WPFB_File::GetByPost($post_id);
        if (!$file) return;

        $changed = 0;
        foreach ($bindings as $p => $f) {
            if ($post->$p != $file->$f) {
                $changed++;
                $file->$f = $post->$p;
            }
        }

        if ($changed) {
            if ($file->IsLocked()) {
                \WPFB_Core::LogMsg("Error: Detected wpfb_filepage import changes, could not sync these to $file because its locked!");
                return;
            }
            //return;
            \WPFB_Core::LogMsg("Detected $changed changes to file post object (ID $post_id), synced to file object ($file)!");
            $file->DBSave();
        }
    }

}