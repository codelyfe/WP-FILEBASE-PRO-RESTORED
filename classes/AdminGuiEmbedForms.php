<?php
class WPFB_AdminGuiEmbedForms {
	static function Display()
	{
		wpfb_loadclass('Output','EmbeddedForm');
		
		if(!empty($_REQUEST['action'])) {
			if(!WPFB_Core::CheckPermission('manage_forms'))
				wp_die(__('Cheatin&#8217; uh?'));
				
			switch($_REQUEST['action']) {
							
				case "new-form":
					$form = new WPFB_EmbeddedForm();
					$_POST['tag'] = sanitize_key($_POST['tag']);
					$form->Edited($_POST);
					break;
					
				case "edit-form":
					echo '<div class="wrap">';
					self::Form(WPFB_EmbeddedForm::Get($_REQUEST['form_tag']));
					echo "</div>";
					return;
					
				case "edited-form":
					$form = WPFB_EmbeddedForm::Get($_POST['tag']);
					$form->Edited($_POST);
					break;
					
				case "delete-form":
					WPFB_EmbeddedForm::Get($_REQUEST['form_tag'])->Delete();
					break;
			}
		}
		
	    $list_table = new WPFB_EmbeddableForms_List_Table();
	    $list_table->prepare_items();    
	    
	    if(!WPFB_Core::$settings->frontend_upload) {
	    	echo "
	<div id='ebksel-warning' class='updated fade'><p><strong>".__('Front end upload disabled!','wp-filebase')."</strong> ".sprintf(__("Embedded forms will not work unless you enable front end uploads in <a href=\"%s\">Security Settings</a>",'wp-filebase'), admin_url('admin.php?page=wpfilebase_sets#'.sanitize_title(__('Security','wp-filebase'))))."</p></div>
	";
	    }
    ?>
    <div class="wrap"> 
    <h2><?php _e('Embeddable Forms','wp-filebase') ?></h2>      
        <form method="post">
            <?php $list_table->display() ?>
        </form>
        <?php self::Form(); ?>        
    </div>
    <?php
	}

