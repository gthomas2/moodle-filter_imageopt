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
 * Image optimiser support for page component.
 * @package filter_imageopt
 * @author    Guy Thomas <dev@citri.city>
 * @copyright Copyright (c) 2020 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace filter_imageopt\componentsupport;

defined('MOODLE_INTERNAL') || die;

use filter_imageopt\local;

class mod_page extends base_component {

    private static function check_component(array $pathcomponents) {
        if ($pathcomponents[1] !== 'mod_page') {
            throw new \coding_exception('Component is not a page ('.$pathcomponents[2].')');
        }
    }

    public static function get_img_file(array $pathcomponents) {
        self::check_component($pathcomponents);

        $filearea = $pathcomponents[2];
        if ($filearea !== 'content') {
            // We don't do any special processing for page mod unless the file area
            // is content.
            return null;
        }

        $pathcomponents[3] = 0;
        $path = '/'.implode('/', $pathcomponents);
        $fs = get_file_storage();
        $file = $fs->get_file_by_hash(sha1($path));

        return $file;
    }

    public static function get_optimised_path(array $pathcomponents, $maxwidth) {
        self::check_component($pathcomponents);

        $filearea = $pathcomponents[2];
        if ($filearea !== 'content') {
            // We don't do any special processing for page mod unless the file area
            // is content.
            return null;
        }

        $pathcomponents[3] = 0;

        $pathcomponents[count($pathcomponents) - 1] = 'imageopt/'.$maxwidth.'/'.$pathcomponents[count($pathcomponents) - 1];
        $optimisedpath = implode('/', $pathcomponents);
        if (substr($optimisedpath, 0, 1) !== '/') {
            $optimisedpath = '/'.$optimisedpath;
        }
        return $optimisedpath;
    }

    public static function get_optimised_src(\stored_file $file, $originalsrc) {
        global $CFG;

        $maxwidth = get_config('filter_imageopt', 'maxwidth');

        $urlpath = local::get_img_path_from_src($originalsrc);
        $urlpathcomponents = local::explode_img_path($urlpath);

        $filearea = $urlpathcomponents[2];
        if ($filearea !== 'content') {
            // We don't do any special processing for page mod unless the file area
            // is content.
            return null;
        }

        $urlpathcomponents[3] = 0;

        array_splice($urlpathcomponents, 4, 0, ['imageopt', $maxwidth]);

        $opturl = new \moodle_url($CFG->wwwroot.'/pluginfile.php/'.implode('/', $urlpathcomponents));

        return $opturl;
    }
}
