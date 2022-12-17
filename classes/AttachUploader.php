<?php class WPFB_AttachUploader
{
    public static function ReturnHTML($post_id, $tpl_tag)
    {
        ob_start();
        self::RenderHTML($post_id, $tpl_tag);
        return ob_get_clean();
    }

    public static function RenderHTML($post_id, $tpl_tag)
    {
        $jss = $post_id;
        $container_id = "wpfb-attach-uploader-$post_id";
        ?>
        <div id="<?php echo $container_id; ?>-files" class="wpfb-attach-uploader-files"></div>
        <div id="<?php echo $container_id; ?>" class="wpfb-attach-uploader"
             style="display:none;"><?php _e('Drop files here to attach or'); ?>
            <input id="<?php echo $container_id; ?>-btn" type="button" value="Select Files" class="button"/>
        </div>
        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready(function () {
            });

            if ('undefined' == typeof wpfb_isScrolledIntoView) {
                function wpfb_isScrolledIntoView(elem) {
                    var docViewTop = jQuery(window).scrollTop();
                    var docViewBottom = docViewTop + jQuery(window).height();

                    var elemTop = elem.offset().top;
                    var elemBottom = elemTop + elem.height();

                    return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
                }
            }

            function wpfb_dtContains(dt, t) {
                if ('undefined' !== typeof dt.types.indexOf)
                    return dt.types.indexOf(t) !== -1;
                if ('undefined' !== typeof dt.types.contains)
                    return dt.types.contains(t);
                for (var s in dt.types) {
                    if (s === t)
                        return true;
                }
                return false;
            }

            jQuery('#<?php echo $container_id; ?>').hide()
                .parent()
                .bind('dragover', function (e) {
                    if(!wpfb_dtContains(e.originalEvent.dataTransfer, "Files"))return;
                    var el = jQuery('#<?php echo $container_id; ?>');
                    if (!wpfb_isScrolledIntoView(el)) jQuery('html, body').animate({scrollTop: el.offset().top - (jQuery(window).height() / 2)}, 500);
                    el.show('fast');
                })
                .bind('drop', function () {
                    jQuery('#<?php echo $container_id; ?>').hide();
                });

            var callbacks<?php echo $jss ?> = {
                fileQueued: function (up, file) {
                    jQuery('#<?php echo $container_id; ?>-files')
                        .append(
                            '<div id="' + file.dom_id + '" class="file wpfilebase-file-default">' +
                            '<img src="<?php echo site_url(WPINC . '/images/crystal/default.png'); ?>" alt="Loading..." style="height:3em;margin-right:0.3em;"/> ' +
                            '<span class="filename">' + file.name + '</span> ' +
                            '<div class="progress"><div class="percent">0%</div><div class="bar"></div></div> ' +
                            '<span class="error"></span> ' +
                            '</div>');
                },
                success: function (file, serverData) {
                    var item = jQuery('#' + file.dom_id);
                    if (serverData.tpl)
                        item.removeClass('wpfilebase-file-default').html(serverData.tpl);
                    //jQuery('.loading,.percent',item).hide();
                }
            };
            //]]>
        </script>
        <?php
        wpfb_loadclass('PLUploader');
        $uploader = new WPFB_PLUploader();
        $cb_prefix = 'callbacks' . $jss . '.';
        $uploader->js_file_queued = $cb_prefix . 'fileQueued';
        $uploader->js_upload_success = $cb_prefix . 'success';
        $uploader->post_params['file_add_now'] = true;
        $uploader->post_params['presets'] = "file_post_id=$post_id";
        $uploader->post_params['tpl_tag'] = $tpl_tag;
        $uploader->Init($container_id);
    }
}