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
 * Main library for image optimiser filter.
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use filter_imageopt\image;
use filter_imageopt\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Serves any files associated with the image optimiser filter
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function filter_imageopt_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG;

    $urlpathid = clean_param($args[0], PARAM_INT);
    $originalimgpath = local::get_url_path_by_id($urlpathid);
    $originalfile = local::get_img_file($originalimgpath);
    $optimisedpath = local::get_optimised_path($originalimgpath);
    $optimisedurlpath = local::get_optimised_path($originalimgpath, false);

    $fs = get_file_storage();

    $optimisedfile = local::get_img_file($optimisedpath);

    if ($optimisedfile) {
        local::file_pluginfile($optimisedurlpath);
        die;
    }

    $regex = '/imageopt\/(\d*)/';
    $matches = [];
    preg_match($regex, $optimisedpath, $matches);
    $maxwidth = ($matches[1]);
    $item = $originalfile->get_itemid();
    $component = $originalfile->get_component();
    $filename = $originalfile->get_filename();
    $filearea = $originalfile->get_filearea();
    $pathinfo = pathinfo($filename);

    $originalts = $originalfile->get_timemodified();

    $imageinfo = (object) $originalfile->get_imageinfo();
    if ($imageinfo->width <= $maxwidth) {
        local::file_pluginfile(local::url_decode_path($originalimgpath));
        die;
    }

    // Make sure resized file is fresh.
    if ($optimisedfile && ($optimisedfile->get_timemodified() < $originalts)) {
        $optimisedfile->delete();
        $optimisedfile = false;
    }

    if (!$optimisedfile) {

        $pathcomps = local::explode_img_path($optimisedpath);
        local::url_decode_path_components($pathcomps);

        $imageoptpos = array_search('imageopt', $pathcomps, true);
        if ($imageoptpos === false) {
            local::file_pluginfile(local::url_decode_path($originalimgpath));
            die;
        }

        $filepos = array_search($filename, $pathcomps, true);
        $length = $filepos - $imageoptpos;

        $optimiseddirpath = '/'.implode('/', array_slice($pathcomps, $imageoptpos, $length)).'/';

        $optimisedfile = image::resize($originalfile, $optimiseddirpath, $filename, $maxwidth);

    }

    if (!$optimisedfile) {
        local::file_pluginfile(local::url_decode_path($originalimgpath));
        die;
    }

    local::delete_queue_item_by_path($originalimgpath); // Delete the queue record.
    local::file_pluginfile($optimisedurlpath);
    die;
}
