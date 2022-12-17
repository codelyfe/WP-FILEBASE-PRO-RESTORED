<?php

class WPFB_AdminGuiRemoteSync
{


    static function Display()
    {
        wpfb_loadclass('Output', 'RemoteSync');

        if (!empty($_REQUEST['action'])) {
            switch ($_REQUEST['action']) {
                case "rsync":
                    $rsync = WPFB_RemoteSync::GetSync($_REQUEST['rsync_id']);
                    if (empty($rsync)) wp_die('Remote Sync not found!');

                    if (!empty($_REQUEST['flush-cache']))
                        $rsync->cacheFlush();

                    wpfb_loadclass('ProgressReporter');
                    $progress_reporter = new WPFB_ProgressReporter();
                    $progress_reporter->InitMemBar();
                    try {
                        $rsync->Sync(false, $progress_reporter);
                    } catch (Exception $ex) {
                        $progress_reporter->LogException($ex);
                    }
                    $progress_reporter->ChangedFilesReport();
                    return;

                case "new-rsync":
                    $service_class = $_REQUEST['service_class'];
                    if (!WPFB_RemoteSync::IsServiceClass($service_class))
                        wp_die('Not a service class!');
                    $rsync = new $service_class($_REQUEST['name']);
                    $rsync->DisplayEditForm();
                    WPFB_RemoteSync::AddSync($rsync);
                    return;

                case "edit-rsync":
                    $rsync = WPFB_RemoteSync::GetSync($_REQUEST['rsync_id']);
                    if (empty($rsync)) wp_die('Remote Sync not found!');
                    $rsync->DisplayEditForm();
                    return;


                case "edited-rsync":
                    $rsync = WPFB_RemoteSync::GetSync($_REQUEST['rsync_id']);
                    if (empty($rsync)) wp_die('Remote Sync not found!');
                    $res = $rsync->Edited(stripslashes_deep($_POST));
                    if ($res['err']) wp_die($res['err']);
                    if (!empty($res['reload_form'])) {
                        $rsync->DisplayEditForm();
                        return;
                    }
                    break;

                case "delete-rsync":
                    WPFB_RemoteSync::DeleteSync($_REQUEST['rsync_id']);
                    break;

                case "export-rsync":
                    $rsync = WPFB_RemoteSync::GetSync($_REQUEST['rsync_id']);
                    if (empty($rsync)) wp_die('Remote Sync not found!');
                    $rsync->ToJson();
                    return;

                case "duplicate-rsync":
                    $rsync = WPFB_RemoteSync::GetSync($_REQUEST['rsync_id']);
                    if (empty($rsync)) wp_die('Remote Sync not found!');
                    $rsync2 = $rsync->cloneMe();
                    $rsync2->Save();
                    WPFB_AdminLite::JsRedirect(add_query_arg(array('action' => 'edit-rsync', 'rsync_id'=>$rsync2->GetId())));
                    return;
            }
        }

        //Create an instance of our package class...
        $list_table = new WPFB_RemoteSync_List_Table();
        $items = $list_table->prepare_items();

        wpfb_call('Output', 'PrintJS');
        ?>
        <div class="wrap">
            <?php
            if (count($items) > 0) {
                ?>
                <h2><?php _e('Cloud Syncs', 'wp-filebase') ?><input type="file" id="rsync-import-file"
                                                                    name="rsync-import-file"
                                                                    style="position:relative; width:70px; margin-right: -70px; opacity: 0.01; z-index: 1;"
                                                                    ;/>
                    <a href="" class="page-title-action"><?php _e('Import'); ?></a></h2>
                <form method="post" id="rsync-actions">
                    <?php $list_table->display() ?>
                </form>

                <script>


                    jQuery('#rsync-import-file').change(function () {
                        //jQuery(this).val();
                        alert('Sorry, not yet supported!'); // TODO
                    });

                    jQuery('#rsync-actions').submit(function () {
                        var isExport = jQuery(this).find('select[name=action]').val() == 'export' || jQuery(this).find('select[name=action2]').val() == 'export';
                        if (isExport)
                            return confirm('Exporting a Cloud Sync generates a file containing its settings and authentication data. This file allows full access to your cloud hosting service, so keep it safe! Do you want to continue?');
                        return true;
                    });
                </script>

                <?php
            }
            self::NewSyncForm(); ?>
        </div>
        <?php
    }

