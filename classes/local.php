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

class local {

    const REGEXP_IMGSRC = '/<img\s[^\>]*(src=["|\']((?:.*)(pluginfile.php(?:.*)))["|\'])(?:.*)>/isU';

    const REGEXP_SRC = '/(?:.*)(pluginfile.php(?:.*))/';
    /**
     * Get the optimised path for a file path.
     * @param type $filepath
     * @return type
     */
    public static function get_optimised_path($filepath) {
        $maxwidth = get_config('filter_imageopt', 'maxwidth');

        $tmparr = explode('/', $filepath);
        if ($tmparr[0] === 'pluginfile.php') {
            array_shift($tmparr);
        }

        $tmparr[count($tmparr)-1] = 'imageopt/'.$maxwidth.'/'.$tmparr[count($tmparr)-1];
        $optimisedpath = implode('/', $tmparr);
        if (substr($optimisedpath, 0, 1) !== '/') {
            $optimisedpath = '/'.$optimisedpath;
        }
        return $optimisedpath;
    }

    public static function get_img_path_from_src($src) {
        $matches = [];

        preg_match(self::REGEXP_SRC, $src, $matches);

        return $matches[1];
    }

    /**
     * Get's an image file from the plugin file path.
     *
     * @param str $pluginfilepath pluginfile.php/
     * @return \stored_file
     */
    public static function get_img_file($pluginfilepath) {
        $fs = get_file_storage();

        if (strpos($pluginfilepath, 'pluginfile.php') === 0) {
            $pluginfilepath = substr($pluginfilepath, strlen('pluginfile.php'));
        }

        $tmparr = explode('/', $pluginfilepath);

        for ($t = 4; $t < count($tmparr); $t++) {
            $tmparr[$t] = urldecode($tmparr[$t]);
        }

        // If no item id then put one in.
        if (!is_number($tmparr[4])) {
            $tmparr[4] = '0/'.$tmparr[4];
        }

        $path = implode('/', $tmparr);

        $file = $fs->get_file_by_hash(sha1($path));

        return $file;
    }
}