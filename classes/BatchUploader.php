<?php

/**
 * Description of BatchUploader
 *
 * @author flap
 */
class WPFB_BatchUploader
{

    var $prefix;
    var $presets;

    var $embedded_form = null;
    var $hidden_vars = array();

    public function __construct($prefix = 'batch', $presets = array())
    {
        $this->prefix = str_replace('-','_',$prefix);
        $this->presets = $presets; //TODO
    }

    public function SetEmbeddedForm($form, $vars)
    {
        $this->embedded_form = $form;
        $this->hidden_vars = $vars;
        $this->hidden_vars['frontend_upload'] = true;
        $this->hidden_vars['prefix'] = $this->prefix;
    }

    public function Display()
    {
        wpfb_call('Output', 'PrintJS');
        wp_print_scripts('utils'); // setUserSetting
        ?>
        <style type="text/css"
               media="screen">@import url(<?php echo WPFB_PLUGIN_URI.'css/batch-uploader.css' ?>);</style>
        
        <div id="<?php echo $this->prefix; ?>-uploader-wrap">
            <div id="<?php echo $this->prefix; ?>-uploader-interface" class="wpfb-batch-uploader-interface">
                <div class="form-wrap uploader-presets" id="<?php echo $this->prefix; ?>-uploader-presets">
                    <form method="POST" action="" class="validate" name="batch_presets">
                        <?php                         if (!$this->embedded_form) {  ?>
                            <h2><?php _e('Upload Presets', 'wp-filebase'); ?></h2> <?php                         } ?>
                        <?php
                                                if ($this->embedded_form) {
                            WPFB_Output::DisplayExtendedFormFields($this->prefix, $this->embedded_form->secret_key, $this->hidden_vars, $this->embedded_form->extended);
                            echo $this->embedded_form->GetCform7Html();
                        } else   {
                            self::DisplayUploadPresets($this->prefix);
                            //wp_nonce_field('batch-presets'); // TODO validate this!
                        }
                        ?>
                    </form>
                </div>

                <div id="<?php echo $this->prefix; ?>-drag-drop-uploader" class="drag-drop-uploader">
                    <?php                     if (!$this->embedded_form) { ?> <h2>Drag &amp; Drop</h2> <?php                     } ?>
                    <div id="<?php echo $this->prefix; ?>-drag-drop-area" class="drag-drop-area">
                        <div style="margin: 70px auto 0;">
                            <p class="drag-drop-info"><?php _e('Drop files here'); ?></p>
                            <p><?php _ex('or', 'Uploader: Drop files here - or - Select Files'); ?></p>
                            <p class="drag-drop-buttons"><input id="<?php echo $this->prefix; ?>-browse-button"
                                                                type="button"
                                                                value="<?php esc_attr_e('Select Files'); ?>"
                                                                class="button"/></p>
                        </div>
                    </div>
                    <div id="<?php echo $this->prefix; ?>-uploader-errors"></div>
                </div>

                <div style="clear: both;"></div>
            </div>

            <div id="<?php echo $this->prefix; ?>-uploader-files" style="position:relative;"></div>
        </div>

        <?php
        wp_print_scripts('jquery-color');
        wp_print_scripts('jquery-deserialize');
        ?>

        <script type="text/javascript">

            var mouseDragPos = [];
            var morePresets = 0;

            jQuery(document).ready(function () {
                var form = jQuery('#<?php echo $this->prefix; ?>-uploader-presets').find('form');

                jQuery('#<?php echo $this->prefix; ?>-drag-drop-area').bind('dragover', function (e) {
                    mouseDragPos = [e.originalEvent.pageX, e.originalEvent.pageY];
                });

                <?php if(!$this->embedded_form) { ?>
                wpfb_setupFormAutoSave(form, 'batch_presets');
                <?php } ?>


                var batchUploaderSetPresetsMore = function (m) {
                    if (isNaN(m)) m = 0;
                    var form = jQuery('#<?php echo $this->prefix; ?>-uploader-presets').find('form');

                    form.find('tr.more')[m == 0 ? 'hide' : 'show'](400);
                    form.find('tr.more-more')[m != 2 ? 'hide' : 'show'](400);

                    // TODO show any field with non-default value!!

                    //form.find('tr.more').toggle(morePresets > 0);
                    //form.find('tr.more-more').toggle(morePresets > 1);

                    if (typeof(setUserSetting) !== 'undefined') setUserSetting('wpfb_batch_presets_more', '' + morePresets);
                    jQuery('#<?php echo $this->prefix; ?>-uploader-presets-more-toggle td span').html(m == 2 ? '<?php _e('less'); ?>' : '<?php _e('more'); ?>');
                };

                // "more" toggle init
                form.find('tr.more').hide();
                form.find('tr.more-more').hide();
                morePresets = 0;
                jQuery('#<?php echo $this->prefix; ?>-uploader-presets-more-toggle').click(function () {
                    batchUploaderSetPresetsMore(morePresets = ((morePresets + 1) % 3));
                });
                batchUploaderSetPresetsMore(typeof(getUserSetting) !== 'function' || getUserSetting('wpfb_batch_presets_more') || 0);
            });

            var <?php echo $this->prefix; ?>callbacks = {
                batchUploaderFilesQueued: function (up, files) {
                    var form = jQuery('#<?php echo $this->prefix; ?>-uploader-presets').find('form');
                    up.settings.multipart_params["presets"] = form.serialize();

                    var hidden_params = form.find('input[type=hidden]').serializeArray();
                    for (var i = 0; i < hidden_params.length; ++i) {
                        up.settings.multipart_params[hidden_params[i].name] = hidden_params[i].value;
                    }

                    form
                        .css({background: "rgba(255,255,0,0.0)"})
                        .animate({backgroundColor: "rgba(255,255,0,0.5)"}, 100)
                        .animate({backgroundColor: "rgba(255,255,0,0.0)"}, 400);

                    form.find('input,textarea,select')
                        .animate({opacity: 0.2}, 100)
                        .animate({opacity: 1.0}, 400);

                    form.find("input[name='file_display_name']").val('');
                },

                batchUploaderFileQueued: function (up, file) {
                    //file.name, file.size

                    jQuery('#<?php echo $this->prefix; ?>-uploader-files').prepend('<div id="<?php echo $this->prefix; ?>-uploader-file-' + file.id + '-spacer" class="batch-uploader-file-spacer"></div>');

                    jQuery('#<?php echo $this->prefix; ?>-uploader-files').prepend('<div id="' + file.dom_id + '" class="media-item batch-uploader-file">' +
                        '<div class="progress"><div class="percent">0%</div><div class="bar"></div></div>' +
                        '<img src="<?php echo site_url(WPINC . '/images/crystal/default.png'); ?>" alt="Loading..." /><span class="filename">' + file.name + '</span><span class="error"></span></div>');


                    var fileEl = jQuery('#' + file.dom_id);
                    var spacerEl = jQuery('#<?php echo $this->prefix; ?>-uploader-file-' + file.id + '-spacer');
                    var dest = fileEl.offset();
                    var ppos = fileEl.parent().offset();
                    var destWidth = fileEl.width();

                    fileEl.css({
                        position: 'absolute',
                        zIndex: 100,
                        top: mouseDragPos[1] - ppos.top,
                        left: mouseDragPos[0] - ppos.left - 15
                    });

                    fileEl.animate({
                        //opacity: 0.25,
                        left: dest.left - ppos.left,
                        top: dest.top - ppos.top
                    }, 400, function () {
                        spacerEl.remove();
                        var startWidth = jQuery(this).width();
                        jQuery(this)
                            .css({position: '', top: 0, left: 0, width: startWidth})
                            .animate({width: destWidth}, 200);
                    });

                    spacerEl.animate({height: fileEl.outerHeight(true)}, 400);

                    jQuery('.error', fileEl).hide();
                },

                batchUploaderSuccess: function (file, serverData) {
                    var item = jQuery('#' + file.dom_id);

                    if (!serverData || serverData == -1 || 'object' != typeof(serverData)) {
                        jQuery('.error', item).show().html('Server response error! ' + serverData);
                        console.log(serverData);
                        return;
                    }

                    var url = serverData.file_cur_user_can_edit ? serverData.file_edit_url : serverData.file_download_url;
                    jQuery('.filename', item).html('<a href="' + url + '" target="_blank">' + serverData.file_display_name + '</a> <span class="ok"><?php _e('Upload OK!', 'wp-filebase') ?></span>');
                    jQuery('img', item).attr('src', serverData.file_thumbnail_url);
                }
            };
        </script>
        <?php
        wpfb_loadclass('PLUploader');
        $uploader = new WPFB_PLUploader();
        $uploader->js_file_queued = $this->prefix.'callbacks.batchUploaderFileQueued';
        $uploader->js_files_queued = $this->prefix.'callbacks.batchUploaderFilesQueued';
        $uploader->js_upload_success = $this->prefix.'callbacks.batchUploaderSuccess';

        $uploader->post_params['file_add_now'] = true;

        if (!empty($this->hidden_vars))
            $uploader->post_params = array_merge($uploader->post_params, $this->hidden_vars);

        $uploader->Init($this->prefix . '-drag-drop-area', $this->prefix . '-browse-button', $this->prefix . '-uploader-errors');
    }

