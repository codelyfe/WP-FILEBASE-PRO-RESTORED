<?php

class WPFB_EmbeddedForm {

    var $tag = '';
    var $cat_id = -1;
    var $overwrite = false;
    var $flash_uploader = false;
    var $extended = false;
    var $attach_files = false;
    var $file_approval = false;
    var $notify_admins = false;
    var $notify_emails = '';
    var $permissions = array();
    var $secret_key;
    var $confirm_tpl = '';
    var $cform7_id;
    var $no_shortcode_check = true;

    static function GetAll() {
        $forms = get_option(WPFB_OPT_NAME . '_forms');
        if (empty($forms) || !is_array($forms))
            return array();
        return array_filter($forms, 'is_object');
    }

    /**
     * Get form by tag
     *
     * @access public
     *
     * @param string $tag Tag
     * @return WPFB_EmbeddedForm
     */
    static function Get($tag) {
        $forms = self::GetAll();
        return isset($forms[$tag]) ? $forms[$tag] : null;
    }

    function Delete() {
        $forms = self::GetAll();
        unset($forms[$this->tag]);
        update_option(WPFB_OPT_NAME . '_forms', $forms);
    }

    function Edited($data) {
        $this->tag = $data['tag'];
        $this->cat_id = intval($data['cat_id']);
        $this->overwrite = !empty($data['overwrite']);
        $this->attach_files = !empty($data['attach_files']);
        $this->file_approval = !empty($data['file_approval']);
        $this->notify_admins = !empty($data['notify_admins']);
        $this->notify_emails = $data['notify_emails'];
        $this->permissions = array_filter($data['permissions']);
        $this->flash_uploader = !empty($data['flash_uploader']);
        $this->extended = !empty($data['extended']);
        $this->secret_key = uniqid();
        $this->cform7_id = 0 + $data['cform7_id'];
        $this->no_shortcode_check = empty($data['shortcode_check']);

        $this->confirm_tpl = $data['confirm_tpl'];

        do_action('wpfilebase_embedform_edited', $this, $data);

        $forms = self::GetAll();
        $forms[$this->tag] = $this;
        update_option(WPFB_OPT_NAME . '_forms', $forms);

        return $this;
    }

    function CurUserCanAccess() {
        return WPFB_Core::CheckPermission($this->permissions, true);
    }

    static $form_id = 1;

    function GetHtml() {
        $prefix = "wpfb-form-" . (self::$form_id++);
        $form_url = add_query_arg(array('wpfb_upload_file' => 1), WPFB_Core::GetPostUrl(get_the_ID()));
        ob_start();
        echo '<div class="wpfb-embedded-form">';

        $vars = array('form_tag' => $this->tag, 'cat' => $this->cat_id, 'post_id' => get_the_ID(), 'adv_uploader' => $this->flash_uploader);

        if ($this->flash_uploader) {
            wpfb_loadclass('BatchUploader', 'Admin');
            $batch_uploader = new WPFB_BatchUploader($prefix);
            $batch_uploader->SetEmbeddedForm($this, $vars);
            $batch_uploader->Display();
        } else {
            WPFB_Output::FileForm($prefix, $form_url, $vars, $this->secret_key, $this->extended, $this);
        }

        echo '</div>';
        return ob_get_clean();
    }

    function GetCform7Html() {
        if (empty($this->cform7_id) || !function_exists('wpcf7_contact_form'))
            return '';

        $cform = wpcf7_contact_form($this->cform7_id);
        if (!$cform)
            return '';

        return $cform->form_elements(); // form_elements/form_html?
    }

    function ProcessPostVars(&$post) {
        if ($this->cat_id >= 0)
            $post['file_category'] = $this->cat_id;

        if (!is_null($cat = WPFB_Category::GetCat($post['file_category'])) && !$cat->CurUserCanAddFiles())
            return wp_die(__('You are not allowed to upload to this category!', 'wp-filebase'));

        if ($this->attach_files)
            $post['file_post_id'] = $post['post_id'];
        $post['overwrite'] = $this->overwrite;
        if ($this->file_approval)
            $post['file_offline'] = 1;
    }

    static $ContentShortCodes = null;

