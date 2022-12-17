<?php

class WPFB_GetID3 {

	static $engine;

	static function GetEngine() {
		if (!self::$engine) {
			if (!class_exists('getID3')) {
				$tmp_dir = WPFB_Core::UploadDir() . '/.tmp';
				if (!is_dir($tmp_dir))
					@mkdir($tmp_dir);
				define('GETID3_TEMP_DIR', $tmp_dir . '/');
				unset($tmp_dir);
				require_once(WPFB_PLUGIN_ROOT . 'extras/getid3/getid3.php');
			}

			if (!class_exists('getid3_lib')) {
				require_once(WPFB_PLUGIN_ROOT . 'extras/getid3/getid3.lib.php');
			}

			self::$engine = new getID3;
		}
		return self::$engine;
	}

	private static function xml2Text($content) {
		return trim(esc_html(preg_replace('! +!', ' ', strip_tags(str_replace('<', ' <', $content)))));
	}

	static function Pdf2Text($filename, $page_start, $page_num) {
		$c = pdf2txt_gs(WPFB_Core::$settings->ghostscript_path, $filename, $page_start, $page_num);
		if ($c === false) {
			// failed? split in 2 page sets			
			if ($page_num <= 1)
				return " [PAGEFAILED_{$page_start}] ";
			$d = round($page_num / 2);
			return self::Pdf2Text($filename, $page_start, $d) . self::Pdf2Text($filename, $page_start + $d, $page_num - $d);
		}
		return $c;
	}

	/**
	 * @param WPFB_File $file
	 * @param array $info
	 */
private static function indexDocument($file, &$info, &$times)
{

	$filename = $file->GetLocalPath();

	$ext = strtolower(substr($filename, strrpos($filename, '.') + 1));
	if ($ext == "pdf")
	{
		$times['pdf_pages'] = microtime(true);

		require_once(WPFB_PLUGIN_ROOT . 'extras/pdf-utils.php');
		$info['pdf'] = array('page_text' => array(), 'extracted_title' => null, 'num_pages' => 1);

		@set_time_limit(10);
		$pdf_pages = pdf_get_num_pages(WPFB_Core::$settings->ghostscript_path, $filename);

		if ($pdf_pages <= 0)
			$pdf_pages = 100;

		$info['pdf']['num_pages'] = 0+$pdf_pages;

		// first 3 single pages
		for ($i = 1; $i <= min($pdf_pages, 3); $i++) {
			@set_time_limit(10);
			$c = pdf2txt_gs(WPFB_Core::$settings->ghostscript_path, $filename, $i);
			if ($c === false)
				break; // false means: page overflow, break page loop
			if (empty($c))
				continue;
			$info['pdf']['page_text'][] = $c;
			if (empty($info['pdf']['extracted_title'])) {
				$ms = array();
				$cb = substr($c, 0, 200);
				if (preg_match("/" . WPFB_Core::$settings->pdf_title_regex . "/i", $cb, $ms) > 0) {
					$ms[1] = strip_tags(trim($ms[1]));
					// on failure, the title starts with GPL Ghostscript, skip it then
					if (strpos($ms[1], "GPL Ghostscript") !== 0)
						$info['pdf']['extracted_title'] = $ms[1];
				}
			}
			unset($c);
		}

		//if (!empty($_GET['debug'])) {
		WPFB_Core::LogMsg("Pdf2Text $file");
		// }

		$times['pdf_2text'] = microtime(true);
		@set_time_limit(120);
		$keywords = self::Pdf2Text($filename, 1, $pdf_pages);

		if(empty($keywords) && $pdf_pages > 0 && $pdf_pages < 20) {
			$keywords = pdf2text($filename);
		}


		if (!empty($_GET['debug'])) {
			wpfb_loadclass('Sync');
			WPFB_Sync::PrintDebugTrace("pdf2txt_" . $file->GetLocalPathRel());
		}

		$info['keywords'] = $keywords;
		// only use alt text extraction for small pdf files
		if ($pdf_pages <= 60) {
			WPFB_Core::LogMsg("pdf2txt_keywords $file");
			$times['pdf_2text'] = microtime(true);
			@set_time_limit(120);
			$info['keywords'] .= ' ' . preg_replace('/[\x7F\x00-\x1F]/', ' ', pdf2txt_keywords($filename));
		}
		@set_time_limit(0);
	}
	elseif ($ext == "docx" || $ext == "odt" || $ext == "pptx" || $ext == "pptm" || $ext == "xlsx") {
		$times['unzip'] = microtime(true);
		$zres = self::Unzip($filename); // this can run out of memory!
		if (!empty($zres['dir'])) {
			if (!isset($info[$ext]))
				$info[$ext] = array();

			$times['xml2txt'] = microtime(true);
			if ($ext == "pptx" || $ext == "pptm") {
				$i = 1;
				while (is_file($sf = $zres['dir'] . "/ppt/slides/slide{$i}.xml")) {
					$info[$ext]['slide_text'][$i] = self::xml2Text(file_get_contents($sf));
					$i++;
				}
			} else {
				$content_files = array(
					'docx' => 'word/document.xml',
					'xlsx' => 'xl\sharedStrings.xml',
					'odt' => 'content.xml',
				);
				if (is_file($zres['dir'] . "/" . @$content_files[$ext]))
					$info[$ext]['words'] = self::xml2Text(file_get_contents($zres['dir'] . "/" . @$content_files[$ext]));
			}
			self::DeleteDir($zres['dir']);
		}
	}
}

