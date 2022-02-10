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
 * Tests for local class
 *
 * @package   filter_imageopt
 * @copyright 2022 Catalyst IT Australia Pty Ltd
 * @author    Cameron Ball <cameron@cameron1729.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_imageopt;

defined('MOODLE_INTERNAL') || die();

use context;
use stored_file;
use context_helper;
use context_module;
use filter_imageopt;
use advanced_testcase;

global $CFG;

require_once($CFG->dirroot . '/files/externallib.php');
require_once(__DIR__.'/../filter.php');

/**
 * Tests for local class
 *
 * @package   filter_imageopt
 * @copyright 2022 Catalyst IT Australia Pty Lrd
 * @author    Cameron Ball <cameron@cameron1729.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \filter_imageopt\local
 */
class local_test extends advanced_testcase {

    /**
     * Create moodle file.
     * @param context $context
     * @param string $fixturefile name of fixture file
     * @return stored_file
     */
    private function std_file_record(context $context, $fixturefile) {
        global $CFG;

        $component = 'mod_label';
        $filearea = 'intro';

        $filerecord = [
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $fixturefile
        ];

        $fs = \get_file_storage();
        $file = $fs->create_file_from_pathname($filerecord, $CFG->dirroot.'/filter/imageopt/tests/fixtures/'.$fixturefile);

        return $file;
    }

    /**
     * Return image file in label and return label text + regex matches.
     * @param string $fixturefile The name of the file.
     * @return [string, array, stored_file]
     */
    private function create_image_file_text($fixturefile) {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $plugin = $dg->get_plugin_generator('mod_label');

        $intro = '<p><img src="@@PLUGINFILE@@/'.$fixturefile.'" alt="" role="presentation"><br></p>';
        $label = $plugin->create_instance((object) ['course' => $course->id, 'intro' => $intro]);
        $context = context_module::instance($label->cmid);

        $file = $this->std_file_record($context, $fixturefile);

        // Test filter plugin img, lazy load.
        $labeltxt = file_rewrite_pluginfile_urls($label->intro, 'pluginfile.php', $context->id,
                $file->get_component(), $file->get_filearea(), 0);
        $matches = [];
        $regex = local::REGEXP_IMGSRC;
        preg_match($regex, $labeltxt, $matches);
        return [$labeltxt, $matches, $file];
    }

    /**
     * Test main filter function.
     *
     * @covers ::file_is_public
     */
    public function test_file_is_public() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fixturefile = 'testpng_2880x1800.png';

        // Test duplicate detection.
        list($labeltxt1, $matches1, $file1) = $this->create_image_file_text($fixturefile);
        list($labeltxt2, $matches2, $file2) = $this->create_image_file_text($fixturefile);
        $context1 = context_helper::instance_by_id($file1->get_contextid());
        $context2 = context_helper::instance_by_id($file2->get_contextid());
        $filter1 = new filter_imageopt($context1, []);
        $filter2 = new filter_imageopt($context2, []);
        $filtered1 = $filter1->filter($labeltxt1);

        // Initial configuration, nothing should be detected as a duplicate.
        $this->assertFalse(local::file_is_public(($file1)));
        $this->assertFalse(local::file_is_public(($file2)));

        // Min duplicates exceeds 2, nothing should be detected as a duplicate.
        set_config('minduplicates', '3', 'filter_imageopt');
        $this->assertFalse(local::file_is_public(($file1)));
        $this->assertFalse(local::file_is_public(($file2)));

        // Min duplicates is equal to 2, both duplicates should be detected.
        set_config('minduplicates', '2', 'filter_imageopt');
        $this->assertTrue(local::file_is_public(($file1)));
        $this->assertTrue(local::file_is_public(($file2)));

        // Min duplicates is less than 2, both duplicates should be detected.
        set_config('minduplicates', '2', 'filter_imageopt');
        $this->assertTrue(local::file_is_public(($file1)));
        $this->assertTrue(local::file_is_public(($file2)));

        // Min duplicates is  0, nothing should be detected as a duplicate.
        set_config('minduplicates', '0', 'filter_imageopt');
        $this->assertFalse(local::file_is_public(($file1)));
        $this->assertFalse(local::file_is_public(($file2)));
    }
}