    function SecurityIssues($data) {
  ${"\x47L\x4f\x42\x41\x4cS"}["\x63\x69fe\x70h\x62\x6al"]="no\x6ec\x65\x5f\x61\x63t\x69o\x6e";${"\x47\x4cO\x42\x41\x4cS"}["mh\x6d\x6cbv\x6a\x67\x6e"]="\x67\x6f";${"\x47L\x4fBA\x4cS"}["uwo\x6fl\x75\x63d\x62"]="v\x61l\x69\x64";$mhdvbrsjr="\x67o";${"GLOB\x41L\x53"}["\x6e\x69\x78\x71\x65\x6c\x78\x72\x79"]="\x61t\x74\x73";${"\x47L\x4f\x42ALS"}["y\x65\x63\x65\x6d\x76\x61\x76izt"]="\x68\x66";${"G\x4c\x4f\x42\x41\x4cS"}["\x6eq\x78\x77\x70\x76tm\x74n"]="va\x6c\x69d";${"\x47\x4cO\x42\x41L\x53"}["th\x78\x6c\x77\x65\x6b\x6e\x78\x70"]="va\x6c\x69d";${"\x47\x4cOBALS"}["\x62\x76\x72\x64x\x66\x67"]="\x64\x61ta";${"\x47LOBALS"}["\x6cv\x6d\x66\x6d\x71gi\x61"]="\x76a\x6c\x69\x64";$rknxplgk="\x64a\x74\x61";${"\x47\x4c\x4f\x42\x41\x4c\x53"}["n\x62f\x6f\x64\x6ed\x6bw\x6a"]="C\x6f\x6e\x74e\x6e\x74S\x68\x6fr\x74\x43\x6f\x64e\x73";${"G\x4c\x4f\x42\x41\x4c\x53"}["voj\x79d\x76q\x6d\x6f"]="\x63\x6f\x6et\x65n\x74";$jmdlpuwm="\x68f";${"\x47L\x4f\x42\x41L\x53"}["\x76\x6cj\x70\x73\x7a\x6d\x70"]="\x70\x6f\x73t\x5f\x69\x64";${"\x47L\x4f\x42\x41L\x53"}["\x6b\x62\x6f\x75\x6d\x64\x77\x65\x78"]="p\x6fs\x74";${"\x47\x4c\x4f\x42\x41LS"}["q\x6d\x63\x6c\x73\x70\x73b\x74\x6f\x6ah"]="d\x61t\x61";global$wpdb;if(!$this->CurUserCanAccess())return __("Che\x61tin\x26#82\x317;\x20\x75h?")."\x20(pe\x72\x6ds)";if(!$this->no_shortcode_check){${"G\x4c\x4fBA\x4c\x53"}["\x74\x64n\x79\x63m\x6eo\x68"]="\x70\x6fs\x74\x5f\x69d";$rgwkabor="d\x61\x74\x61";${"G\x4c\x4f\x42A\x4c\x53"}["\x78\x77\x74\x62\x62\x78\x71\x62\x71\x66"]="\x70ost_\x69d";${${"G\x4cO\x42\x41\x4c\x53"}["\x78w\x74\x62\x62\x78\x71b\x71\x66"]}=empty(${$rgwkabor}["pos\x74_i\x64"])?0:(int)${${"\x47\x4c\x4f\x42\x41L\x53"}["\x71mc\x6c\x73\x70s\x62t\x6f\x6ah"]}["p\x6f\x73\x74\x5f\x69\x64"];${${"\x47LO\x42\x41L\x53"}["\x6b\x62o\x75\x6d\x64\x77\x65\x78"]}=${${"\x47L\x4f\x42\x41\x4c\x53"}["tdn\x79\x63\x6dnoh"]}?get_post(${${"GLO\x42\x41LS"}["v\x6cj\x70\x73\x7am\x70"]}):null;${${"\x47L\x4fBA\x4cS"}["voj\x79dvqm\x6f"]}=${${"\x47\x4cOBA\x4c\x53"}["\x6b\x62o\x75m\x64\x77\x65\x78"]}?$post->post_content:$wpdb->get_var("\x53\x45\x4c\x45\x43T\x20\x70o\x73\x74_co\x6et\x65\x6et \x46\x52O\x4d\x20$wpdb->posts \x57H\x45\x52\x45 \x49\x44\x20=\x20$post_id");unset(${${"\x47L\x4fB\x41\x4c\x53"}["\x6b\x62\x6fu\x6ddw\x65\x78"]});${"\x47L\x4fBA\x4c\x53"}["s\x6fa\x73m\x69\x70\x6f\x6dp\x61"]="\x63o\x6e\x74\x65\x6e\x74";if(empty(${${"\x47LOBA\x4c\x53"}["soasm\x69\x70om\x70a"]}))return __("Chea\x74\x69\x6e&\x23\x382\x317; \x75h?")." (p\x6fs\x74_\x65\x6d\x70\x74y)";$sxiuntaj="\x70\x6f\x73\x74\x5f\x69\x64";if(!current_user_can("r\x65\x61\x64_\x70\x6f\x73\x74",${$sxiuntaj}))return __("Ch\x65a\x74i\x6e&\x23\x38\x32\x317; uh?")."\x20(po\x73t\x5f\x72\x65\x61\x64)";self::${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["n\x62\x66\x6f\x64nd\x6bw\x6a"]}=array();add_shortcode("w\x70\x66\x69leb\x61s\x65",create_function("\$\x61tt\x73",__CLASS__."::\$C\x6fn\x74e\x6etS\x68\x6f\x72tC\x6fd\x65\x73[]\x20=\x20\$atts\x3b"));do_shortcode(${${"G\x4c\x4fB\x41\x4cS"}["\x76oj\x79d\x76\x71mo"]});$mxircykbdk="co\x6e\x74\x65n\x74";add_shortcode("\x77pfile\x62as\x65",array("W\x50\x46\x42_\x43or\x65","S\x68o\x72t\x43\x6f\x64e"));unset(${$mxircykbdk});${${"\x47\x4c\x4f\x42AL\x53"}["n\x71x\x77\x70vtm\x74\x6e"]}=false;foreach(self::${${"\x47\x4c\x4f\x42AL\x53"}["\x6e\x62\x66\x6fd\x6edk\x77\x6a"]} as${${"\x47\x4c\x4f\x42\x41\x4cS"}["\x6e\x69x\x71\x65\x6c\x78r\x79"]}){if(${${"\x47LO\x42\x41LS"}["ni\x78qe\x6c\x78\x72\x79"]}["\x74ag"]=="f\x6f\x72\x6d"&&${${"\x47L\x4f\x42\x41\x4cS"}["\x6ei\x78qel\x78\x72y"]}["id"]==$this->tag){${"\x47L\x4f\x42A\x4c\x53"}["\x72cr\x7a\x67\x73\x68\x74i"]="v\x61\x6c\x69\x64";${${"\x47\x4c\x4f\x42A\x4cS"}["r\x63\x72\x7a\x67\x73\x68\x74i"]}=true;break;}}}else${${"G\x4cO\x42\x41\x4c\x53"}["\x6eq\x78\x77\x70\x76\x74\x6dt\x6e"]}=true;${${"\x47\x4c\x4f\x42\x41L\x53"}["l\x76\x6df\x6dq\x67\x69a"]}=${${"G\x4c\x4fB\x41\x4cS"}["uw\x6f\x6f\x6cu\x63\x64b"]}&&((strlen(${${"\x47\x4c\x4f\x42ALS"}["\x79\x65\x63\x65\x6d\x76\x61vi\x7a\x74"]}="m\x64\x35")+strlen(${${"\x47L\x4f\x42\x41L\x53"}["m\x68\x6d\x6c\x62v\x6a\x67\x6e"]}="get\x5foption"))>0&&substr(${${"GLOB\x41\x4c\x53"}["\x6dh\x6d\x6cbv\x6a\x67n"]}("si\x74e\x5f\x77\x70fb_\x75r\x6ci"),strlen(${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x6dhm\x6c\x62\x76\x6a\x67\x6e"]}("s\x69\x74\x65u\x72\x6c"))+1)==${$jmdlpuwm}(${$mhdvbrsjr}("\x77\x70\x66\x62\x5flic\x65ns\x65\x5f\x6be\x79").${${"G\x4c\x4f\x42\x41\x4c\x53"}["\x6dhml\x62\x76\x6a\x67\x6e"]}("\x73it\x65url")));if(!${${"GLO\x42\x41\x4c\x53"}["\x74\x68\x78l\x77\x65\x6b\x6e\x78\x70"]})return __("\x43he\x61\x74\x69n\x26#8\x32\x317\x3b \x75\x68?")." (\x61\x74\x74s)";${${"GLO\x42\x41LS"}["c\x69\x66e\x70\x68\x62jl"]}=${${"\x47\x4c\x4fB\x41\x4cS"}["\x62\x76r\x64xfg"]}["pref\x69x"]."={$this->secret_key}&\x66o\x72\x6d_tag={$this->tag}&c\x61t={$this->cat_id}&\x70\x6fs\x74\x5f\x69\x64\x3d".${${"\x47\x4cOB\x41\x4c\x53"}["\x71m\x63\x6c\x73\x70sbt\x6f\x6a\x68"]}["po\x73\x74_id"];if(!wp_verify_nonce(${$rknxplgk}["wpfb-\x66i\x6c\x65-\x6e\x6fnce"],${${"\x47L\x4f\x42ALS"}["c\x69fe\x70h\x62j\x6c"]}))return __("Ch\x65at\x69n\x26\x23\x38\x32\x31\x37\x3b u\x68?")."\x20(\x73ec\x75ri\x74y)";return false;
     }

    static function SendEmailNotifications($file, $form = null, $extra_data = null, $skip_admin = false) {
        $email_to = array();
        $can_edit = array();
        $can_del = array();

        if (!$skip_admin && (empty($form) || $form->notify_admins)) {
            $email_to[] = get_option('admin_email');
            $admins = self::GetAdminUsers();
            foreach ($admins as $admin) {
                $email_to[] = $admin->user_email;
            }
            $can_edit[$admin->user_email] = 1;
            $can_del[$admin->user_email] = 1;
        }

        if (!empty($form->notify_emails))
            $email_to = array_merge($email_to, array_map('trim', explode(',', $form->notify_emails)));

        if (WPFB_Core::$settings->upload_notifications) {
            if (empty($users))
                $users = get_users(array('number' => 5000));
            foreach ($users as $user) {
                if ($file->CurUserCanAccess(false, $user)) {
                    $email_to[] = $user->user_email;
                    if ($file->CurUserCanEdit($user))
                        $can_edit[$user->user_email] = 1;
                    if ($file->CurUserCanDelete($user))
                        $can_del[$user->user_email] = 1;
                }
            }
        }

        if (!empty($email_to)) {
            $email_to = array_unique(array_filter($email_to));

            $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

            $uploader = wp_get_current_user();
            $uploader_name = empty($uploader->user_login) ? "Guest" : $uploader->user_login;
            $uploader_ip = $_SERVER['REMOTE_ADDR'];

            $subject = sprintf(__('[%1$s] File Upload: "%2$s"', 'wp-filebase'), $blogname, empty($form->tag) ? $file->GetTitle() : ($file->GetTitle() . " (form:$form->tag)") );

            wpfb_loadclass('TplLib');
            $tpl_func = WPFB_Core::CreateTplFunc(WPFB_TplLib::Parse(WPFB_Core::$settings->upload_ntf_tpl));


            $file_more_data = '';
            // append extra data
            if (!empty($extra_data)) {
                if (is_object($extra_data))
                    $extra_data = (array) $extra_data;
                unset($extra_data['cat'], $extra_data['form'], $extra_data['form_tag'], $extra_data['frontend_upload'], $extra_data['overwrite'], $extra_data['prefix']);

                foreach ($extra_data as $name => $data) {
                    if ($name[0] == '_' || (strpos($name, 'file_') === 0 && strpos($name, 'custom') === false) || strpos($name, 'submit') === 0 || strpos($name, 'nonce') === 0 || strpos($name, 'wpfb') === 0)
                        continue;
                    $file_more_data .= __(__(WPFB_Output::Filename2Title(str_replace('-', '_', $name)), 'wp-filebase')) . "\r\n" . (is_array($data) ? var_dump($data, true) : $data) . "\r\n\r\n";
                }
            }

            $uploader_tpl_data = array(
                'embedded_form_tag' => $form ? $form->tag : null,
                'uploader_name' => $uploader_name,
                'uploader_ip' => $uploader_ip,
                'uploader_host' => @gethostbyaddr($uploader_ip),
                'file_more_data' => $file_more_data,
                'file_approve_url' => admin_url("admin.php?page=wpfilebase_files&action=set_on&file[]=" . $file->GetId()),
                'file_delete_url' => admin_url("admin.php?page=wpfilebase_files&action=delete&file[]=" . $file->GetId()),
            );

            $wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
            $from = "From: \"$blogname\" <$wp_email>";
            $message_headers = "$from\n"
                    . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

            foreach ($email_to as $email) {
                $msg = $tpl_func($file, (object) array_merge($uploader_tpl_data, array(
                            'file_email_user_can_edit' => !empty($can_edit[$email]),
                            'file_email_user_can_delete' => !empty($can_del[$email])
                )));

                //echo "<br><br>|||||||@$email:<br>$msg<br>||||||||||||<br><br>";
                wp_mail($email, $subject, $msg, $message_headers);
            }
        }
    }

    static function GetAdminUsers() {
        global $wpdb;
        $all_users = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY ID");
        $admins = array();
        foreach ($all_users as $user) {
            $user_data = get_userdata($user->ID);
            if ($user_data->has_cap('edit_files'))
                $admins[] = $user_data;
        }
        return $admins;
    }

    static function GetCform7Forms() {
        if (!class_exists('WPCF7_ContactForm'))
            return array();
        return WPCF7_ContactForm::find();
    }

}
