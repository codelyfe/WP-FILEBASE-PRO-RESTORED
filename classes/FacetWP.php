<?php
namespace WPFB;

if (!class_exists('\WPFB\FacetWP')) {

    class FacetWP
    {
        /**
         * @param $post_ids
         * @param $class \FacetWP_Facet
         * @return mixed
         */
        static function filteredPostIds($post_ids, $class)
        {
            if ($class->query_args['post_type'] !== 'any' && $class->query_args['post_type'] !== 'wpfb_filepage' && (!is_array($class->query_args['post_type']) || !in_array('wpfb_filepage', $class->query_args['post_type'])))
                return $post_ids;

            wpfb_loadclass('File');

            $filtered_post_ids = array();
            foreach ($post_ids as $post_id) {
                $file = \WPFB_File::GetByPost($post_id);
                if (!$file || $file->CurUserCanAccess(true)) {
                    $filtered_post_ids[] = $post_id;
                }
            }
            return $filtered_post_ids;
        }
    }
}