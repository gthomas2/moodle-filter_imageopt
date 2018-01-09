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
 * Image optimiser settings.
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// You can never trust autoloading in settings.php!
require_once(__DIR__.'/classes/image.php');

use filter_imageopt\image;

if ($ADMIN->fulltree) {
    $choices = [
        480 => '480',
        800 => '800',
        1024 => '1024',
        2048 => '2048'
    ];
    $settings->add(new admin_setting_configselect('filter_imageopt/maxwidth', get_string('maxwidth', 'filter_imageopt'),
        get_string('maxwidthdesc', 'filter_imageopt'), 480, $choices));

    $choices = [
        0 => get_string('loadonvisibilityall', 'filter_imageopt')
    ];
    for ($c = 0; $c < 10; $c++) {
        $choices[$c + 1] = get_string('loadonvisibilityafter', 'filter_imageopt', $c + 1);
    }
    $choices[999] = get_string('donotloadonvisible', 'filter_imageopt');
    $settings->add(new admin_setting_configselect('filter_imageopt/loadonvisible', get_string('loadonvisible', 'filter_imageopt'),
        get_string('loadonvisibledesc', 'filter_imageopt'), 5, $choices));

    $choices = [
        image::WIDTHATTPRSERVELTMAX => get_string('widthattpreserveltmax', 'filter_imageopt'),
        image::WIDTHATTPRESERVE => get_string('widthattpreserve', 'filter_imageopt')
    ];
    $settings->add(new admin_setting_configselect('filter_imageopt/widthattribute',
        get_string('widthattribute', 'filter_imageopt'),
        get_string('widthattributedesc', 'filter_imageopt'), image::WIDTHATTPRSERVELTMAX, $choices));
}
