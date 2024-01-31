<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local class for local people (we'll have no trouble here).
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace filter_imageopt;

defined('MOODLE_INTERNAL') || die();

use core_text;
use stored_file;

/**
 * Local class for local people (we'll have no trouble here).
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local {

    /**
     * Always preserve a width attribute already in an image tag.
     */
    const WIDTH_ATT_PRESERVE = 0;

    /**
     * Only preserve a width attribute if it's less than the maximum width.
     */
    const WIDTH_ATT_PRESERVE_MAX = 1;

    /**
     * @var string REGEXP_IMGSRC The regular expression used to see if it is an image.
     */
    const REGEXP_IMGSRC = '/<img\s[^\>]*(src=["|\']((?:.*)(pluginfile\.php(?:.*)))["|\'|?])(?:.*)>/isU';

    /**
     * @var string REGEXP_SRC The regular expression used to see if pluginfile.php is in the source.
     */
    const REGEXP_SRC = '/(?:.*)(pluginfile.php(?:.*))/';

    /**
     * Get the optimised path for a file path - this is the path that get's written to the db as a hash.
     *
     * @param \stored_file $file
     * @param mixed $filepath
     * @param bool $asfilepath If true will return the path for use with the file storage system, not urls
     * @return string the optimised path
     */
    public static function get_optimised_path(\stored_file $file, $filepath, $asfilepath = true) {
        $maxwidth = get_config('filter_imageopt', 'maxwidth');
        if (empty($maxwidth)) {
            $maxwidth = 480;
        }

        if ($file->get_imageinfo()['width'] <= $maxwidth && self::file_is_public($file->get_contenthash())) {
            $maxwidth = '-1';
        }

        if (self::file_is_public($file->get_contenthash())) {
            return '/' . \context_system::instance()->id . '/filter_imageopt/public/1/imageopt/' .
                       $maxwidth . '/' . $file->get_contenthash();
        }

        $pathcomps = self::explode_img_path($filepath);
        self::url_decode_path_components($pathcomps);
        if ($asfilepath) {
            // See if we have component support for this component.
            $classname = '\\filter_imageopt\\componentsupport\\'.$file->get_component();
            if (class_exists($classname) && method_exists($classname, 'get_optimised_path')) {
                $optimisedpath = $classname::get_optimised_path($pathcomps, $maxwidth);
                if ($optimisedpath !== null) {
                    return $optimisedpath;
                }
            }
        }

        $pathcomps[count($pathcomps) - 1] = 'imageopt/'.$maxwidth.'/'.$pathcomps[count($pathcomps) - 1];
        $optimisedpath = implode('/', $pathcomps);
        if (substr($optimisedpath, 0, 1) !== '/') {
            $optimisedpath = '/'.$optimisedpath;
        }
        return $optimisedpath;
    }

    /**
     * Get optimised src.
     * @param stored_file $file
     * @param string $originalsrc
     * @param string $optimisedpath
     * @return string
     */
    public static function get_optimised_src(\stored_file $file, $originalsrc, $optimisedpath) {
        global $CFG;
        $classname = '\\filter_imageopt\\componentsupport\\'.$file->get_component();
        $optimisedsrc = null;
        if (!self::file_is_public($file->get_contenthash()) && class_exists($classname) && method_exists($classname, 'get_optimised_src')) {
            $optimisedsrc = $classname::get_optimised_src($file, $originalsrc);
        }
        if (empty($optimisedsrc)) {
            $optimisedsrc = new \moodle_url($CFG->wwwroot.'/pluginfile.php'.$optimisedpath);
        }
        $optimisedsrc = $optimisedsrc->out();
        return $optimisedsrc;
    }

    /**
     * Add url path to queue
     * @param string $path
     * @return bool|int
     * @throws \dml_exception
     */
    public static function add_url_path_to_queue($path) {
        global $DB;

        $existing = $DB->get_record_select(
            'filter_imageopt',
            $DB->sql_compare_text('urlpath', core_text::strlen($path)) . '= :path',
            ['path' => $path],
            '*',
            IGNORE_MULTIPLE // It's very unlikely but there is a possible race condition where multiple records exist.
        );
        if ($existing) {
            return $existing->id;
        }

        $data = (object) [
            'urlpath' => $path,
            'timecreated' => time()
        ];

        return $DB->insert_record('filter_imageopt', $data);
    }

    /**
     * Get url path by id
     * @param int $id
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_url_path_by_id($id) {
        global $DB;

        return $DB->get_field('filter_imageopt', 'urlpath', ['id' => $id]);
    }

    /**
     * Delete queue item by url path.
     * @param string $urlpath
     */
    public static function delete_queue_item_by_path($urlpath) {
        global $DB;

        $DB->delete_records_select(
            'filter_imageopt',
            $DB->sql_compare_text('urlpath', core_text::strlen($urlpath)) . '= :path',
            ['path' => $urlpath]
        );
    }

    /**
     * Gets an img path from image src attribute.
     * @param string $src
     * @return array
     */
    public static function get_img_path_from_src($src) {
        $matches = [];

        preg_match(self::REGEXP_SRC, $src, $matches);

        return $matches[1];
    }

    /**
     * Explode an image path.
     * @param string $pluginfilepath
     * @return array
     */
    public static function explode_img_path($pluginfilepath) {
        $tmparr = explode('/', $pluginfilepath);
        if ($tmparr[0] === 'pluginfile.php') {
            array_splice($tmparr, 0, 1);
        } else if ($tmparr[0] === '') {
            array_splice($tmparr, 0, 1);
        }
        return $tmparr;
    }

    /**
     * URL decode each component of a path.
     * @param array $pathcomponents
     */
    public static function url_decode_path_components(array &$pathcomponents) {
        array_walk($pathcomponents, function(&$item, $key) {
            $item = urldecode($item);
        });
    }

    /**
     * URL decode file path.
     * @param string $pluginfilepath
     * @return string
     */
    public static function url_decode_path($pluginfilepath) {
        $tmparr = self::explode_img_path($pluginfilepath);
        self::url_decode_path($tmparr);
        return implode('/', $tmparr);
    }

    /**
     * Get's an image file from the plugin file path.
     *
     * @param string $pluginfilepath pluginfile.php/
     * @return \stored_file
     */
    public static function get_img_file($pluginfilepath) {

        $fs = get_file_storage();

        $pathcomps = self::explode_img_path($pluginfilepath);
        self::url_decode_path_components($pathcomps);

        $component = $pathcomps[1];

        // See if we have component support for this component.
        $classname = '\\filter_imageopt\\componentsupport\\'.$component;
        if (class_exists($classname) && method_exists($classname, 'get_img_file')) {
            $file = $classname::get_img_file($pathcomps);
            if ($file instanceof stored_file) {
                return $file;
            }
        }

        // If no item id then put one in.
        if (!is_number($pathcomps[3])) {
            array_splice($pathcomps, 3, 0, [0]);
        }

        $path = '/'.implode('/', $pathcomps);

        $file = $fs->get_file_by_hash(sha1($path));

        return $file;
    }

    /**
     * This function delegates file serving to individual plugins.
     *
     * @param string $relativepath
     * @return void
     */
    public static function file_pluginfile($relativepath) {
        $forcedownload = optional_param('forcedownload', 0, PARAM_BOOL);
        $preview = optional_param('preview', null, PARAM_ALPHANUM);
        // Offline means download the file from the repository and serve it, even if it was an external link.
        // The repository may have to export the file to an offline format.
        $offline = optional_param('offline', 0, PARAM_BOOL);
        $embed = optional_param('embed', 0, PARAM_BOOL);
        file_pluginfile($relativepath, $forcedownload, $preview, $offline, $embed);
    }

    /**
     * Returns true if a file is considered public.
     *
     * @param string $contenthash
     * @return bool
     */
    public static function file_is_public(string $contenthash): bool {
        global $DB;

        $minduplicates = get_config('filter_imageopt', 'minduplicates');
        $publicfilescache = \cache::make('filter_imageopt', 'public_files');
        $key = 'public_files_' . $minduplicates;

        if (empty($minduplicates)) {
            return false;
        }

        if (!$publicfilescache->has($key)) {
            $publicfiles = $DB->get_records_sql(
                "SELECT contenthash, COUNT(contenthash)
                   FROM {files}
                  WHERE filearea <> 'draft'
                    AND (filearea <> 'public' OR component <> 'filter_imageopt')
               GROUP BY contenthash
                 HAVING COUNT(contenthash) >= :count",
                ['count' => $minduplicates]
            );

            $publicfilescache->set($key, array_column($publicfiles, 'contenthash'));
        }

        return in_array($contenthash, $publicfilescache->get($key));
    }
}