    static function ServiceDropDown()
    {
        $srvs = WPFB_RemoteSync::GetServiceClasses();
        $content = '';

        foreach ($srvs as $tag => $name) {
            $logo_func = array($tag, 'GetServiceLogo');
            if(property_exists($tag, 'deprecated')) {
                continue;
            }
            //$style = is_callable($logo_func) ? 'style="background-image:url('.esc_attr(call_user_func($logo_func)).');"' : '';
            $content .= '<option value="' . $tag . '">' . esc_attr($name) . '</option>';
        }
        return $content;
    }

    static function NewSyncForm()
    {
        ?>
        <div class="form-wrap">
            <h2><?php _e('New Cloud Sync', 'wp-filebase') ?></h2>
            <form id="rsync-actions" action="<?php echo remove_query_arg(array('action', 'service_class')) ?>"
                  method="post" class="validate">
                <input type="hidden" name="action" value="new-rsync"/>
                <div class="form-field form-required">
                    <label for="new-rsync-service"><?php _e('Cloud Service'); ?>:</label>
                    <select id="new-rsync-service" name="service_class"><?php echo self::ServiceDropDown(); ?></select>
                    <a href="<?php echo esc_attr(admin_url('admin.php?page=wpfilebase_manage&action=install-extensions')); ?>">See
                        Extensions</a> for more services
                    <p><?php _e('Select the service you would like to sync with.', 'wp-filebase'); ?></p>
                </div>
                <div class="form-field form-required">
                    <label for="new-rsync-name">Name</label>
                    <input id="new-rsync-name" name="name" type="text" style="width: 120px;"/>
                    <p><?php _e('An identifier or short description', 'wp-filebase'); ?></p>
                </div>
                <p class="submit"><input type="submit" name="submit" class="button-primary"
                                         value="<?php _e("Continue") ?>"/></p>
            </form>
        </div>
        <?php
    }

}


