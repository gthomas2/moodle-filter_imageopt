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
 * Imageopt filter context
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Imageopt filter context
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) 2017 Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_filter_imageopt extends behat_base {
    /**
     * @Given /^the image optimiser filter is enabled$/
     */
    public function the_imageopt_filter_is_enabled() {
        filter_set_global_state('imageopt', TEXTFILTER_ON);
    }

    /**
     * This function is copied from filter_ally, copyright Blackboard Inc 2017.
     * Get current course;
     * @return stdClass | false
     * @throws \Behat\Mink\Exception\ExpectationException
     * @throws coding_exception
     */
    protected function get_current_course() {
        global $DB;

        $bodynode = $this->find('xpath', 'body');
        $bodyclass = $bodynode->getAttribute('class');
        $matches = [];
        if (preg_match('/(?<=^course-|\scourse-)(?:\d*)/', $bodyclass, $matches) && !empty($matches)) {
            $courseid = intval($matches[0]);
        } else {
            $courseid = SITEID;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new coding_exception('Failed to get course by id '.$courseid. ' '.$bodyclass);
        }
        return ($course);
    }

    /**
     * @Given /^the image "(?P<imgfile_string>[^"]*)" has been optimised$/
     * @param string $imgfile
     */
    public function image_optimised($imgfile) {
        $this->ensure_element_exists('//img[contains(@data-originalsrc, "'.$imgfile.'")]', 'xpath_element');
    }

    /**
     * @Given /^the image "(?P<imgfile_string>[^"]*)" has not been optimised$/
     * @param string $imgfile
     */
    public function image_not_optimised($imgfile) {
        $this->ensure_element_does_not_exist('//img[contains(@data-originalsrc, "'.$imgfile.'")]', 'xpath_element');
    }

    /**
     * @Given /^I directly open the image "(?P<imgfile_string>[^"]*)" in the test label in course "(?P<shortname_string>[^"]*)"$/
     * @param string $imgfile
     * @param string $courseshortname
     * @throws ExpectationException
     */
    public function open_label_image_directly($imgfile, $courseshortname) {
        global $DB, $CFG;

        $course = $DB->get_record('course', ['shortname' => $courseshortname]);

        $rs = $DB->get_records('label');

        $row = $DB->get_record('label', ['name' => 'test label', 'course' => $course->id]);
        $text = $row->intro;

        list($course, $cm) = get_course_and_cm_from_instance($row->id, 'label');
        $context = $cm->context;

        $text = file_rewrite_pluginfile_urls($text, 'pluginfile.php', $context->id, 'mod_label', 'intro', 0);
        $text = format_text($text, FORMAT_HTML);

        $regex = '/<img(?:.*)(src="((?:.*)\/'.preg_quote($imgfile).')")/';
        $matches = [];
        preg_match_all($regex, $text, $matches);

        if (!isset($matches[2][0])) {
            throw new ExpectationException('Failed to find image '.$imgfile.' in test label', $this->getSession());
        }

        $src = $matches[2][0];
        // TODO - bodge fix, don't know why but we are getting back a URL that doesn't work with labels because it shouldn't have
        // a 0 item in path. This is only happening in this behat step for some reason (works fine in real use).
        $src = str_replace('/0/', '/', $src);

        $this->getSession()->visit($this->locate_path($src));
    }

    /**
     * This function is copied from filter_ally, copyright Blackboard Inc 2017.
     * @Given /^I create a label resource with fixture images "(?P<images_string>[^"]*)"$/
     * @param string $images (csv)
     */
    public function i_create_label_with_sample_images($images) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot.'/filter/imageopt/tests/fixtures/';
        $images = explode(',', $images);

        $labeltext = '<h2>A test label</h2>';

        $voidtype = '/>';

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML
        ];

        $label = $gen->create_module('label', $data);

        $i = 0;
        foreach ($images as $image) {
            $image = trim($image);
            $i ++;
            // Alternate the way the image tag is closed.
            $voidtype = $voidtype === '/>' ? '>' : '/>';
            $fixturepath = $fixturedir.$image;
            if (!file_exists($fixturepath)) {
                throw new coding_exception('Fixture image does not exist '.$fixturepath);
            }

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($label->cmid)->id, 'itemid' => 0,
                'filename' => $image, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
            $path = '@@PLUGINFILE@@/' . $image;
            $labeltext .= 'Some text before the image';
            $labeltext .= '<img src="' . $path . '" alt="test file ' . $i . '" ' . $voidtype;
            $labeltext .= 'Some text after the image';
        }

        $label = $DB->get_record('label', ['id' => $label->id]);
        $label->intro = $labeltext;
        $label->name = 'test label';
        $DB->update_record('label', $label);
    }
}