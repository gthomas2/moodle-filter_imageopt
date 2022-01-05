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
 * Image optimiser support for question component.
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2018 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace filter_imageopt\componentsupport;

defined('MOODLE_INTERNAL') || die;

use filter_imageopt\local;

/**
 * Image optimiser support for question component.
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2018 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends base_component {

    /**
     * Get the image file for the specified file path components.
     *
     * @param array $pathcomponents - path split by /.
     * @return \stored_file|null
     */
    public static function get_img_file(array $pathcomponents) {
        if (count($pathcomponents) <= 5) {
            // Return null so that local lib uses default.
            // Unless the number of path components is greater than 5
            // then we don't need to do anything special for question files.
            return null;
        }
        if ($pathcomponents[1] !== 'question') {
            throw new \coding_exception('Component is not a question ('.$pathcomponents[2].')');
        }
        if (count($pathcomponents) === 7) {
            array_splice($pathcomponents, 3, 2);
        } else {
            return null;
        }

        $path = '/'.implode('/', $pathcomponents);

        $fs = get_file_storage();
        return $fs->get_file_by_hash(sha1($path));
    }

    /**
     * Get the optimised path for specified file path.
     *
     * @param array $pathcomponents - path split by /.
     * @param int $maxwidth
     * @return string|null
     */
    public static function get_optimised_path(array $pathcomponents, $maxwidth) {
        if (count($pathcomponents) <= 5) {
            // Return null so that local lib uses default.
            // Unless the number of path components is greater than 5
            // then we don't need to do anything special for question files.
            return null;
        }
        if ($pathcomponents[1] !== 'question') {
            throw new \coding_exception('Component is not a question ('.$pathcomponents[2].')');
        }
        if (count($pathcomponents) === 7) {
            array_splice($pathcomponents, 3, 2);
        } else {
            return null;
        }
        $pathcomponents[count($pathcomponents) - 1] = 'imageopt/'.$maxwidth.'/'.$pathcomponents[count($pathcomponents) - 1];
        $optimisedpath = implode('/', $pathcomponents);
        if (substr($optimisedpath, 0, 1) !== '/') {
            $optimisedpath = '/'.$optimisedpath;
        }
        return $optimisedpath;
    }

    /**
     * Return the optimised url for the specfied file and original src.
     *
     * @param \stored_file $file
     * @param string $originalsrc
     * @return \moodle_url
     */
    public static function get_optimised_src(\stored_file $file, $originalsrc) {
        global $CFG;

        $maxwidth = get_config('filter_imageopt', 'maxwidth');

        $urlpath = local::get_img_path_from_src($originalsrc);
        $urlpathcomponents = local::explode_img_path($urlpath);

        array_splice($urlpathcomponents, 6, 0, ['imageopt', $maxwidth]);

        $opturl = new \moodle_url($CFG->wwwroot.'/pluginfile.php/'.implode('/', $urlpathcomponents));

        return $opturl;
    }
}