static function Form($edit_form = null)
{	
	extract(is_null($edit_form) ? (empty($_POST['tag']) ? get_class_vars('WPFB_EmbeddedForm') : $_POST) : (array)$edit_form);
	?>
	<a name="new"></a>
	<h2><?php _e(is_null($edit_form) ? 'New Embedable Form' : 'Edit Embeddable Form','wp-filebase'); if($edit_form) echo " -  <i>".esc_html($edit_form->tag).'</i>'; ?></h2>
	<form action="<?php echo remove_query_arg(array('action','service_class')) ?>" method="post">
		<input type="hidden" name="action" value="<?php echo is_null($edit_form)? 'new-form' : 'edited-form'; ?>" />
		
	<table class="form-table">
	<?php if(is_null($edit_form)) { ?>
	<tr>
		<th><label for="form-tag"><?php _e('Form Tag:','wp-filebase') ?></label></th>
		<td><input type="text" name="tag" value="<?php echo esc_attr(@$tag); ?>" tabindex="1" maxlength="20" /><br /><?php _e('A single word to describe the form.','wp-filebase'); ?></td>
	</tr>
	<?php } else { echo "<input type=\"hidden\" name=\"tag\" value=\"$tag\" />"; } ?>
	
	
	<tr><th><?php _e('Permission') ?></th>
	<td>
	<div id="form-perms"><?php WPFB_Admin::RolesCheckList('permissions', @$permissions) ?></div>
<?php
	printf('Make sure the roles checked here are also selected in <a href="%s">Frontend Upload Permissions</a>', admin_url('admin.php?page=wpfilebase_sets#'.sanitize_title(__('Permissions','wp-filebase'))));
?>
	</td></tr>
	
	
	<tr>
		<th><label for="form-cat"><?php _e('Upload to category:','wp-filebase'); ?></label></th>
		<td><select id="form-cat" name="cat_id" class="wpfb-cat-select">
			<option value="-1"  style="font-style:italic;"><?php _e('Selectable by Uploader','wp-filebase'); ?></option>
			<?php echo WPFB_Output::CatSelTree(array('selected' => @$cat_id, 'none_label' => __('Upload to Root','wp-filebase'), 'add_cats' => true)); ?>
		</select></td>
	</tr>
	
	
	<tr><th>Contact Form 7 Form</th>
		<td>
			<?php $cforms = WPFB_EmbeddedForm::GetCform7Forms(); ?>
			<select id="form-cform7_id" name="cform7_id" <?php disabled(empty($cforms)); ?>>
			<?php
				echo "<option value='0'>".(empty($cforms) ? __('No Contact Forms found or Contact Form 7 not installed','wp-filebase') : __('None'))."</option>";
				foreach($cforms as $cform) {
					$id = is_callable(array($cform,'id')) ? $cform->id() : $cform->id;
					echo "<option value='{$cform->id()}'";
					selected($cform->id(), @$cform7_id);
					echo ">{$cform->title()}</option>";
				}
			?>
			</select>
                            <a href="" id="wpfb-edit-cform"><?php _e('Edit'); ?></a>
                        <script>
                            var wpfbUpdateCformEdit = function() {
                               var fi = jQuery('#form-cform7_id').val(), a = jQuery('#wpfb-edit-cform').attr('href', 'admin.php?page=wpcf7&post='+fi+'&action=edit');
                               (fi > 0) ? a.show() : a.hide();
                            };
                            wpfbUpdateCformEdit();
                            jQuery('#form-cform7_id').change(wpfbUpdateCformEdit);
                        </script>
                        <br />
			You can choose a Contact Form here for additional input elements.
		</td>
	</tr>
	
	<tr><th></th>
		<td><input type="checkbox" id="form-flash_uploader" name="flash_uploader" value="1" <?php checked(@$flash_uploader); ?> />
		<label for="form-flash_uploader"><?php _e('Use advanced Drag &amp; Drop Uploader','wp-filebase') ?></label></td>
	</tr>	
	
	<tr><th></th>
		<td><input type="checkbox" id="form-extended" name="extended" value="1" <?php checked(@$extended); ?> />
		<label for="form-extended"><?php _e('Show extended input fields like Title, Version, Description etc.','wp-filebase') ?></label></td>
	</tr>

	<tr><th></th>
		<td><input type="checkbox" id="form-overwrite" name="overwrite" value="1" <?php checked(@$overwrite); ?> />
		<label for="form-overwrite"><?php _e('Overwrite existing files','wp-filebase') ?></label></td>
	</tr>
	
	<tr><th></th>
		<td><input type="checkbox" id="form-file_approval" name="file_approval" value="1" <?php checked(@$file_approval); ?> />
		<label for="form-file_approval"><?php _e('Uploaded files must been approved','wp-filebase') ?></label></td>
	</tr>
	
	<tr><th></th>
		<td><input type="checkbox" id="form-attach_files" name="attach_files" value="1" <?php checked(@$attach_files); ?> />
		<label for="form-attach_files"><?php _e('Attach uploaded files to post or page the form is embedded in','wp-filebase') ?></label></td>
	</tr>	
	
	<tr><th></th>
		<td><input type="checkbox" id="form-notify_admins" name="notify_admins" value="1" <?php checked(@$notify_admins); ?> />
		<label for="form-notify_admins"><?php _e('Notify Admins whenever a file is uploaded','wp-filebase') ?></label></td>
	</tr>
	
	<tr><th><label for="form-notify_emails">More email notifications</label></th>
		<td><input type="text" id="form-notify_emails" name="notify_emails" value="<?php echo esc_attr(@$notify_emails); ?>" style="width:98%;" />
		<br />
		<?php _e('Enter additional email addresses that will be notified on upload. Seperate by comma <code>,</code> .','wp-filebase') ?></td>		
	</tr>
		
	<tr><th><?php _e('Upload Success Template') ?></th>
	<td>
		<select id="form-confirm_tpl" name="confirm_tpl">
			<?php echo WPFB_Admin::TplDropDown('file', @$confirm_tpl) ?>
		</select>
		<br />Only used with normal uploader, <b>not</b> drag &amp drop uploader!
	</td></tr>
	
	<tr><th><?php _e('High security') ?></th>
		<td><input type="checkbox" id="form-shortcode_check" name="shortcode_check" value="1" <?php checked(!@$no_shortcode_check); ?> />
		<label for="form-shortcode_check"><?php _e('Only allow placement inside post content. This provides better security since the upload only works if the form shortcode has actually been placed in the post and the uploading user can read this post. If you want to place the form directly into the template using the <code>do_shortcode</code> function this must be disabled.','wp-filebase') ?></label></td>
	</tr>
		<?php do_action('wpfilebase_embedform_admin_fields', $edit_form); ?>
	</table>
	<p class="submit"><input type="submit" name="submit" class="button-primary" value="<?php _e("Submit") ?>" /></p>
</form>
	<?php
}
}


