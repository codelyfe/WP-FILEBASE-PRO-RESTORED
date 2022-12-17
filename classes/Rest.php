<?php
namespace WPFB;

/*
 *
 * TODO filter by cat:
 * https://github.com/WP-API/rest-filter/blob/master/plugin.php
 * - search tag!
 */

class Rest
{
    static function prepareFilepage($response, $post, $request)
    {
        //print_r($post);
        $response->data['file_id'] = +get_post_meta($post->ID, 'file_id', true);

        $size = +get_post_meta($post->ID, 'file_size', true);
        $response->data['file_size'] = array('rendered' => \WPFB_Output::FormatFilesize($size), 'raw' => $size);

        $response->data['file_link'] = get_post_meta($post->ID, 'file_url', true);

        $article_id = +get_post_meta($post->ID, 'file_post_id', true);
        if($article_id)
            $response->data['file_article_link'] = get_permalink($article_id);

        $response->data['file_version'] = get_post_meta($post->ID, 'file_version', true);

        $response->data['file_views'] = +get_post_meta($post->ID, 'file_hits', true);

        $response->data['file_icon_link'] = get_post_meta($post->ID, 'file_icon_url', true);

         /*   file_version
            file_hits

           */

        return $response;
    }
}