    static function DisplayUploadPresets($prefix, $cat_select = true)
    {
        $defaults = array(
            'display_name' => '',
            'category' => 0,
            'tags' => '',
            'description' => '',
            'version' => '',
            'author' => '',
            'license' => '',
            'post_id' => 0,
            'languages' => '',
            'offline' => 0,
            'user_roles' => '',
            'direct_linking' => 1,
            'platforms' => '',
            'requirements' => '',
        );
        ?>
        <table class="form-table">

            <?php if ($cat_select) { ?>
                <tr class="form-field">
                    <th scope="row"><label for="batch_category"><?php _e('Category') ?></label></th>
                    <td><select name="file_category" id="<?php echo $prefix; ?>_category" class="wpfb-cat-select">
                            <?php echo WPFB_Output::CatSelTree(array('selected' => $defaults['category'] , 'check_add_perm' => true , 'add_cats' => true)) ?>
                        </select>
                    </td>
                </tr>
            <?php } ?>

            <tr class="form-field">
                <th scope="row"><label for="batch_tags"><?php _e('Tags') ?></label></th>
                <td><input name="file_tags" id="<?php echo $prefix; ?>_tags" type="text"
                           value="<?php echo esc_attr(trim($defaults['tags'], ',')); ?>" maxlength="250"
                           autocomplete="off"/></td>
            </tr>

            <tr class="form-field">
                <th scope="row"><label for="batch_description"><?php _e('Description') ?></label></th>
                <td><textarea name="file_description" id="<?php echo $prefix; ?>_description"
                              rows="2"><?php echo esc_html($defaults['description']); ?></textarea></td>
            </tr>

            <tr class="form-field">
                <th scope="row"><label for="batch_author"><?php _e('Author') ?></label></th>
                <td><input name="file_author" id="<?php echo $prefix; ?>_author" type="text"
                           value="<?php echo esc_attr($defaults['author']); ?>"/></td>
            </tr>

            <?php if (WPFB_Core::$settings->licenses) { ?>
                <tr class="form-field">
                    <th scope="row"><label for="batch_license"><?php _e('License', 'wp-filebase') ?></label></th>
                    <td><select id="<?php echo $prefix; ?>_license"
                                name="file_license"><?php echo WPFB_Admin::MakeFormOptsList('licenses', $defaults['license'], true) ?></select>
                    </td>
                </tr>
            <?php } ?>

            <tr class="form-field">
                <th scope="row"><label
                        for="<?php echo $prefix; ?>_post_id"><?php _e('Attach to Post', 'wp-filebase') ?></label></th>
                <td>ID: <input type="text" name="file_post_id" class="num" style="width:60px; text-align:right;"
                               id="<?php echo $prefix; ?>_post_id"
                               value="<?php echo esc_attr($defaults['post_id']); ?>"/>
                    <span id="<?php echo $prefix; ?>_post_title"
                          style="font-style:italic;"><?php if ($defaults['post_id'] > 0) echo get_the_title($defaults['post_id']); ?></span>
                    <a href="javascript:;" class="button"
                       onclick="WPFB_PostBrowser('<?php echo $prefix; ?>_post_id', '<?php echo $prefix; ?>_post_title');"><?php _e('Select') ?></a>
                </td>
            </tr>

            <tr>
                <td></td>
                <td><input type="checkbox" name="file_offline" id="<?php echo $prefix; ?>_offline"
                           value="1" <?php checked('1', $defaults['offline']); ?> />
                    <label for="<?php echo $prefix; ?>_offline"
                           style="display: inline;"><?php _e('Don\'t publish uploaded files (set offline)', 'wp-filebase') ?></label>
                </td>
            </tr>

            
            <tr class="form-field more">
                <th scope="row"><label for="<?php echo $prefix; ?>_display_name"><?php _e('Title') ?></label></th>
                <td><input name="file_display_name" id="<?php echo $prefix; ?>_display_name" type="text"
                           value="<?php echo esc_attr($defaults['display_name']); ?>"/></td>
            </tr>

            <tr class="form-field more">
                <th scope="row"><label for="batch_user_roles"><?php _e('Access Permission', 'wp-filebase') ?></label>
                </th>
                <td><!-- <input type="hidden" name="file_perm_explicit" value="1" /> -->
                    <div
                        id="<?php echo $prefix; ?>_user_roles"><?php WPFB_Admin::RolesCheckList('file_user_roles', $defaults['user_roles'], __('Inherit from Cat.', 'wp-filebase')) ?></div>
                </td>
            </tr>


            <tr class="more"> <!-- class="form-field" -->
                <th scope="row"><label for="batch_direct_linking"><?php _e('Direct Linking', 'wp-filebase') ?></label>
                </th>
                <td>
                    <fieldset>
                        <legend class="hidden"><?php _e('Direct Linking', 'wp-filebase') ?></legend>
                        <label title="<?php _e('Yes') ?>"><input type="radio" name="file_direct_linking"
                                                                 value="1" <?php checked('1', $defaults['direct_linking']); ?>/> <?php _e('Allow direct linking', 'wp-filebase') ?>
                        </label>
                        <label title="<?php _e('No') ?>"><input type="radio" name="file_direct_linking"
                                                                value="0" <?php checked('0', $defaults['direct_linking']); ?>/> <?php _e('Redirect to post', 'wp-filebase') ?>
                        </label>
                        
                        <label title="<?php _e('No') ?>"><input type="radio" name="file_direct_linking"
                                                                value="2" <?php checked('2', $defaults['direct_linking']); ?>/> <?php _e('Redirect to File Page', 'wp-filebase') ?>
                        </label>
                        
                    </fieldset>
                </td>
            </tr>

            
            <tr class="form-field more">
                <th scope="row"><label for="batch_password"><?php _e('File Password', 'wp-filebase') ?></label></th>
                <td><input name="file_password" id="<?php echo $prefix; ?>_password" type="text" value="" maxlength="16"
                           autocomplete="off"/></td>
            </tr>
            

            <tr class="form-field more">
                <th scope="row"><label for="batch_version"><?php _e('File Version', 'wp-filebase') ?></label></th>
                <td><input name="file_version" id="<?php echo $prefix; ?>_version" type="text" value="" maxlength="32"
                           autocomplete="off"/></td>
            </tr>

            <?php
            $custom_fields = WPFB_Core::GetCustomFields();
            foreach ($custom_fields as $ct => $cn) {
                $hid = 'file_custom_' . esc_attr($ct);
                ?>
                <tr class="form-field more">
                    <th scope="row"><label for="<?php echo $hid; ?>"><?php echo esc_html($cn) ?></label></th>
                    <td><textarea name="<?php echo $hid; ?>" id="<?php echo $hid; ?>"
                                  rows="1"><?php echo empty($defaults["custom_$ct"]) ? '' : esc_html($defaults["custom_$ct"]); ?></textarea>
                    </td>
                </tr>
            <?php } ?>


            <?php if (WPFB_Core::$settings->languages) { ?>
                <tr class="form-field more-more">
                    <th scope="row"><label for="batch_languages"><?php _e('Languages') ?></label></th>
                    <td><select id="<?php echo $prefix; ?>_languages" name="file_languages[]" multiple="multiple"
                                style="height:80px;"><?php echo WPFB_Admin::MakeFormOptsList('languages', $defaults['languages'], true) ?></select>
                    </td>
                </tr>
            <?php } ?>

            <?php if (WPFB_Core::$settings->platforms) { ?>
                <tr class="form-field more-more">
                    <th scope="row"><label for="batch_platforms"><?php _e('Platforms', 'wp-filebase') ?></label></th>
                    <td><select id="<?php echo $prefix; ?>_platforms" name="file_platforms[]" size="40"
                                multiple="multiple"
                                style="height:80px;"><?php echo WPFB_Admin::MakeFormOptsList('platforms', $defaults['platforms'], true) ?></select>
                    </td>
                </tr>
            <?php } ?>

            <?php if (WPFB_Core::$settings->requirements) { ?>
                <tr class="form-field more-more">
                    <th scope="row"><label for="batch_requirements"><?php _e('Requirements', 'wp-filebase') ?></label>
                    </th>
                    <td><select id="<?php echo $prefix; ?>_requirements" name="file_requirements[]" size="40"
                                multiple="multiple"
                                style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('requirements', $defaults['requirements'], true) ?></select>
                    </td>
                </tr>
            <?php } ?>

            <tr id="<?php echo $prefix; ?>-uploader-presets-more-toggle" class="more-toggle">
                <td colspan="2"><span><?php _e('more') ?></span></td>
            </tr>

            <?php  /*ADV_BATCH_UPLOADER*/
            ?>

        </table>
        <?php
    }
}