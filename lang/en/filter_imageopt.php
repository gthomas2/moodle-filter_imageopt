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
 * Image optimiser filter.
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['filtername'] = 'Image optimiser';
$string['maxwidth'] = 'Maximum image width';
$string['maxwidthdesc'] = 'Maximum image width in pixels';
$string['loadonvisible'] = 'Load when visible';
$string['loadonvisibledesc'] = 'Delay loading of images until visible';
$string['donotloadonvisible'] = 'No placeholding, load immediately';
$string['loadonvisibilityall'] = 'All images';
$string['loadonvisibilityafter'] = 'After {$a} image(s)';
$string['widthattribute'] = 'Image width attribute';
$string['widthattributedesc'] = 'How should existing image width attributes be dealt with';
$string['widthattpreserve'] = 'Preserve user width attributes in all cases';
$string['widthattpreserveltmax'] = 'Preserve user width attributes only if less than maximum width';
$string['privacy:metadata'] = 'The image optimiser filter creates optimised versions of pre-existing images. It does not store any information other than that relating to pre-existing images.';