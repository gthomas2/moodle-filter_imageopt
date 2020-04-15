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

use stored_file;

class local
{
    const REGEXP_IMGSRC = '/<img\s[^\>]*(src=["|\']((?:.*)(pluginfile.php(?:.*)))["|\'])(?:.*)>/isU';

    const REGEXP_SRC = '/(?:.*)(pluginfile.php(?:.*))/';

    /**
     * Get the optimised path for a file path - this is the path that get's written to the db as a hash.
     * @param string $filepath
     * @param bool $asfilepath - if true will return the path for use with the file storage system, not urls.
     * @return string
     */
    public static function get_optimised_path($filepath, $asfilepath = true)
    {
        $maxwidth = get_config('filter_imageopt', 'maxwidth');
        if (empty($maxwidth)) {
            $maxwidth = 480;
        }
        $pathcomps = self::explode_img_path($filepath);
        self::url_decode_path_components($pathcomps);
        // what is the significance of pathcomps being larger than 5?
        if (count($pathcomps) > 5 && $asfilepath) {
            $component = $pathcomps[1];
            // See if we have component support for this component.
            $classname = '\\filter_imageopt\\componentsupport\\' . $component;
            if (class_exists($classname) && method_exists($classname, 'get_optimised_path')) {
                $optimisedpath = $classname::get_optimised_path($pathcomps, $maxwidth);
                if ($optimisedpath !== null) {
                    return $optimisedpath;
                }
            }
        }

        $pathcomps[count($pathcomps) - 1] = 'imageopt/' . $maxwidth . '/' . $pathcomps[count($pathcomps) - 1];
        $optimisedpath                    = implode('/', $pathcomps);
        if (substr($optimisedpath, 0, 1) !== '/') {
            $optimisedpath = '/' . $optimisedpath;
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
    public static function get_optimised_src(\stored_file $file, $originalsrc, $optimisedpath)
    {
        global $CFG;
        $classname    = '\\filter_imageopt\\componentsupport\\' . $file->get_component();
        $optimisedsrc = null;
        if (class_exists($classname) && method_exists($classname, 'get_optimised_src')) {
            $optimisedsrc = $classname::get_optimised_src($file, $originalsrc);
        }
        if (empty($optimisedsrc)) {
            $optimisedsrc = new \moodle_url($CFG->wwwroot . '/pluginfile.php' . $optimisedpath);
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
    public static function add_url_path_to_queue($path)
    {
        global $DB;

        $existing = $DB->get_record('filter_imageopt', ['urlpath' => $path]);
        if ($existing) {
            return $existing->id;
        }

        $data = (object) [
            'urlpath'     => $path,
            'timecreated' => time(),
        ];

        return $DB->insert_record('filter_imageopt', $data);
    }

    /**
     * Get url path by id
     * @param int $id
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_url_path_by_id($id)
    {
        global $DB;

        return $DB->get_field('filter_imageopt', 'urlpath', ['id' => $id]);
    }

    /**
     * Delete queue item by url path.
     * @param $urlpath
     * @throws \dml_exception
     */
    public static function delete_queue_item_by_path($urlpath)
    {
        global $DB;

        $DB->delete_records('filter_imageopt', ['urlpath' => $urlpath]);
    }

    /**
     * Gets an img path from image src attribute.
     * @param type string $src
     * @return array
     */
    public static function get_img_path_from_src($src)
    {
        $matches = [];

        preg_match(self::REGEXP_SRC, $src, $matches);

        return $matches[1];
    }

    /**
     * Explode an image path.
     * @param string $pluginfilepath
     * @return array
     */
    public static function explode_img_path($pluginfilepath)
    {
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
    public static function url_decode_path_components(array &$pathcomponents)
    {
        array_walk($pathcomponents, function (&$item, $key) {
            $item = urldecode($item);
        });
    }

    /**
     * URL decode file path.
     * @param string $pluginfilepath
     * @return string
     */
    public static function url_decode_path($pluginfilepath)
    {
        $tmparr = self::explode_img_path($pluginfilepath);
        self::url_decode_path($tmparr);
        return implode('/', $tmparr);
    }

    /**
     * Get's an image file from the plugin file path.
     *
     * @param str $pluginfilepath pluginfile.php/
     * @return \stored_file
     */
    public static function get_img_file($pluginfilepath)
    {
        $fs = get_file_storage();

        $pathcomps = self::explode_img_path($pluginfilepath);
        self::url_decode_path_components($pathcomps);
        // why is this > 5?
        if (count($pathcomps) > 5) {
            $component = $pathcomps[1];
            // See if we have component support for this component.
            $classname = '\\filter_imageopt\\componentsupport\\' . $component;
            if (class_exists($classname) && method_exists($classname, 'get_img_file')) {
                $file = $classname::get_img_file($pathcomps);
                if ($file instanceof stored_file) {
                    return $file;
                }
            }
        }

        // If no item id then put one in.
        if (!is_number($pathcomps[3])) {
            array_splice($pathcomps, 3, 0, [0]);
        }

        // if mod_page, force the item id to 0. Why? Who knows!
        if ($pathcomps[1] == 'mod_page') {
            $pathcomps[3] = 0;
        }

        $path = '/' . implode('/', $pathcomps);

        // remove query string if there is one as that borks the hash, mod_page seems to add one for example
        $path = preg_replace('/\?.*/', '', $path);

        $file = $fs->get_file_by_hash(sha1($path));
        return $file;
    }

    public static function file_pluginfile($relativepath)
    {
        $forcedownload = optional_param('forcedownload', 0, PARAM_BOOL);
        $preview       = optional_param('preview', null, PARAM_ALPHANUM);
        // Offline means download the file from the repository and serve it, even if it was an external link.
        // The repository may have to export the file to an offline format.
        $offline = optional_param('offline', 0, PARAM_BOOL);
        $embed   = optional_param('embed', 0, PARAM_BOOL);
        file_pluginfile($relativepath, $forcedownload, $preview, $offline, $embed);
    }
}