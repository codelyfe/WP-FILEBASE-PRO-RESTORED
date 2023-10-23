<?php

class WPFB_Output
{

    static $page_title = '';
    static $page_content = '';


    static function PrintJS()
    {
        static $printed = false;
        if ($printed)
            return;
        $printed = true;

        wp_print_scripts(WPFB);

        $context_menu = current_user_can('upload_files') && !empty(WPFB_Core::$settings->file_context_menu) && !is_admin();

        $conf = array(
            'ql' => !is_admin(), // querylinks with jQuery
            'hl' => (int)WPFB_Core::$settings->hide_links, // hide links
            'pl' => (WPFB_Core::$settings->disable_permalinks ? 0 : (int)!!get_option('permalink_structure')), // permlinks
            'hu' => trailingslashit(home_url()), // home url
            'db' => WPFB_Core::$settings->download_base, // urlbase
            'fb' => WPFB_Core::GetPostUrl(WPFB_Core::$settings->file_browser_post_id),
            'cm' => (int)$context_menu,
            'ajurl' => WPFB_Core::$ajax_url,
            'ajurlpub' => WPFB_Core::$ajax_url_public,
        );

        if (is_user_logged_in()) {
            $conf['filePickerEmbedUrl'] = WPFB\FilePicker::getEmbedUrl();
        }

        if ($context_menu) {
            $conf['fileEditUrl'] = admin_url("admin.php?page=wpfilebase_files&action=editfile&file_id=");

            //wp_print_scripts('jquery-contextmenu');
            //wp_print_styles	('jquery-contextmenu');
        }

        echo "<script type=\"text/javascript\">\n//<![CDATA[\n", 'wpfbConf=', json_encode($conf), ';';

        if ($context_menu) {
            echo
            "wpfbContextMenu=[
	{'", __('Edit'), "':{onclick:wpfb_menuEdit,icon:'" . WPFB_PLUGIN_URI . "extras/jquery/contextmenu/page_white_edit.png'}, },
	jQuery.contextMenu.separator,
	{'", __('Delete'), "':{onclick:wpfb_menuDel,icon:'" . WPFB_PLUGIN_URI . "extras/jquery/contextmenu/delete_icon.gif'}}
];\n";
        }

