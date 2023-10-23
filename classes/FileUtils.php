<?php

require_once(ABSPATH . 'wp-admin/includes/file.php');

class WPFB_FileUtils
{

    static function GetFileSize($file)
    {
        $fsize = filesize($file);

        // If the result is negative...
        if ($fsize < 0 || $fsize > 1200000000) { // cannot rely on big file sizes, or negative!!
            // If the platform is Windows...
            if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
                // Try using the NT substition modifier %~z
                $size = trim(exec("for %F in (" . escapeshellarg($file) . ") do @echo %~zF"));

                // If the return is blank, zero, or not a number
                if (!$size || !ctype_digit($size)) {
                    // Use the Windows COM interface
                    $fsobj = new COM('Scripting.FileSystemObject');
                    if (dirname($file) == '.')
                        $file = ((substr(getcwd(), -1) == DIRECTORY_SEPARATOR) ? getcwd() . basename($file) : getcwd() . DIRECTORY_SEPARATOR . basename($file));
                    if ($fsobj) {
                        $f = $fsobj->GetFile($file);
                        return $f->Size;
                    }
                }
                // Otherwise, return the result of the 'for' command
                if (is_numeric($size) && $size > 0)
                    return $size;
            } else {
                // If the platform is not Windows, use the stat command (should work for *nix and MacOS)
                $size = trim(`stat -c%s "{$file}"`);
                if (is_numeric($size) && $size > 0)
                    return $size;
            }
        }

        // Otherwise, return the result of the filesize() call
        return $fsize;
    }

    static function CreateThumbnail($src_img, $max_size)
    {
        $ext = trim(strtolower(strrchr($src_img, '.')), '.');

        $extras_dir = WPFB_PLUGIN_ROOT . 'extras/';
        $tmp_img = $src_img . '_thumb.jpg';
        $tmp_del = true;

        switch ($ext) {
            case 'bmp':
                if (class_exists('Imagick')) {
                    $image = new Imagick($src_img);
                    $image->setImageFormat('jpeg');
                    $image->writeImage($tmp_img);
                } elseif (@file_exists($extras_dir . 'phpthumb.functions.php') && @file_exists($extras_dir . 'phpthumb.bmp.php')) {
                    @include_once($extras_dir . 'phpthumb.functions.php');
                    @include_once($extras_dir . 'phpthumb.bmp.php');

                    if (class_exists('phpthumb_functions') && class_exists('phpthumb_bmp')) {
                        $phpthumb_bmp = new phpthumb_bmp();

                        $im = $phpthumb_bmp->phpthumb_bmpfile2gd($src_img);
                        if ($im)
                            @imagejpeg($im, $tmp_img, 100);
                        else
                            return false;
                    }
                }
                break;

            case 'pdf':
                require_once($extras_dir . 'pdf-utils.php');

                if (empty(WPFB_Core::$settings->ghostscript_path) || !pdf_thumb(WPFB_Core::$settings->ghostscript_path, $src_img, $tmp_img)) {
                    if (!empty(WPFB_Core::$settings->ghostscript_path))
                        WPFB_Core::LogMsg("error: pdf_thumb(" . WPFB_Core::$settings->ghostscript_path . ", {$src_img}, {$tmp_img}) failed!");

                    // Imagick Bug: fails if filename includes utf8 chars?!?
                    $tmp_img = dirname($tmp_img) . '/' . urlencode(str_replace(' ', '_', basename($tmp_img))) . '_thumb.jpg';
                    if (!pdf_thumb_imagick($src_img, $tmp_img)) {
                        WPFB_Core::LogMsg("error: pdf_thumb(" . WPFB_Core::$settings->ghostscript_path . ", {$src_img}, {$tmp_img}) failed!");
                        return false;
                    }

                }
                break;
            case 'tiff':
            case 'tif':
            case 'psd':
            case 'cr2': // RAW

                if (class_exists('Imagick')) {
                    $image = new Imagick($src_img);
                    if ($ext === 'psd')
                        $image->setIteratorIndex(0);
                    $image->setImageFormat('jpeg');
                    $image->writeImage($tmp_img);
                } else {
                    @exec("convert " . escapeshellarg($src_img) . " " . escapeshellarg($tmp_img));
                    if (!is_file($tmp_img))
                        return false;
                }
                break;
            default:
                $tmp_img = $src_img;
                $tmp_del = false;
                break;
        }

        $tmp_size = array();
        if (!@file_exists($tmp_img) || @filesize($tmp_img) == 0 || !WPFB_FileUtils::IsValidImage($tmp_img, $tmp_size)) {
            return $tmp_del && is_file($tmp_img) && @unlink($tmp_img) && false;
        }

        if (!function_exists('wp_get_image_editor') && !(include_once ABSPATH . 'wp-includes/media.php') && !function_exists('wp_get_image_editor')) {
            return $tmp_del && is_file($tmp_img) && @unlink($tmp_img) && false;
        }

        // load image
        $editor = wp_get_image_editor($tmp_img);
        if (is_wp_error($editor)) {
            return $tmp_del && is_file($tmp_img) && @unlink($tmp_img) && false;
        }


        // "trim" image whitespaces
        $boundary = self::GetImageBoundary($tmp_img);
        if (array_sum($boundary) > 0 && is_wp_error($editor->crop($boundary[0], $boundary[1], $boundary[2], $boundary[3]))) {
            return $tmp_del && is_file($tmp_img) && @unlink($tmp_img) && false;
        }

        // resize to max thumb size
        if (is_wp_error($editor->resize($max_size, $max_size))) {
            // if it fails to resize, image might be smaller than $max_size -> just copy (we already checked that it is a valid image)
            // otherwise we would return:
            //return $tmp_del && is_file($tmp_img) && @unlink($tmp_img) && false;
        }

        // save
        $thumb = $editor->save();
        $dir = dirname($src_img) . '/';

        // error occurs when image is smaller than thumb_size. in this case, just copy original
        if (is_wp_error($thumb) && !empty($tmp_size) && max($tmp_size) <= $max_size) {
            $name = wp_basename($src_img, ".$ext");
            $new_thumb = "{$name}-{$tmp_size[0]}x{$tmp_size[1]}" . strtolower(strrchr($tmp_img, '.'));
            if ($tmp_del)
                rename($tmp_img, $dir . $new_thumb);
            else
                copy($tmp_img, $dir . $new_thumb);

            $thumb = array('file' => $new_thumb);
        }

        $tmp_del && is_file($tmp_img) && unlink($tmp_img);

        if (!$thumb || is_wp_error($thumb))
            return false;

        $fn = $dir . str_ireplace(array('.pdf_thumb', '.jpg_thumb', '.tiff_thumb', '.tif_thumb', '.bmp_thumb'), '', $thumb['file']);

        // make sure we have a thumb file name like `._[KK..K].thumb.(jpg|png)$`
        $thumb_suffix = '.thumb';
        $lts = strlen($thumb_suffix);
        $p = strrpos($fn, '.');
        if ($p <= $lts || strcmp($thumb_suffix, substr($fn, $p - $lts, $lts)) != 0) {
            // add token to make thumbnail url non-guessable
            $token = '._' . wp_generate_password(12, false, false);
            $fn = substr($fn, 0, $p) . $token . $thumb_suffix . substr($fn, $p);
        }

        rename($dir . $thumb['file'], $fn);

        return $fn;
    }

    static function IsValidImage($img, &$img_size = null)
    {
        $fs = WPFB_FileUtils::GetFileSize($img);
        if ($fs < 50 || $fs > 20000000)
            return false; // skip big files to prevent hight mem usage

        $s = @getimagesize($img);
        if ($s !== false)
            $img_size = $s;
        return $s !== false;
    }

    static function FileHasImageExt($name)
    {
        $name = strtolower(substr($name, strrpos($name, '.') + 1));
        return ($name == 'png' || $name == 'gif' || $name == 'jpg' || $name == 'jpeg' || $name == 'bmp' || $name == 'tif' || $name == 'tiff' || $name == 'psd' || $name == 'cr2');
    }

