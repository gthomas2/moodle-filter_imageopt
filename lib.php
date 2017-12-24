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

    $src = base64_decode(clean_param($args[0], PARAM_ALPHANUMEXT)); // PARAM_BASE64 did not work for me.

    $imgpath = local::get_img_path_from_src($src);
    $optimisedpath = local::get_optimised_path($imgpath);
    $optimisedurl = new moodle_url($CFG->wwwroot.str_replace('//' ,'/', '/pluginfile.php/'.$optimisedpath));

    $fs = get_file_storage();
    $optimisedfile = local::get_img_file($optimisedpath);

    if ($optimisedfile) {
        redirect($optimisedurl);
        die;
    }

    $originalfile = local::get_img_file($imgpath);

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
        redirect($src);
        die;
    }

    // Make sure resized file is fresh.
    if ($optimisedfile && ($optimisedfile->get_timemodified() < $originalts)) {
        $optimisedfile->delete();
        $optimisedfile = false;
    }
    if (!$optimisedfile) {
        $spos = strripos($optimisedpath, '/'.$filename);
        $optimisedpathonly = substr($optimisedpath, 0, $spos);
        $spos = stripos($optimisedpathonly, '/'.$context->id.'/'.$component.'/'.$filearea);
        $spos += strlen('/'.$context->id.'/'.$component.'/'.$filearea);
        $optimisedpathonly = substr($optimisedpathonly, $spos);
        $optimisedfile = image::resize($originalfile, $optimisedpathonly, $filename, $maxwidth);
    }

    if (!$optimisedfile) {
        redirect($src);
        die;
    } else {
        redirect($optimisedurl);
        die;
    }
}
