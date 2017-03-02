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
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use filter_imageopt\image;

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
    if (count($args) < 2) {
        throw new coding_exception('Bad image url, args should contain item id and original component');
    }

    $item = $args[0];
    $component = $args[1];
    $maxwidth = $args[2];
    $filename = $args[3];
    $pathinfo = pathinfo($filename);

    $resizename = $pathinfo['filename'].'_opt_'.$maxwidth.'.'.$pathinfo['extension'];

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, $component, $filearea, $item, '/', $filename);
    $originalts = $file->get_timemodified();


    $imageinfo = (object) $file->get_imageinfo();
    if ($imageinfo->width <= $maxwidth) {
        send_stored_file($file, null, 0, false);
        return true;
    }

    $resizedfile = $fs->get_file($context->id, $component, $filearea, $item, '/', $resizename);
    // Make sure resized file is fresh.
    if ($resizedfile && ($resizedfile->get_timemodified() < $originalts)) {
        $resizedfile->delete();
        $resizedfile = false;
    }
    if (!$resizedfile) {
        $resizedfile = image::resize($file, $resizename, $maxwidth);
    }

    if (!$resizedfile) {
        send_stored_file($file, null, 0, false);
        return true;
    } else {
        send_stored_file($resizedfile, null, 0, false);
        return true;
    }
}
