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

    $fs = get_file_storage();

    if ($filearea == 'public') {
        $path = '/' . context_system::instance()->id . '/filter_imageopt/public/1/imageopt/' . $args[2] . '/' . $args[3];
        if (!$file = $fs->get_file_by_hash(sha1($path))) {
            return false;
        }

        if (!local::file_is_public($file)) {
            return false;
        }

        send_stored_file($file, YEARSECS, 0, false, [
            'cacheability' => 'public',
            'immutable' => true,
        ]);
    }

    cache_helper::purge_by_definition('filter_imageopt', 'public_files');
    $urlpathid = clean_param($args[0], PARAM_INT);
    $originalimgpath = local::get_url_path_by_id($urlpathid);
    $originalfile = local::get_img_file($originalimgpath);
    $optimisedurlpath = local::get_optimised_path($originalfile, $originalimgpath, false);
    $optimisedpath = local::get_optimised_path($originalfile, $originalimgpath);
    $fileispublic = local::file_is_public($originalfile);
    $optimisedfile = local::get_img_file($optimisedpath);

    if ($optimisedfile) {
        local::file_pluginfile($optimisedurlpath);
        die;
    }

    $regex = '/\/imageopt\/(-1|\d*)/';
    $matches = [];
    preg_match($regex, $optimisedpath, $matches);
    $maxwidth = ($matches[1]);
    $filename = $fileispublic ? $originalfile->get_contenthash() : $originalfile->get_filename();

    $originalts = $originalfile->get_timemodified();

    $imageinfo = (object) $originalfile->get_imageinfo();
    if ($imageinfo->width <= $maxwidth && !$fileispublic) {
        local::file_pluginfile(local::url_decode_path($originalimgpath));
        die;
    }

    $pathcomps = local::explode_img_path($optimisedpath);
    local::url_decode_path_components($pathcomps);

    $imageoptpos = array_search('imageopt', $pathcomps, true);
    if ($imageoptpos === false) {
        local::file_pluginfile(local::url_decode_path($originalimgpath));
        die;
    }

    $filepos = array_search($filename, $pathcomps, true);
    $length = $filepos - $imageoptpos;

    $optimiseddirpath = '/' . implode('/', array_slice($pathcomps, $imageoptpos, $length)) . '/';

    $filerecord = [
        'filename' => $filename,
        'contextid' => $fileispublic ? \context_system::instance()->id : $originalfile->get_contextid(),
        'component' => $fileispublic ? 'filter_imageopt' : $originalfile->get_component(),
        'filearea' => $fileispublic ? 'public' : $originalfile->get_filearea(),
        'itemid' => $fileispublic ? 1 : $originalfile->get_itemid(),
        'filepath' => $optimiseddirpath
    ];

    if ($maxwidth == '-1') {
        $new = new stdClass;
        $new->contextid = context_system::instance()->id;
        $new->component = 'filter_imageopt';
        $new->filearea = 'public';
        $new->filepath = $optimiseddirpath;
        $new->filename = $filename;
        $new->itemid = 1;
        $optimisedfile = $fs->create_file_from_storedfile($new, $originalfile);
    } else {
        $optimisedfile = $fs->convert_image($filerecord, $originalfile, $maxwidth);
    }

    if (!$optimisedfile) {
        local::file_pluginfile(local::url_decode_path($originalimgpath));
        die;
    }

    local::delete_queue_item_by_path($originalimgpath); // Delete the queue record.
    local::file_pluginfile($optimisedurlpath);
    die;
}