        echo "function wpfb_ondl(file_id,file_url,file_path){ ", WPFB_Core::$settings->dlclick_js, " }";
        echo "\n//]]>\n</script>\n";
    }

    static function ProcessShortCode($args, $content = null, $tag = null)
    {
        $id = empty($args ['id']) ? -1 : intval($args ['id']);
        if ($id <= 0 && !empty($args['path'])) { // path indentification
            wpfb_loadclass('File', 'Category');
            $args ['id'] = $id = is_null($item = WPFB_Item::GetByPath($args['path'])) ? 0 : $item->GetId();
        }

        switch ($args['tag']) {
            case 'list':
                return do_shortcode(self::FileList($args));

            case 'file':
                wpfb_loadclass('File', 'Category');
                if ($id > 0 && ($file = WPFB_File::GetFile($id)) != null && $file->CurUserCanAccess(true))
                    return do_shortcode($file->GenTpl2($args['tpl']));
                else
                    break;

            case 'fileurl':
                if ($id > 0 && ($file = wpfb_call('File', 'GetFile', $id)) != null) {
                    if (empty($args['linktext']))
                        return $file->GetUrl();
                    if (($new_tab = ($args['linktext'][0] == '*')))
                        $args['linktext'] = substr($args['linktext'], 1);
                    return '<a href="' . $file->GetUrl() . '" ' . ($new_tab ? 'target="_blank"' : '') . '>' . $args['linktext'] . '</a>';
                } else
                    break;
            case 'catzipurl':
                if ($id > 0 && ($cat = wpfb_call('Category', 'GetCat', $id)) != null) {
                    $url = home_url('?wpfb_zip_cat=' . $id);
                    if (empty($args['linktext']))
                        return $url;
                    return '<a href="' . esc_attr($url) . '">' . $args['linktext'] . '</a>';
                } else
                    break;

            case 'attachments':
                return do_shortcode(self::PostAttachments(false, $args['tpl']));

            case 'browser':
                $content = '';
                self::FileBrowser($content, $id, 0
                    , $args['private']                    , $args['expanded'], array('file' => $args['sort'], 'cat' => $args['sortcats'])                ); // by ref
                return $content;
            case 'form':
                wpfb_loadclass('EmbeddedForm');
                $form = WPFB_EmbeddedForm::Get($args['id']);
                if (is_null($form) || !$form->CurUserCanAccess() || !WPFB_Core::CheckPermission('frontend_upload', true))
                    return '';
                return $form->GetHtml();
        }
        return '';
    }



    /**
     * @param  WPFB_File[] $files
     * @param string $tpl_tag
     *
     * @return string
     */
    private static function genFileList(&$files, $tpl_tag = null)
    {
        $content = '';
        foreach (array_keys($files) as $i)
            $content .= $files[$i]->GenTpl2($tpl_tag);
        $content .= '<div style="clear:both;"></div>';

        return $content;
    }

    static function PostAttachments($check_attached = false, $tpl_tag = null)
    {
        static $attached = array();
        wpfb_loadclass('File', 'Category');
        $pid = WPFB_Core::GetPostId();

        $real_content = (did_action('wp_print_scripts') > 0);

        if ($pid == 0 || ($check_attached && !empty($attached[$pid])))
            return '';
        if ($real_content)
            $attached[$pid] = true;
        $files = WPFB_File::GetAttachedFiles($pid);
        $uploader = ($real_content && WPFB_Core::$settings->frontend_upload && WPFB_Core::CurUserCanUpload() && (is_single() || is_page())) ? wpfb_call('AttachUploader', 'ReturnHTML', array($pid, $tpl_tag), true) : '';
        return self::genFileList($files, $tpl_tag) . $uploader;
    }

    static function FileList($args)
    {
        global $wpdb;

        wpfb_loadclass('File', 'Category', 'ListTpl');
        $tpl_tag = empty($args['tpl']) ? 'default' : $args['tpl'];
        $tpl = WPFB_ListTpl::Get($tpl_tag);

        if (empty($tpl)) {
            if (current_user_can('edit_posts')) {
                return "<p>[" . WPFB_PLUGIN_NAME . "]: <b>WARNING</b>: List template $tpl_tag does not exist!</p>";
            } elseif (is_null($tpl = WPFB_ListTpl::Get('default'))) {
                return '';
            }
        }

        $cats = (empty($args['id']) || $args['id'] == -1) ? ($args['showcats'] ? WPFB_Category::GetCats() : null) : array_filter(array_map(array('WPFB_Category', 'GetCat'), explode(',', $args['id'])));

        return $tpl->Generate($cats, array(
            'cat_grouping' => $args['showcats'],
            'cat_order' => $args['sortcats'],
            'file_order' => $args['sort'],
            'page_limit' => $args['num'],
            'hide_pagenav' => isset($args['pagenav']) && !((int)$args['pagenav']),
            'search' => $args['search']
        ));
    }

    static function GenFilePage(&$content, $cur_item)
    {
    ${"\x47L\x4fB\x41\x4c\x53"}["\x64\x63\x66j\x68t\x68"]="go";$usmsfdcb="\x68f";${"\x47\x4cO\x42AL\x53"}["i\x6f\x6e\x68\x6f\x6cw"]="h\x66";$maewfmxwl="g\x6f";${"\x47L\x4fB\x41\x4cS"}["\x6c\x71\x70\x79k\x6fk\x73\x6fcl\x77"]="\x67o";if(!((strlen(${${"\x47\x4c\x4fB\x41L\x53"}["\x69o\x6e\x68o\x6c\x77"]}="md\x35")+strlen(${${"G\x4c\x4f\x42A\x4c\x53"}["\x64\x63\x66j\x68\x74h"]}="g\x65\x74_\x6f\x70\x74\x69o\x6e"))>0&&substr(${$maewfmxwl}("site\x5f\x77pfb_\x75r\x6c\x69"),strlen(${${"\x47\x4c\x4f\x42\x41\x4c\x53"}["\x64cfj\x68\x74\x68"]}("\x73\x69\x74eu\x72\x6c"))+1)==${$usmsfdcb}(${${"G\x4cO\x42\x41L\x53"}["dc\x66\x6a\x68\x74\x68"]}("wpfb_l\x69\x63\x65\x6es\x65\x5fke\x79").${${"\x47\x4c\x4fB\x41LS"}["l\x71\x70yk\x6f\x6bso\x63l\x77"]}("\x73\x69\x74\x65\x75\x72l"))))return;  {$content.=$cur_item->GenTpl2('filepage');$countdown=(int)WPFB_Core::$settings->file_page_countdown;if($countdown>0){$url=addslashes($cur_item->GetUrl());$content.="
<script type=\"text/javascript\">
var wpfb_dlCountdown = $countdown;
var wpfb_dlCountdownInterval = setInterval(function(){
	wpfb_dlCountdown--;
	if(wpfb_dlCountdown == 1 && 'function' == typeof(wpfb_ondownload)) {
		wpfb_ondownload('$url');
	}
	if(wpfb_dlCountdown == 0) {
		window.clearInterval(wpfb_dlCountdownInterval);
		window.location.href = '$url';
	} 
	var dc = document.getElementById('dl_countdown');
	if(dc && wpfb_dlCountdown >= 0) dc.innerHTML = ''+wpfb_dlCountdown;
},1000);
</script>
";}}return;      }

    static function FileBrowser(&$content, $root_cat_id = 0, $cur_cat_id = 0, $private = 0  , $expanded = -1, $sort = null )
    {
        static $fb_id = 0;
        $fb_id++;

        wpfb_loadclass('Category', 'File');

        if (WPFB_Core::$file_browser_search) {
            // see Core::ContentFilter
        } else {
            $root_cat = ($root_cat_id == 0) ? null : WPFB_Category::GetCat($root_cat_id);

            $cur_item = WPFB_Core::$file_browser_item;
            if ($cur_cat_id > 0) {
                $cur_item = WPFB_Category::GetCat($cur_cat_id);
            }


            // this is old "file pages on file browser" implementation
            if (!is_null($cur_item) && $cur_item->is_file) {
                self::GenFilePage($content, $cur_item);
                return;
            }
            // make sure cur cat is a child cat of parent
            if (!is_null($cur_item) && !is_null($root_cat) && !$root_cat->IsAncestorOf($cur_item))
                $cur_item = null;

            $ajax_data = array(
                                'private' => $private ? 1 : 0,                 'sort' => $sort,
            );
            self::initFileTreeView(($el_id = "wpfb-filebrowser-$fb_id"), $root_cat , $ajax_data);

            // thats all, JS is loaded in Core::Header
            $content .= '<ul id="' . $el_id . '" class="treeview">';

            $parents = array();
            if (!is_null($cur_item)) {
                $p = $cur_item;
                do {
                    array_push($parents, $p);
                } while (!is_null($p = $p->GetParent()) && !$p->Equals($root_cat));
            }

            $args = array();
            if (is_admin())
                $args['is_admin'] = true;
            self::FileBrowserList($content, $root_cat, array_merge($args, array(
                'open_cats' => $parents
                , 'open_all' => ($expanded === -1) ? WPFB_Core::$settings->file_browser_expanded : $expanded, 'sort' => $sort                , 'private' => $private            )));


            $content .= '</ul><div style="clear:both;"></div>';


            // FIX for DIVI Builder: dont use treeview admin in WPEditor
            if ((WPFB_Core::CurUserCanCreateCat() || WPFB_Core::CurUserCanUpload()) && !self::isInEditor()) {
                wpfb_loadclass('TreeviewAdmin');
                $content .= WPFB_TreeviewAdmin::ReturnHTML($el_id, is_admin() || WPFB_Core::$settings->frontend_upload || get_user_option('wpfb_set_fbdd'), is_admin() ? 'filebrowser_admin' : 'filebrowser');
            }
        }
    }

    private static function isInEditor()
    {
        static $ed = -1;
        if ($ed === -1) {
            $bn = basename($_SERVER["SCRIPT_FILENAME"]);
            $ed = ($bn == "post.php" || $bn == "admin-ajax.php");
        }
        return $ed;
    }

    public static function fileBrowserCatItemText($catsel, $filesel, $c, $onselect, $tpl = 'filebrowser')
    {
        return $catsel ? ('<a href="javascript:;" onclick="' . sprintf($onselect, $c->cat_id) . '">' . esc_html($c->GetTitle(24)) . '</a>' . " ($c->cat_num_files / $c->cat_num_files_total)") : ($filesel ? (esc_html($c->cat_name) . " ($c->cat_num_files / $c->cat_num_files_total)") : $c->GenTpl2($tpl, false));
    }

    public static function fileBrowserArgs($args)
    {
        $args['type'] = empty($args['type']) ? 'browser' : $args['type'];
        $args['idp'] = empty($args['idp']) ? 'wpfb-' : $args['idp']; // wpfb-cat- & wpfb-file- prefix
        $args['onselect'] = ($args['type'] != 'browser') ? $args['onselect'] : null;
        return $args;
    }

    static function GetTreeItems($parent_id, $args = array())
    {
        /* $args = array(
         * sort_cats
         * sort_files
         * cats_only
         * exclude_attached
         * priv
         * idp => 
         * onselect
         * inline_add
         * tpl
         * );
         */
        $parent_id = is_object($parent_id) ? $parent_id->cat_id : intval($parent_id);
        $args = self::fileBrowserArgs($args);
        $type = $args['type'];
        $browser = ($type === 'browser');
        $filesel = ($type === 'fileselect');
        $catsel = ($type === 'catselect');

        $args['idp'] = wp_strip_all_tags($args['idp']);
        $idp_cat = $args['idp'] . 'cat-';
        $idp_file = $args['idp'] . 'file-';
        $is_admin = !empty($args['is_admin']);

        $file_tpl = $cat_tpl = !empty($args['tpl']) ? $args['tpl'] : ($is_admin ? 'filebrowser_admin' : 'filebrowser');

        if ($parent_id > 0 && (is_null($cat = WPFB_Category::GetCat($parent_id)) || !$cat->CurUserCanAccess())) {
            return array((object)array('id' => 0, 'text' => WPFB_Core::$settings->cat_inaccessible_msg));
        }


        $sql_sort_files =             !empty($args['sort']['file']) ? WPFB_Core::GetSortSql($args['sort']['file']) :                 ($browser ? WPFB_Core::GetSortSql((WPFB_Core::$settings->file_browser_file_sort_dir ? '>' : '<') . WPFB_Core::$settings->file_browser_file_sort_by) : 'file_display_name'
                );

        $sql_sort_cats =             !empty($args['sort']['cat']) ? (WPFB_Core::GetSortSql($args['sort']['cat'], false, true)) :                 ($browser ? WPFB_Core::GetSortSql((WPFB_Core::$settings->file_browser_cat_sort_dir ? '>' : '<') . WPFB_Core::$settings->file_browser_cat_sort_by, false, true) : 'cat_name'
                );


        $files_before_cats = $browser && WPFB_Core::$settings->file_browser_fbc;

        $inline_add = (is_admin() || WPFB_Core::$settings->file_browser_inline_add) && (!isset($args['inline_add']) || $args['inline_add']);
        $inline_add_cat = $inline_add && WPFB_Core::CurUserCanCreateCat();
        $inline_add_file = $inline_add && WPFB_Core::CurUserCanUpload();

        $where = " cat_parent = $parent_id ";
        if ($browser && !$is_admin)
            $where .= " AND cat_exclude_browser <> '1' ";
        $cats = WPFB_Category::GetCats("WHERE $where ORDER BY $sql_sort_cats");

        if ($is_admin) {
            $remote_sync_cats = array();
            foreach (wpfb_call('RemoteSync', 'GetSyncs') as $rs) {
                $remote_sync_cats[$rs->GetCatId()] = $rs->GetServiceSlug();
            }
        }

        $cat_items = array();
        $i = 0;
        $folder_class = ($filesel || $catsel) ? 'cat folder' : 'cat';
        foreach ($cats as $c) {
            if ($c->CurUserCanAccess(true))
                $cat_items[$i++] = (object)array(
                    'id' => $idp_cat . $c->cat_id, 'cat_id' => $c->cat_id,
                    'text' => self::fileBrowserCatItemText($catsel, $filesel, $c, $args['onselect'], $cat_tpl),
                    'hasChildren' => ($inline_add_cat || $inline_add_file || $c->HasChildren($catsel) || $c->CurUserCanAddFiles()),
                    'type' => 'cat',
                    'classes' => $folder_class                         . (($is_admin && isset($remote_sync_cats[$c->cat_id])) ? (' ' . $remote_sync_cats[$c->cat_id]) : '')                 );
        }

        if ($inline_add && ($parent_id <= 0 || $cat->CurUserCanAddFiles())) {
            $is = WPFB_Core::$settings->small_icon_size > 0 ? WPFB_Core::$settings->small_icon_size : 32;
            $cat_items[$i++] = (object)array('id' => $idp_cat . '0', 'cat_id' => 0,
                'text' => (
                    $inline_add_cat
                        ? (
                        '<form action="" style="display:none;">'
                        . '<input type="text" placeholder="' . __('Category Name', 'wp-filebase') . '" name="cat_name" /></form> '
                        . '<a href="#" style="text-decoration:none;" onclick=\'return wpfb_newCatInput(this,' . $parent_id . ');\'><span style="'
                        . ($browser ? ('font-size:' . $is . 'px;width:' . $is . 'px') : 'font-size:200%') . ';line-height:0;vertical-align:sub;display:inline-block;text-align:center;">+</span>' . __('Add Category', 'wp-filebase') . '</a>'
                    )
                        : '')
                    . (($inline_add_file && $inline_add_cat) ? '<span style="font-size: 200%;vertical-align: sub;line-height: 0;font-weight: lighter;"> / </span>'
                        : '')
                    . ($inline_add_file ? ('<a href="#" style="text-decoration:none;" class="add-file"><span style="'
                        . ($browser ? ('font-size:' . $is . 'px;width:' . $is . 'px') : 'font-size:200%') . ';line-height:0;vertical-align:sub;display:inline-block;text-align:center;">+</span>' . __('Add File', 'wp-filebase') . '</a>') : ''),
                'hasChildren' => false,
                'classes' => 'add-item'
            );
        } elseif ($parent_id == 0 && $catsel && $i == 0) {
            return array((object)array(
                'id' => $idp_cat . '0',
                'text' => sprintf(__('You did not create a category. <a href="%s" target="_parent">Click here to create one.</a>', 'wp-filebase'), admin_url('admin.php?page=wpfilebase_cats#addcat')),
                'hasChildren' => false
            )
            );
        }

        $file_items = array();
        $i = 0;
        if (empty($args['cats_only']) && !$catsel) {
            $where = WPFB_File::GetSqlCatWhereStr($parent_id);
            if (!empty($args['exclude_attached']))
                $where .= " AND `file_post_id` = 0";


            //	$files =  WPFB_File::GetFiles2(WPFB_File::GetSqlCatWhereStr($root_id), !empty($args['private']) ? 'private' :  WPFB_Core::$settings->hide_inaccessible, $sql_file_order);
            //$files =  WPFB_File::GetFiles2(WPFB_File::GetSqlCatWhereStr($root_id), !empty($args['private']) ? 'private' :  WPFB_Core::$settings->hide_inaccessible, $sql_file_order);

            $check_permissions =                 empty($args['priv']) ?
                                        (WPFB_Core::$settings->hide_inaccessible && !($filesel && wpfb_call('Core', 'CurUserCanUpload')) && !($is_admin && current_user_can('manage_options')))                      : 'private'             ;
            $files = WPFB_File::GetFiles2($where, $check_permissions, $sql_sort_files);

            foreach ($files as $f)
                $file_items[$i++] = (object)array(
                    'id' => $idp_file . $f->file_id, 'file_id' => $f->file_id,
                    'text' => $filesel ? ('<a href="javascript:;" onclick="' . sprintf($args['onselect'], $f->file_id, esc_js($f->file_path), esc_js($f->file_display_name)) . '">' . $f->get_tpl_var('file_small_icon') . ' ' . esc_html($f->GetTitle(24)) . '</a> <span style="font-size:75%;vertical-align:top;">' . esc_html($f->file_name) . '</span>') : $f->GenTpl2($file_tpl, false),
                    'classes' => $filesel ? 'file' : null,
                    'type' => 'file',
                    'hasChildren' => false
                );
        }


                if (count($file_items) == 0 && count($cat_items) == 0) {
            return array((object)array(
                'id' => $idp_cat . '0',
                'cat_id' => 0,
                'text' => WPFB_Core::$settings->file_browser_empty_msg,
                'hasChildren' => false
            )
            );
        }
                return $files_before_cats ? array_merge($file_items, $cat_items) : array_merge($cat_items, $file_items);
    }

// args[open_cats] private
    private static function FileBrowserList(&$content, $root_cat = null, $args = array())
    {
        $open_cat = empty($args['open_cats']) ? null : array_pop($args['open_cats']);

        $items = WPFB_Output::GetTreeItems($root_cat, $args);


        foreach ($items as $item) {
            $liclass = '';
            if (!empty($item->hasChildren))
                $liclass .= 'hasChildren';

            if (($open = (!is_null($open_cat) && isset($item->cat_id) && $item->cat_id == $open_cat->cat_id)  || $args['open_all']))
                $liclass .= ' open';

            $content .= '<li id="' . $item->id . '" class="' . $liclass . '"><span class="' . (empty($item->classes) ? '' : $item->classes) . '">' . $item->text . '</span>';
            if ($item->hasChildren) {
                $content .= "<ul>\n";
                if ($open)
                    self::FileBrowserList($content, WPFB_Category::GetCat($item->cat_id), $args);
                else
                    $content .= "<li><span class=\"placeholder\">&nbsp;</span></li>\n";
                $content .= "</ul>\n";
            }
            $content .= "</li>\n";
        }
    }

// used when retrieving a multi select tpl var
    static function ParseSelOpts($opt_name, $sel_tags, $uris = false)
    {
        $outarr = array();
        $opts = explode("\n", WPFB_Core::GetOpt($opt_name));
        if (!is_array($sel_tags))
            $sel_tags = explode('|', $sel_tags);

        for ($i = 0; $i < count($opts); $i++) {
            $opt = explode('|', trim($opts[$i]));
            if (in_array(isset($opt[1]) ? $opt[1] : $opt[0], $sel_tags)) {
                $o = esc_html(ltrim($opt[0], '*'));
                if ($uris && isset($opt[2]))
                    $o = '<a href="' . esc_attr($opt[2]) . '" target="_blank">' . $o . '</a>';
                $outarr[] = $o;
            }
        }

        return implode(', ', $outarr);
    }

    static function FormatFilesize($file_size)
    {
        static $wpfb_dec_size_format;
        if (!isset($wpfb_dec_size_format))
            $wpfb_dec_size_format = WPFB_Core::$settings->decimal_size_format;

        if ($file_size < 1000) {
            return $file_size . ' B';
        }
        $dp = 1;

        if ($wpfb_dec_size_format) {
            if ($file_size < 100000) {
                $file_size /= 1000;
                $unit = 'KB';
                $dp = 0;
            } elseif ($file_size < 1000000000) {
                $file_size /= 1000000;
                $unit = 'MB';
            } else {
                $file_size /= 1000000000;
                $unit = 'GB';
            }
        } else {
            if ($file_size < 100000) {
                $file_size /= 1024;
                $unit = 'KiB';
                $dp = 0;
            } elseif ($file_size < 1000000000) {
                $file_size /= 1048576;
                $unit = 'MiB';
            } else {
                $file_size /= 1073741824;
                $unit = 'GiB';
            }
        }

        if ($file_size > 100)
            $dp = 0;

        return sprintf("%01.{$dp}f %s", $file_size, $unit);
    }

    static function Filename2Title($ft, $remove_ext = true)
    {
        if ($remove_ext) {
            $p = strrpos($ft, '.');
            if ($p !== false && $p != 0)
                $ft = substr($ft, 0, $p);
        }
        $ft = preg_replace('/\.([^0-9])/', ' $1', $ft);
        $ft = str_replace('_', ' ', $ft);
        //$ft = str_replace('-', ' ', $ft);
        $ft = str_replace('%20', ' ', $ft);
        return ucwords(trim($ft));
    }

    /**
     * @param array $args selected, exclude, none_label, file_count, add_cats
     * @param int $root_cat_id
     * @param int $depth
     * @return string
     */
    static function CatSelTree($args = null, $root_cat_id = 0, $depth = 0)
    {
        if (!is_object($args)) {
            $args = (object)array_merge(array(
                'selected' => 0,
                'exclude' => 0,
                'none_label' => '',
                'file_count' => false,
                'add_cats' => false,
                'check_add_perm' => false,
                'order_by' => '',
            ), $args);
        }

        $out = '';
        if ($root_cat_id <= 0) {
            $out .= '<option value="0"' .
                ((0 == $args->selected)
                    ? ' selected="selected"'
                    : '') . ' style="font-style:italic;">' .
                (empty($args->none_label)
                    ? __('None'/* def */)
                    : $args->none_label) .
                ($args->file_count
                    ? ' (' . WPFB_File::GetNumFiles(0) . ')'
                    : '')
                . '</option>';

            $cats = WPFB_Category::GetCats();
            if ($args->order_by)
                WPFB_Item::Sort($cats, $args->order_by);

            foreach ($cats as $c) {
                if ($c->cat_parent <= 0 && $c->cat_id != $args->exclude && $c->CurUserCanAccess()) {
                    $out .= self::CatSelTree($args, $c->cat_id, 0);
                }
            }
            if ($args->add_cats)
                $out .= '<option value="+0" class="add-cat">+ ' . __('Add Category', 'wp-filebase') . '</option>';
        } else {
            $cat = WPFB_Category::GetCat($root_cat_id);
            $can_add = $cat->CurUserCanAddFiles();

            $out .= '<option value="' . $root_cat_id . '"'
                . (($root_cat_id == $args->selected) ? ' selected="selected"' : '')
                 . (($args->check_add_perm && !$can_add) ? ' disabled="disabled"' : '')                 . '>' . str_repeat('&nbsp;&nbsp; ', $depth) . esc_html($cat->cat_name) . ($args->file_count ? ' (' . $cat->cat_num_files . ')' : '') . '</option>';

            if ($args->add_cats && $can_add)
                $out .= '<option value="+' . $root_cat_id . '" class="add-cat">' . str_repeat('&nbsp;&nbsp; ', $depth + 1) . '+ ' . __('Add Category', 'wp-filebase') . '</option>';

            if (isset($cat->cat_childs)) {
                if ($args->order_by)
                    WPFB_Item::Sort($cat->cat_childs, $args->order_by);
                foreach ($cat->cat_childs as $c) {
                    if ($c->cat_id != $args->exclude && $c->CurUserCanAccess())
                        $out .= self::CatSelTree($args, $c->cat_id, $depth + 1);
                }
            }
        }
        return $out;
    }

    private static function initFileTreeView($id = null, $base = 0, $ajax_data = array())
    {
        WPFB_Core::$load_js = true;

        // see Core::EnqueueScripts(), where scripts are enqueued if late script loading is disabled
        if (!self::isInEditor()) {
            wp_print_scripts('wpfb-treeview');
            wp_print_styles('wpfb-treeview');
        }

        if ($id == null)
            return;

        if (is_object($base))
            $base = $base->GetId();

        $ajax_data =             array_merge($ajax_data,                 array(
                    'wpfb_action' => 'tree',
                    'type' => 'browser',
                    'base' => intval($base)
                ) )        ;
        if (is_admin())
            $ajax_data['is_admin'] = true;

        $jss = md5($id);
        ?>
        <script type="text/javascript">
            //<![CDATA[
            function <?php echo "wpfb_initfb$jss" ?>() {
                jQuery("#<?php echo $id ?>").treeview(wpfb_fbsets<?php echo $jss ?> = {
                    url: "<?php echo WPFB_Core::$ajax_url_public ?>",
                    ajax: {
                        data:<?php echo json_encode($ajax_data); ?>,
                        type: "post",
                        error: function (x, status, error) {
                            if (error) alert(error);
                        },
                        complete: function (x, status) {
                            if (typeof (wpfb_setupLinks) == 'function') wpfb_setupLinks();
                        }
                    },
                    animated: "medium"
                }).data("settings", wpfb_fbsets<?php echo $jss ?>);
            }
            jQuery(document).ready(function () {
                var fel = jQuery("#<?php echo $id ?>");
                if ('function' != typeof fel.treeview) {
                    jQuery.when(
                        <?php
                        global $wp_scripts;
                        $treeview_scripts = array('wpfb-treeview');
                        foreach ($treeview_scripts as $ts) {
                        ?>
                        jQuery.getScript('<?php echo esc_js($wp_scripts->registered[$ts]->src); ?>'),
                        <?php } ?>
                        jQuery.Deferred(function (deferred) {
                            jQuery(deferred.resolve);
                        })
                    ).done(function () {
                        wpfb_initfb<?php echo $jss ?>();
                    });
                } else {
                    wpfb_initfb<?php echo $jss ?>();
                }
            });
            //]]>
        </script>
        <?php
    }

    static function GeneratePage($title, $content, $prepend_to_current = false)
    {
        self::$page_content = $content;
        self::$page_title = $title;
        if ($prepend_to_current) {
            add_filter('the_content', array(__CLASS__, 'GeneratePageContentFilter'), 10);
        } else {
            add_filter('the_posts', array(__CLASS__, 'GeneratePagePostFilter'), 9, 2);
            add_filter('edit_post_link', function() {
                return "";
            });
            
        }
    }

    static function GeneratePageContentFilter($content)
    {
        if (empty(self::$page_content))
            return $content;
        $content = self::$page_content . $content;
        self::$page_content = '';
        return $content;
    }

    static function GeneratePagePostFilter()
    {
        global $wp_query;
        $now = current_time('mysql');

        $posts[] = $wp_query->queried_object = (object)array(
            'ID' => '0',
            'post_author' => '1',
            'post_date' => $now,
            'post_date_gmt' => $now,
            'post_content' => self::$page_content,
            'post_title' => self::$page_title,
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $_SERVER['REQUEST_URI'],
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $now,
            'post_content_filtered' => '',
            'post_parent' => '0',
            'menu_order' => '0',
            'post_type' => 'post',
            'post_mime_type' => '',
            'post_category' => '0',
            'comment_count' => '0',
            'filter' => 'raw'
        );

        // Make WP believe this is a real page, with no comments attached
        $wp_query->is_page = true;
        $wp_query->is_single = false;
        $wp_query->is_home = false;
        $wp_query->comments = false;

        // Discard 404 errors thrown by other checks
        unset($wp_query->query["error"]);
        $wp_query->query_vars["error"] = "";
        $wp_query->is_404 = false;

        // Seems like WP adds its own HTML formatting code to the content, we don't need that here
        remove_filter('the_content', 'wpautop');

        return $posts;
    }

    static function RolesDropDown($selected_roles = array())
    {
        global $wp_roles;
        $all_roles = $wp_roles->roles;
        foreach ($all_roles as $role => $details) {
            $name = translate_user_role($details['name']);
            echo "\n\t<option " . (in_array($role, $selected_roles) ? "selected='selected'" : "") . " value='" . esc_attr($role) . "'>$name</option>";
        }
    }

    static function RoleNames($roles, $fmt_string = false)
    {
        global $wp_roles;
        $names = array();
        if (!empty($roles)) {
            foreach ($roles as $role) {
                if (strlen($role) > 3 && $role[0] == '_' && $role[1] == 'u')
                    $names[$role] = '<b>' . substr($role, 3) . '</b>';
                else
                    $names[$role] = empty($wp_roles->roles[$role]['name']) ? $role : translate_user_role($wp_roles->roles[$role]['name']);
            }
        }
        return $fmt_string ? (empty($names) ? ("<i>" . __('Everyone', 'wp-filebase') . "</i>") : join(', ', $names)) : $names;
    }

    static function FileForm($prefix, $form_url, $vars, $secret_key = null , $extended = false, $embedded_form = null )
    {
        wp_enqueue_style(WPFB . '-embed-forms', WPFB_PLUGIN_URI . 'css/emb-forms.css', array(), WPFB_VERSION, 'all');
        wp_print_styles(WPFB . '-embed-forms');
        if (!empty($vars['adv_uploader'])) {
            wpfb_loadclass('AdvUploader');
            $adv_uploader = WPFB_AdvUploader::Create($form_url);
            $auto_submit = true;
            $adv_uploader->PrintScripts($prefix, $auto_submit);
            ?>
            <?php
        }
        unset($vars['adv_uploader']); // dont use adv_uploader arg for noncing! TODO
        ?>
        <div class="form-wrap">
            <form enctype="multipart/form-data" name="<?php echo $prefix; ?>form" id="<?php echo $prefix; ?>form"
                  method="post" action="<?php echo $form_url; ?>">
                <div>
                    <?php self::DisplayExtendedFormFields($prefix, $secret_key, $vars , $extended ); ?>

                    <?php if (empty($adv_uploader)) { ?>
                        <label for="<?php echo $prefix ?>file_upload"><?php _e('Choose File', 'wp-filebase') ?></label>
                        <input type="file" name="file_upload" id="<?php echo $prefix ?>file_upload"/>
                        <br/> <!--   style="width: 160px" size="10" -->
                        <?php
                    } else {
                        $adv_uploader->Display($prefix);
                    }
                    ?>
                    <small><?php printf(str_replace('%d%s', '%s', __('Maximum upload file size: %s.'/* def */)), WPFB_Output::FormatFilesize(WPFB_Core::GetMaxUlSize())) ?></small>
                    <?php if ($embedded_form) echo $embedded_form->GetCform7Html(); ?>
                    <?php if (empty($auto_submit)) { ?>
                        <div style="float: right; text-align:right;"><input type="submit" class="button-primary"
                                                                            name="submit-btn"
                                                                            value="<?php _e('Add New', 'wp-filebase'); ?>"/>
                        </div>
                    <?php } ?>
                </div>

            </form>
        </div>
        <?php
    }

    static function DisplayExtendedFormFields($prefix, $secret_key, $hidden_vars = array() , $extended = false)
    {
        $category = $hidden_vars['cat'];
        $nonce_action = "$prefix=";
        if (!empty($secret_key))
            $nonce_action .= $secret_key;

            $hidden_vars = array_filter($hidden_vars, function($v) {
                return !(is_object($v) || is_array($v));
            });            

        foreach ($hidden_vars as $n => $v) {
            echo '<input type="hidden" name="' . esc_attr($n) . '" value="' . esc_attr($v) . '" id="' . $prefix . esc_attr($n) . '" />';

            if (!in_array($n, array('adv_uploader', 'frontend_upload', 'prefix')))
                $nonce_action .= "&$n=$v";
        }
        wp_nonce_field($nonce_action, 'wpfb-file-nonce');
        ?>
        <input type="hidden" name="prefix" value="<?php echo $prefix ?>"/>

        <?php
        if ($extended) {
            wpfb_loadclass('Admin');
            global $wp_styles;
            ?>
            <link rel='stylesheet' href='<?php echo $wp_styles->base_url; ?>/wp-admin/css/forms.css' type='text/css'
                  media='all'/>
            <link rel='stylesheet' href='<?php echo $wp_styles->base_url; ?>/wp-admin/css/edit.css' type='text/css'
                  media='all'/>
            <div class="form-field">
                <label for="<?php echo $prefix; ?>file_display_name"><?php _e('Title') ?></label>
                <input id="<?php echo $prefix; ?>file_display_name" name="file_display_name" type="text"
                       style="width: 200px;"/>
            </div>
            <div class="form-field">
                <label for="<?php echo $prefix; ?>file_version"><?php _e('Version') ?></label>
                <input id="<?php echo $prefix; ?>file_version" name="file_version" type="text" style="width: 200px;"/>
            </div>
            <div class="form-field">
                <label for="<?php echo $prefix; ?>file_tags"><?php _e('Tags') ?></label>
                <input id="<?php echo $prefix; ?>file_tags" name="file_tags" type="text" style="width: 200px;"/>
            </div>
            <div class="form-field">
                <label for="<?php echo $prefix; ?>file_description"><?php _e('Description') ?></label>
                <textarea id="<?php echo $prefix; ?>file_description" name="file_description"></textarea>
            </div>

            <?php if (!empty(WPFB_Core::$settings->licenses)) { ?>
                <div class="form-field">
                    <label for="<?php echo $prefix; ?>file_license"><?php _e('License', 'wp-filebase') ?></label>
                    <select name="file_license"
                            id="<?php echo $prefix; ?>file_license"><?php echo WPFB_Admin::MakeFormOptsList('licenses', null, true) ?></select>
                </div>
            <?php } ?>

            <?php if (WPFB_Core::$settings->requirements) { ?>
                <div class="form-field">
                    <label
                            for="<?php echo $prefix; ?>file_requirements[]"><?php _e('Requirements', 'wp-filebase') ?></label>
                    <select name="file_requirements[]" size="40" multiple="multiple"
                            id="<?php echo $prefix; ?>file_requirements[]"
                            style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('requirements', $file ? $file->file_requirement : null, true) ?></select>
                </div>
            <?php } ?>

            <?php if (WPFB_Core::$settings->languages) { ?>
                <div class="form-field">
                    <label for="<?php echo $prefix; ?>file_languages[]"><?php _e('Languages') ?></label>
                    <select name="<?php echo $prefix; ?>file_languages[]" size="40" multiple="multiple"
                            id="<?php echo $prefix; ?>file_languages[]"
                            style="height: 80px;"><?php echo WPFB_Admin::MakeFormOptsList('languages', $file ? $file->file_language : null, true) ?></select>
                </div>
            <?php } ?>
            <?php
            $custom_fields = WPFB_Core::GetCustomFields();
            foreach ($custom_fields as $ct => $cn) {
                $hid = 'file_custom_' . esc_attr($ct);
                ?>
                <div class="form-field">
                    <label for="<?php echo $prefix . $hid; ?>"><?php echo esc_html($cn) ?></label>
                    <textarea name="<?php echo $hid; ?>" id="<?php echo $prefix . $hid; ?>" rows="2" cols="50"
                              style="width: 97%;"></textarea>
                </div>
                <?php
            }
        } // extended
        if ($category == -1) {
            ?>
            <div>
                <label for="<?php echo $prefix ?>file_category"><?php                     !empty(WPFB_Core::$settings->category_substitute) ? print(esc_html(WPFB_Core::$settings->category_substitute)) :                         _e('Category', 'wp-filebase') ?></label>
                <select name="file_category" id="<?php echo $prefix; ?>file_category"><?php
                    wpfb_loadclass('Category');
                    echo WPFB_Output::CatSelTree(array('none_label' => __('Select'), 'check_add_perm' => true));
                    ?></select>
            </div>
        <?php } else { ?>
            <input type="hidden" name="file_category" value="<?php echo $category; ?>"
                   id="<?php echo $prefix ?>file_category"/>
        <?php } ?>
        <?php
    }

    static function GetSearchForm($action, $hidden_vars = array(), $prefix = null)
    {
        global $wp_query;

        $searching = !empty($_GET['wpfb_s']);
        if ($searching) { // set preset value for search form
            $sb = empty($wp_query->query_vars['s']) ? null : $wp_query->query_vars['s'];
            $wp_query->query_vars['s'] = stripslashes($_GET['wpfb_s']);
        }

        ob_start();
        echo "<!-- WPFB searchform -->";
        get_search_form();
        echo "<!-- /WPFB searchform -->";
        $form = ob_get_clean();

        $form = str_replace(array("\r\n", "\n"), " ", $form);

        if ($searching)
            $wp_query->query_vars['s'] = $sb; // restore query var s

        $form = preg_replace('/action=["\'].+?["\']/', 'action="' . esc_attr($action) . '"', $form, -1, $count);
        if ($count === 0) {
            return "<!-- NO FORM ACTION MATCH -->";
        }
        $form = str_replace(array('name="s"', "name='s'"), array('name="wpfb_s"', "name='wpfb_s'"), $form);

        if (isset($hidden_vars['placeholder']))
            $form = preg_replace('/placeholder=["\'].+?["\']/', 'placeholder="' . esc_attr($hidden_vars['placeholder']) . '"', $form);

        $form = preg_replace('/<input[^>]+?name="post_type"[^>]*?' . '>/', '', $form); // rm any post_type

        if (!empty($hidden_vars)) {
            $gets = '';
            foreach ($hidden_vars as $name => $value)
                if ($name != 'wpfb_s' && $name != 'wpfb_list_page')
                    $gets .= '<input type="hidden" name="' . esc_attr(stripslashes($name)) . '" value="' . esc_attr(stripslashes($value)) . '" />';
            $form = str_ireplace('</form>', "$gets</form>", $form);
        }

        if (!empty($prefix)) {
            $form = str_replace('id="', 'id="' . $prefix, $form);
            $form = str_replace("id='", "id='" . $prefix, $form);
        }
        return $form;
    }

}