	/**
	 * Intesive analysis of file contents. Does _not_ make changes to the file or store anything in the DB!
	 * 
	 * @param WPFB_File $file
	 * @return type
	 */
	private static function analyzeFile($file) {
		wpfb_call('Admin', 'DisableTimeouts');
		$filename = $file->GetLocalPath();

		$times = array();
		$times['analyze'] = microtime(true);
		$info = WPFB_Core::$settings->disable_id3 ? array() : self::GetEngine()->analyze($filename);

		if (!WPFB_Core::$settings->disable_id3 && class_exists('getid3_lib')) {
			getid3_lib::CopyTagsToComments($info);
		}

		$info = apply_filters('wpfilebase_analyze_file', $info, $file);


		// only index if keywords not externally set
		if(!isset($info['keywords']))
			self::indexDocument($file, $info, $times);

		
		$times['end'] = microtime(true);			
		$t_keys = array_keys($times);

		$into['debug'] = array('timestamp' => $times[$t_keys[0]], 'timings' => array());			
		for($i = 1; $i < count($t_keys); $i++) {
			$info['debug']['timings'][$t_keys[$i-1]] = round(($times[$t_keys[$i]] - $times[$t_keys[$i-1]]) * 1000);
		}
			
		return $info;
	}

	/**
	 * 
	 * @global type $wpdb
	 * @param WPFB_File $file
	 * @param type $info
	 * @return type
	 */
	static function StoreFileInfo($file, $info) {
		global $wpdb;

		if (empty($file->file_thumbnail)) {
			if (!empty($info['comments']['picture'][0]['data']))
				$cover_img = & $info['comments']['picture'][0]['data'];
			elseif (!empty($info['id3v2']['APIC'][0]['data']))
				$cover_img = & $info['id3v2']['APIC'][0]['data'];
			elseif (!empty($info['document']['thumbnail_url'])) {
				// read thumbnail from external webservice
				$cover_img = @file_get_contents($info['document']['thumbnail_url']);
			} else {
				$cover_img = null;
			}

			// TODO unset pic in info?

			if (!empty($cover_img)) {
				$cover = $file->GetLocalPath();
				$cover = substr($cover, 0, strrpos($cover, '.')) . '.jpg';
				file_put_contents($cover, $cover_img);
				$file->CreateThumbnail($cover, true);
				@unlink($cover);
				$cf_changed = true;
			}
		}

		self::cleanInfoByRef($info);

		// set encoding to utf8 (required for GetKeywords)
		if (function_exists('mb_internal_encoding')) {
			$cur_enc = mb_internal_encoding();
			mb_internal_encoding('UTF-8');
		}


		wpfb_loadclass('Misc');

		$keywords = array();
		WPFB_Misc::GetKeywords($info, $keywords);
		$keywords = strip_tags(join(' ', $keywords));
		$keywords = str_replace(array('\n', '&#10;'), '', $keywords);
		$keywords = preg_replace('/\s\s+/', ' ', $keywords);
		if (!function_exists('mb_detect_encoding') || mb_detect_encoding($keywords, "UTF-8") != "UTF-8")
			$keywords = utf8_encode($keywords);
		// restore prev encoding
		if (function_exists('mb_internal_encoding'))
			mb_internal_encoding($cur_enc);

		// don't store keywords 2 times:
		unset($info['keywords']);
		self::removeLongData($info, 8000);

		$data = empty($info) ? '0' : base64_encode(serialize($info));

		$res = $wpdb->replace($wpdb->wpfilebase_files_id3, array(
			 'file_id' => (int) $file->GetId(),
			 'analyzetime' => time(),
			 'value' => &$data,
			 'keywords' => &$keywords
		));
		unset($data, $keywords);

		$cf_changed = false;

		// check for custom_fields that are fed by %file_info/...%
		$custom_defaults = array();
		$custom_fields = WPFB_Core::GetCustomFields(true, $custom_defaults);
		foreach ($custom_fields as $ct => $cn) {
			$fcv = property_exists($file, $ct) ? $file->$ct : '';
			if (!empty($custom_defaults[$ct]) && preg_match('/^%file_info\\/[a-zA-Z0-9_\\/]+%$/', $custom_defaults[$ct]) && ($nv = $file->get_tpl_var(trim($custom_defaults[$ct], '%'))) != $fcv) {
				$file->$ct = $nv;
				$cf_changed = true;
			}
		}
		if (WPFB_Core::$settings->pdf_extract_title && !empty($info['pdf']['extracted_title']) && $file->file_display_name != $info['pdf']['extracted_title']) {
			$file->file_display_name = $info['pdf']['extracted_title'];
			$cf_changed = true;
		}
		// TODO: move this cleanup into a callback / should NOT be HERE!
		if ($file->file_rescan_pending) {
			$file->file_rescan_pending = 0;
			$cf_changed = true;
		}

		// delete local temp file
		if ($file->IsRemote() && file_exists($file->GetLocalPath())) {
			@unlink($file->GetLocalPath());
		}
		// TODO END;

		if ($cf_changed && !$file->IsLocked())
			$file->DbSave(true);

		return $res;
	}