if(!class_exists('WP_List_Table'))
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    
class WPFB_EmbeddableForms_List_Table extends WP_List_Table {
	var $editor_plugin;
	var $js_insert_callback;
	
    function __construct($editor_plugin = false, $js_insert_callback = null){
        global $status, $page;
                
        $this->editor_plugin = $editor_plugin;
        $this->js_insert_callback = $js_insert_callback;
 		wpfb_loadclass('EmbeddedForm');
		
		
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'form',     //singular name of the listed records
            'plural'    => 'forms',    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
        
    }

	public function no_items() {
		_e( 'No items found.' );
		echo ' <a href="'.esc_attr(admin_url('admin.php?page=wpfilebase_embedforms#new')).'">Create new form</a>';
	}
    
    function column_default($item, $column_name){
    	return '???';
    }    
    
    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ 'form_tag',
            /*$2%s*/ urlencode($item->tag)
        );
    }
    
    function column_title($item){
        
        //Build row actions
        $actions =  $this->editor_plugin ?
          array('insert' => '<a href="javascript:void();" onclick="'.$this->js_insert_callback.'(\''.$item->tag.'\')">Insert</a>')
        : array(
            'edit'      => sprintf('<a href="?page=%s&action=%s&form_tag=%s">Edit</a>',$_REQUEST['page'],'edit-form',urlencode($item->tag)),
            'delete'    => sprintf('<a href="?page=%s&action=%s&form_tag=%s">Delete</a>',$_REQUEST['page'],'delete-form',urlencode($item->tag)),
        );
        
        //Return the title contents
        return $item->tag . $this->row_actions($actions);
    }
    
    function column_cat($item)
    {
    	if($item->cat_id == -1) return '<i>'.__('Selectable by Uploader','wp-filebase').'</i>';
    	if($item->cat_id == 0) return '<i>'.__('Upload to Root','wp-filebase').'</i>';
    	return is_null($cat=WPFB_Category::GetCat($item->cat_id)) ? 'Category does not exists!' : $cat->GetTitle();    	
    }
    
    function column_overwrite($item) { return __($item->overwrite?'Yes':'No'); }
    function column_flash_uploader($item)  { return __($item->flash_uploader?'Yes':'No'); }
    function column_permissions($item) { return WPFB_Output::RoleNames($item->permissions, true); }    
	function column_approval($item)  { return __($item->file_approval?'Yes':'No'); }
    
    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            'title'     => __('Name'),
        	'cat'		=> __('Category'),
        	'overwrite' => __('Overwrite'),
        	'flash_uploader' => __('Drag &amp; Drop uploader'),
        	'permissions' => __('Permissions'),
        	'approval' => __('Upload Approval'),
        );
        if($this->editor_plugin) unset($columns['cb']);
        return $columns;
    }
    
    function get_bulk_actions() {
    	if($this->editor_plugin) return array();
        $actions = array(
            'delete'    => __('Delete')
        );
        return $actions;
    }
    
    
    function process_bulk_action() {
    	if($this->editor_plugin) return;
    	
		if(!WPFB_Core::CheckPermission('manage_forms'))
			wp_die(__('Cheatin&#8217; uh?'));
    	
        if(empty($_REQUEST['form_tag']) || !is_array($_REQUEST['form_tag'])) return;
    	switch($this->current_action()) {
    		case 'delete':
    			$forms = WPFB_EmbeddedForm::GetAll(); 
    			foreach($_REQUEST['form_tag'] as $tag) {
    				if(is_object($forms[$tag])) $forms[$tag]->Delete();
    			}
    			break;
    	}        
    }
    
    function prepare_items() {
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        $this->process_bulk_action();        
        
        $data = WPFB_EmbeddedForm::GetAll();      

        $total_items = count($data);
        $this->items = $data;
        
        
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $total_items,                     //WE have to determine how many items to show on a page
            'total_pages' => 1   //WE have to calculate the total number of pages
        ) );
    }
}
