<?php
/*
 * WP-Filebase has its own shadow tables for files and categories. These are syncronised with custom post types and taxonomies
 * These lib provides the necessary functions
 */

/**
 * Description of WPPostLib
 *
 * @author Fabian
 */
class WPFB_WPPostLib {

	public static function OnCreatedTerm($term_id) {
		// check if term created through GUI, then create a new File Category for this
		if (empty($_REQUEST['action']) || $_REQUEST['action'] !== 'add-tag')
			return;
		$term = get_term($term_id, 'wpfb_file_category');
		if (!$term || is_wp_error($term))
			return;

		wpfb_loadclass('Admin');

		WPFB_Admin::InsertCategory(array(
			 'cat_name' => $term->name,
			 'cat_wp_term_id' => $term_id,
			 'cat_parent' => $term->parent ? WPFB_Category::GetByWPTermId($term->parent)->GetId() : 0,
			 'cat_description' => $term->description
		));
	}

	public static function OnTermEdited($term_id) {
		// check if term created through GUI, then create a new File Category for this
		if (empty($_REQUEST['action']) || ($_REQUEST['action'] !== 'editedtag' && $_REQUEST['action'] !== 'inline-save-tax'))
			return;
		$term = get_term($term_id, 'wpfb_file_category');
		if (!$term || is_wp_error($term))
			return;


		wpfb_loadclass('Admin');

		$cat = WPFB_Category::GetByWPTermId($term_id);
		WPFB_Admin::InsertCategory(array(
			 'cat_id' => $cat->GetId(),
			 'cat_name' => $term->name,
			 'cat_parent' => $term->parent ? WPFB_Category::GetByWPTermId($term->parent)->GetId() : 0,
			 'cat_description' => $term->description
		));
	}

	public static function GetAttachmentImageSrc($image, $attachment_id, $size, $icon) {
		$post = get_post();

		// image is array (url, width, height)       

		// TODO
		if ($post && $post->post_type === 'wpfb_filepage' && $attachment_id == $post->ID) {
			//global $content_width, $_wp_additional_image_sizes;
			//$siz_wh = $_wp_additional_image_sizes[$size];
			//
            //return array(WPFB_File::GetByPost($post->ID)->GetIconUrl(), 0, 0);
		}

		return $image;
	}

	static function GetEditPostLinkFilter($link, $post_id = 0) {
		global $post;
		return ($post && is_object($post) && $post->post_type === 'wpfb_filepage' && !is_null($file = wpfb_call('File', 'GetByPost', $post->ID)))
			? ($file->GetEditUrl() . '&redirect_to=' . urlencode(get_permalink($post->ID)))
			: $link;
	}

	static function CategoryFormFields() {
		wpfb_loadclass('Category');
		$file_category = new WPFB_Category();
		?>
		<div class="form-field cat-icon-wrap">
			<label for="cat_icon"><?php _e('Category Icon', 'wp-filebase') ?></label>
			<input type="file" name="cat_icon" id="cat_icon" class="postform" />
		<?php if (!empty($file_category->cat_icon)) { ?>
				<br /><img src="<?php echo $file_category->GetIconUrl(); ?>" alt="Icon" /><br />
				<input class="postform" type="checkbox" value="1" name="cat_icon_delete" id="file_delete_thumb" /><label for="cat_icon_delete"><?php _e('Delete'/* def */); ?></label>
			<?php } ?>
		</div>

		<div class="form-field cat-exclude-browser">
			<input type="checkbox" name="cat_exclude_browser" id="cat_exclude_browser" value="1" <?php checked($file_category->cat_exclude_browser) ?> />
			<label for="cat_exclude_browser"><?php _e('Exclude from file browser', 'wp-filebase') ?></label>
		</div>

		<div class="form-field cat-order">
			<label for="cat_order"><?php _e('Custom Sort Order', 'wp-filebase') ?></label>
			<input name="cat_order" id="cat_order" type="number" value="<?php echo esc_attr($file_category->cat_order); ?>" style="width: 100px;" />
		</div>

		<?php
	}

	static function QuickEditBox() {
		?>
		<fieldset>
			<legend class="inline-edit-legend">P</legend>
			<div class="inline-edit-col">
				<label>
					<span class="title">Name</span>
					<span class="input-text-wrap"><input type="text" name="name" class="ptitle" value=""></span>
				</label>
				<label>
					<span class="title">Slug</span>
					<span class="input-text-wrap"><input type="text" name="slug" class="ptitle" value=""></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

}