if (!class_exists('WP_List_Table'))
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WPFB_RemoteSync_List_Table extends WP_List_Table
{
    function __construct()
    {
        global $status, $page;

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'sync',     //singular name of the listed records
            'plural' => 'syncs',    //plural name of the listed records
            'ajax' => false        //does this table support ajax?
        ));

    }

    function column_default($item, $column_name)
    {
        return '???';
    }

    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],
            /*$2%s*/
            $item->GetId()
        );
    }

    function column_title($item)
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_STRING);
        //Build row actions
        $actions = array(
            'edit' => sprintf('<a href="?page=%s&action=%s&rsync_id=%s">Edit</a>', $page, 'edit-rsync', $item->GetId()),
            'delete' => sprintf('<a onclick="return confirm(\'Sure?\')" href="?page=%s&action=%s&rsync_id=%s">Delete</a>', $page, 'delete-rsync', $item->GetId()),
            'sync' => sprintf('<a href="?page=%s&action=%s&rsync_id=%s&no-ob=1">Sync</a>', $page, 'rsync', $item->GetId()),
            'flush-sync' => sprintf('<a href="?page=%s&action=%s&rsync_id=%s&no-ob=1&flush-cache=1">Flush cache</a>', $page, 'rsync', $item->GetId()),
            'duplicate' => sprintf('<a href="?page=%s&action=%s&rsync_id=%s">Duplicate</a>', $page, 'duplicate-rsync', $item->GetId()),
        );


        //Return the title contents
        return sprintf('%1$s <span style="color:silver">(id:%2$s)</span> %3$s',
            /*$1%s*/
            $item->GetTitle(),
            /*$2%s*/
            $item->GetId(),
            /*$3%s*/
            $item->TryLock() ? $this->row_actions($actions) : WPFB_Admin::Icon('lock', 16, '#95a5a6')
        );
    }

    function column_service($item)
    {
        $logo_func = array(get_class($item), 'GetServiceLogo');
        $logo = is_callable($logo_func) ? '<img src="' . esc_attr(call_user_func($logo_func)) . '" style="max-width:48px;" />' : '';
        return $logo . $item->GetServiceName();
    }

    function column_account($item)
    {
        return $item->GetAccountName();
    }

    function column_remote_path($item)
    {
        return $item->GetRemotePath();
    }

    function column_cat($item)
    {
        return is_null($item->GetCat()) ? '-' : $item->GetCat()->GetTitle();
    }

    function column_last_sync_time($item)
    {
        return ($item->GetLastSyncTime() == 0) ? __('Never') : sprintf(__('%s ago'), human_time_diff($item->GetLastSyncTime()));
    }

    /**
     * @param WPFB_RemoteSync $item
     * @return string
     */
    function column_num_files($item)
    {
        $l = esc_attr(admin_url("admin.php?page=wpfilebase_files&rsync_id={$item->GetId()}"));
        return "<a href='{$l}'>{$item->GetNumFiles()}</a>";
    }

    function column_deletion($item)
    {
        return $item->GetDeletionState() ? 'Remove files from cloud' : 'Don\'t remove files from cloud';
    }

    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'title' => __('Name'),
            'service' => __('Service'),
            'account' => __('Account'),
            'remote_path' => __('Remote Path'),
            'cat' => 'Root Category',
            'last_sync_time' => 'Sync Time',
            'num_files' => 'Num. Files',
            'deletion' => 'Deletion',
        );
        return $columns;
    }

    function get_bulk_actions()
    {
        $actions = array(
            'sync' => __('Sync', 'wp-filebase'),
            'delete' => __('Delete'),
            'export' => __('Export')
        );
        return $actions;
    }


    function getCloudSyncPluginSlug($class)
    {
        $reflector = new ReflectionClass($class);
        $dir = dirname($reflector->getFileName());
        while (!file_exists($dir . '/readme.txt') && strlen($dir) > 6) $dir = dirname($dir);
        return basename($dir);
    }

    function process_bulk_action()
    {
        if (empty($_REQUEST['sync']) || !is_array($_REQUEST['sync'])) return;
        switch ($this->current_action()) {
            case 'delete':
                foreach ($_REQUEST['sync'] as $id)
                    WPFB_RemoteSync::DeleteSync($id);
                break;

            case 'sync':
                wpfb_loadclass('ProgressReporter');
                $progress_reporter = new WPFB_ProgressReporter();
                $progress_reporter->InitMemBar();
                foreach ($_REQUEST['sync'] as $id) {
                    if (is_null($rsync = WPFB_RemoteSync::GetSync($id)))
                        continue;
                    try {
                        $rsync->Sync(true, $progress_reporter);
                    } catch (Exception $ex) {
                        $progress_reporter->LogException($ex);
                    }
                }
                $progress_reporter->ChangedFilesReport();
                do_action('wpfilebase_bgscan');
                break;

            case 'export':
                $serialized = array();
                foreach ($_REQUEST['sync'] as $id) {
                    if (is_null($rsync = WPFB_RemoteSync::GetSync($id)))
                        continue;
                    $serialized[$rsync->GetTitle()] = base64_encode($rsync->Serialize());
                    $class = get_class($rsync);

                    $serialized[$rsync->GetTitle() . '__meta'] = array(
                        'class' => $class,
                        'plugin_slug' => $this->getCloudSyncPluginSlug($class)
                    );
                }

                $suffix = sanitize_file_name(join('-', array_keys($serialized)));
                $header = "/* WP-Filebase Cloud Sync Data - https://wpfilebase.com/ */\r\n";
                echo '<textarea id="rsync-export" class="code" style="display: none; width: 98%; height: 70%;">' . esc_html($header . json_encode($serialized, JSON_PRETTY_PRINT)) . '</textarea>';
                ?>
                <h2>Exporting...</h2>
                <script type="application/javascript">
                    function download(filename, text) {
                        var element = document.createElement('a');
                        element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
                        element.setAttribute('download', filename);

                        element.style.display = 'none';
                        document.body.appendChild(element);

                        element.click();

                        document.body.removeChild(element);
                    }

                    download('wpfilebase.cloudsync.<?php echo $suffix; ?>.json', jQuery('#rsync-export').val());
                    setTimeout(function () {
                        window.history.back();
                    }, 500);
                </script>
                <?php

                exit;
        }
    }


    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     *
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();

        $data = WPFB_RemoteSync::GetSyncs();

        $total_items = count($data);
        $this->items = $data;


        $this->set_pagination_args(array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page' => $total_items,                     //WE have to determine how many items to show on a page
            'total_pages' => 1   //WE have to calculate the total number of pages
        ));

        return $data;
    }
}