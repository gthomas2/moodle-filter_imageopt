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
 * File containing the base component class.
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_imageopt\componentsupport;

defined('MOODLE_INTERNAL') || die;

/**
 * The base component class.
 *
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_component {

    /**
     * Get the image file for the specified file path components.
     * @param array $pathcomponents - path split by /.
     * @return \stored_file|null
     */
    abstract public static function get_img_file(array $pathcomponents);

    /**
     * Get the optimised path for specified file path.
     * @param array $pathcomponents - path split by /.
     * @param int $maxwidth
     * @return string|null
     */
    abstract public static function get_optimised_path(array $pathcomponents, $maxwidth);

    /**
     * Return the optimised url for the specfied file and original src.
     * @param \stored_file $file
     * @param string $originalsrc
     * @return \moodle_url
     */
    abstract public static function get_optimised_src(\stored_file $file, $originalsrc);
}