// copy of wp's copy_dir, but moves everything
    static function MoveDir($from, $to)
    {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');

        wpfb_call('Admin', 'DisableTimeouts');
        $wp_filesystem = new WP_Filesystem_Direct(null);

        $dirlist = $wp_filesystem->dirlist($from);

        $from = trailingslashit($from);
        $to = trailingslashit($to);

        foreach ((array)$dirlist as $filename => $fileinfo) {
            if ('f' == $fileinfo['type']) {
                if (!$wp_filesystem->move($from . $filename, $to . $filename, true))
                    return false;
                $wp_filesystem->chmod($to . $filename, octdec(WPFB_PERM_FILE));
            } elseif ('d' == $fileinfo['type']) {
                if (!$wp_filesystem->mkdir($to . $filename, octdec(WPFB_PERM_DIR)))
                    return false;
                if (!self::MoveDir($from . $filename, $to . $filename))
                    return false;
            }
        }

        // finally delete the from dir
        @rmdir($from);

        return true;
    }

    static function GetImageBoundary($img_in)
    {

        $img = is_string($img_in) ? @imagecreatefromstring(file_get_contents($img_in)) : $img_in;

        if (!$img)
            return false;

//find the size of the borders
        $b_top = 0;
        $b_btm = 0;
        $b_lft = 0;
        $b_rt = 0;

//top
        for (; $b_top < imagesy($img); ++$b_top) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, $b_top) != 0xFFFFFF) {
                    break 2; //out of the 'top' loop
                }
            }
        }

//bottom
        for (; $b_btm < imagesy($img); ++$b_btm) {
            for ($x = 0; $x < imagesx($img); ++$x) {
                if (imagecolorat($img, $x, imagesy($img) - $b_btm - 1) != 0xFFFFFF) {
                    break 2; //out of the 'bottom' loop
                }
            }
        }

//left
        for (; $b_lft < imagesx($img); ++$b_lft) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, $b_lft, $y) != 0xFFFFFF) {
                    break 2; //out of the 'left' loop
                }
            }
        }

//right
        for (; $b_rt < imagesx($img); ++$b_rt) {
            for ($y = 0; $y < imagesy($img); ++$y) {
                if (imagecolorat($img, imagesx($img) - $b_rt - 1, $y) != 0xFFFFFF) {
                    break 2; //out of the 'right' loop
                }
            }
        }

//copy the contents, excluding the border		
        $res = array(
            $b_lft, $b_top, imagesx($img) - $b_lft - $b_rt, imagesy($img) - $b_top - $b_btm
        );

        if(is_string($img_in)) imagedestroy($img);

        return $res;
    }

    static function DeleteOldFiles($path, $min_age = 86400)
    {
        $path = trailingslashit($path);

        if (!is_dir($path))
            return 0;

        $d = 0;
        $t = time() - $min_age;

        $files = list_files($path);
        foreach ($files as $file) {
            if (max(filemtime($file), filectime($file)) < $t) {
                unlink($file);
                $d++;
            }
        }

        return $d;
    }

}