	static function UpdateCachedFileInfo($file) {
		$info = self::analyzeFile($file);
		if(self::StoreFileInfo($file, $info) === false)
			return false;
		return $info;
	}

	/**
	 *  gets file info out of the cache or analyzes the file if not cached
	 *  used in file edit form to display the details
	 * @global type $wpdb
	 * @param type $file
	 * @param type $get_keywords
	 * @return type
	 */
	static function GetFileInfo($file, $get_keywords = false) {
		global $wpdb;
		$sql = "SELECT value" . ($get_keywords ? ", keywords" : "") . " FROM $wpdb->wpfilebase_files_id3 WHERE file_id = " . $file->GetId();
		if ($get_keywords) {	// TODO: cache not updated if get_keywords
			$info = $wpdb->get_row($sql);
			if (!empty($info))
				$info->value = unserialize(base64_decode($info->value));
			return $info;
		}
		if (is_null($info = $wpdb->get_var($sql)))
			return self::UpdateCachedFileInfo($file);
		return ($info == '0') ? null : unserialize(base64_decode($info));
	}

	static function GetFileAnalyzeTime($file) {
		global $wpdb;
		$t = $wpdb->get_var("SELECT analyzetime FROM $wpdb->wpfilebase_files_id3 WHERE file_id = " . $file->GetId());
		if (is_null($t))
			$t = 0;
		return $t;
	}

	private static function cleanInfoByRef(&$info) {
		static $skip_keys = array('getid3_version', 'streams', 'seektable', 'streaminfo',
			 'comments_raw', 'encoding', 'flags', 'image_data', 'toc', 'lame', 'filename', 'filesize', 'md5_file',
			 'data', 'warning', 'error', 'filenamepath', 'filepath', 'popm', 'email', 'priv', 'ownerid', 'central_directory', 'raw', 'apic', 'iTXt', 'IDAT');

		foreach ($info as $key => &$val) {
			if (empty($val) || in_array(strtolower($key), $skip_keys) || strpos($key, "UndefinedTag") !== false || strpos($key, "XML") !== false) {
				unset($info[$key]);
				continue;
			}

			if (is_array($val) || is_object($val))
				self::cleanInfoByRef($info[$key]);
			else if (is_string($val)) {
				$a = ord($val{0});
				if ($a < 32 || $a > 126 || $val{0} == '?' || strpos($val, chr(01)) !== false || strpos($val, chr(0x09)) !== false) {  // check for binary data
					unset($info[$key]);
					continue;
				}
			}
		}
	}

	private static function removeLongData(&$info, $max_length) {
		foreach (array_keys($info) as $key) {
			if (is_array($info[$key]) || is_object($info[$key]))
				self::removeLongData($info[$key], $max_length);
			else if (is_string($info[$key]) && strlen($info[$key]) > $max_length)
				unset($info[$key]);
		}
	}



	private static function Unzip($filename) {
		global $wp_filesystem;
		if (empty($wp_filesystem) || !is_object($wp_filesystem)) {
			require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
			require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
			if (!defined('FS_CHMOD_DIR'))
				define('FS_CHMOD_DIR', octdec(WPFB_PERM_DIR));
			if (!defined('FS_CHMOD_FILE'))
				define('FS_CHMOD_FILE', octdec(WPFB_PERM_FILE));
			$wp_filesystem = new WP_Filesystem_Direct(null);
		}
		$dir = WPFB_Admin::GetTmpPath(basename($filename));

		require_once(ABSPATH . 'wp-admin/includes/file.php');

		if (!function_exists('unzip_file'))
			return null;

		$result = unzip_file($filename, $dir);

		if (is_wp_error($result)) {
			$wp_filesystem->delete($dir, true);
			return null;
		}

		//CODELYFE-CREATE-FUNCTION_FIX

		$cff2 = function($fn) { return substr($fn,' . strlen($dir) . '); };
		$files = array_map($cff2, list_files($dir));

		//$files = array_map(create_function('$fn', 'return substr($fn,' . strlen($dir) . ');'), list_files($dir));


		$result = array('dir' => $dir, 'files' => $files);
		return $result;
	}

	private static function DeleteDir($dir) {
		global $wp_filesystem;
		$wp_filesystem->delete($dir, true);
	}

}
