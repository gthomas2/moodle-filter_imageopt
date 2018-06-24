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
 * Tests for filter class
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Guy Thomas 2017.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use filter_imageopt\local;

global $CFG;

require_once($CFG->dirroot . '/files/externallib.php');
require_once(__DIR__.'/../filter.php');

/**
 * Tests for filter class
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Guy Thomas 2017.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_imageopt_filter_testcase extends advanced_testcase {

    /**
     * Test regex works with sample img tag + pluginfile.php src.
     */
    public function test_regex() {
        $regex = local::REGEXP_IMGSRC;
        $matches = [];
        $img = '<img src="http://www.example.com/moodle/pluginfile.php/236001/mod_label/intro/0/testpng_2880x1800.png"';
        $img .= ' alt="" role="presentation"';
        $imgclosed = $img.' />';
        $img .= '>';

        // Test self closing img tag.
        $match = preg_match($regex, $img, $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($img, $matches[0]);
        $match = preg_match($regex, '<div>'.$img.'</div>', $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($img, $matches[0]);
        $match = preg_match($regex, "\n\n\n".$img, $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($img, $matches[0]);

        // Test closed img tag.
        $match = preg_match($regex, $imgclosed, $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($imgclosed, $matches[0]);
        $match = preg_match($regex, '<div>'.$imgclosed.'</div>', $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($imgclosed, $matches[0]);
        $match = preg_match($regex, "\n\n\n".$imgclosed, $matches);
        $this->assertEquals(1, $match);
        $this->assertEquals($imgclosed, $matches[0]);
    }

    /**
     * Test empty svg image contains width and height params.
     * @throws dml_exception
     */
    public function test_empty_image() {
        $filter = new filter_imageopt(context_system::instance(), []);
        $sizes = [
            [400, 300],
            [640, 480],
            [1024, 768]
        ];
        foreach ($sizes as $size) {
            $emptyimage = phpunit_util::call_internal_method($filter, 'empty_image', $size, get_class($filter));
            $this->assertContains('width="'.$size[0].'" height="'.$size[1].'"', $emptyimage);
        }
    }

    /**
     * Test image opt url is created as expected.
     * @throws dml_exception
     */
    public function test_image_opt_url() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('maxwidth', '480', 'filter_imageopt');

        $fixturefile = 'testpng_2880x1800.png';
        $context = context_system::instance();

        $file = $this->std_file_record($context, $fixturefile);
        $filter = new filter_imageopt($context, []);

        $originalurl = 'http://somesite/pluginfile.php/somefile.jpg';

        $url = phpunit_util::call_internal_method($filter, 'image_opt_url', [$file, $originalurl], get_class($filter));

        $row = $DB->get_record('filter_imageopt', ['urlpath' => 'pluginfile.php/somefile.jpg']);
        $urlpathid = $row->id;

        $expected = new moodle_url(
            $CFG->wwwroot.'/pluginfile.php/'.$context->id.'/filter_imageopt/480'.'/'.$urlpathid.'/'.$file->get_filename());
        $this->assertEquals($expected, $url);
    }

    /**
     * Test img_add_width_height function.
     * @throws dml_exception
     */
    public function test_img_add_width_height() {
        $this->resetAfterTest();

        $maxwidth = 480;
        set_config('maxwidth', $maxwidth, 'filter_imageopt');
        $newheight = (400 / 800) * $maxwidth;

        $context = context_system::instance();
        $filter = new filter_imageopt($context, []);

        $img = '<img src="test" width="800" height="400" />';
        $img = phpunit_util::call_internal_method($filter, 'img_add_width_height', [$img, 800, 400], get_class($filter));

        $expected = '<img src="test" width="480" height="'.$newheight.'" />';
        $this->assertEquals($expected, $img);

        $img = '<img src="test" />';
        $expected = '<img width="480" height="'.$newheight.'" src="test" />';
        $img = phpunit_util::call_internal_method($filter, 'img_add_width_height', [$img, 800, 400], get_class($filter));
        $this->assertEquals($expected, $img);
    }

    /**
     * Create moodle file.
     * @param context $context
     * @param string $fixturefile name of fixture file
     * @return stored_file
     * @throws file_exception
     * @throws stored_file_creation_exception
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
     * Test getting image from file path.
     * @throws coding_exception
     */
    public function test_get_img_file() {

        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $plugin = $dg->get_plugin_generator('mod_label');
        $fixturefile = 'testpng_2880x1800.png';
        $intro = '<p><img src="@@PLUGINFILE@@/'.$fixturefile.'" alt="" role="presentation"><br></p>';
        $label = $plugin->create_instance((object) ['course' => $course->id, 'intro' => $intro]);
        $context = context_module::instance($label->cmid);

        $this->std_file_record($context, $fixturefile);

        $filter = new filter_imageopt($context, []);

        $filepath = 'pluginfile.php/'.$context->id.'/mod_label/intro/'.$fixturefile;

        /** @var stored_file $imgfile */
        $imgfile = phpunit_util::call_internal_method(null, 'get_img_file', [$filepath], 'filter_imageopt\local');
        $this->assertNotEmpty($imgfile);
        $this->assertEquals($fixturefile, $imgfile->get_filename());
    }

    /**
     * Return image file in label and return label text + regex matches.
     * @return [string, array, stored_file]
     * @throws coding_exception
     * @throws file_exception
     * @throws stored_file_creation_exception
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
     * Test apply load on visible.
     * @throws coding_exception
     */
    public function test_apply_loadonvisible() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $maxwidth = 480;

        set_config('maxwidth', $maxwidth, 'filter_imageopt');
        set_config('loadonvisible', '0', 'filter_imageopt'); // 0 applies load on visible to all images.

        $fixturefile = 'testpng_2880x1800.png';

        list ($labeltxt, $matches, $file) = $this->create_image_file_text($fixturefile);

        /** @var stored_file $file */
        $file = $file;

        $filter = new filter_imageopt(context_helper::instance_by_id($file->get_contextid()), []);

        $regex = local::REGEXP_IMGSRC;
        preg_match($regex, $labeltxt, $matches);

        $originalsrc = $matches[2];

        $optimisedsrc = $filter->image_opt_url($file, $originalsrc);

        $str = phpunit_util::call_internal_method($filter, 'apply_loadonvisible', [$matches, $file, $originalsrc, $optimisedsrc],
                get_class($filter));

        $loadonvisibleurl = $CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/filter_imageopt/'.
                $maxwidth.'/~pathid~/'.$fixturefile;

        // Test filter plugin img, lazy load.
        $regex = '/img data-loadonvisible="'.str_replace('~pathid~', '(?:[0-9]*)', preg_quote($loadonvisibleurl, '/')).'/';
        $this->assertRegExp($regex, $str);
        $this->assertContains('src="data:image/svg+xml;utf8,', $str);

    }

    /**
     * Get the replacement imageopt filter image url for a stored_file.
     * @param stored_file $file
     * @param int $maxwidth;
     * @return string.
     */
    private function filter_imageopt_url_from_file(stored_file $file, $maxwidth) {
        global $CFG;

        $url = $CFG->wwwroot.'/pluginfile.php/'.$file->get_contextid().'/filter_imageopt/'.$maxwidth.
                '/~pathid~/'.$file->get_filename();

        return $url;
    }

    /**
     * Test processing image src.
     * @throws coding_exception
     */
    public function test_apply_img_tag() {

        $this->resetAfterTest();
        $this->setAdminUser();

        $maxwidth = 480;

        set_config('maxwidth', $maxwidth, 'filter_imageopt');

        $fixturefile = 'testpng_2880x1800.png';

        list ($labeltxt, $matches, $file) = $this->create_image_file_text($fixturefile);

        $context = context_helper::instance_by_id($file->get_contextid());

        $filter = new filter_imageopt($context, []);

        $originalsrc = $matches[2];
        $optimisedsrc = $filter->image_opt_url($file, $originalsrc);

        $processed = phpunit_util::call_internal_method($filter, 'apply_img_tag', [$matches, $file, $originalsrc, $optimisedsrc],
                get_class($filter));

        $postfilterurl = $this->filter_imageopt_url_from_file($file, $maxwidth);
        $regex = '/'.str_replace('~pathid~', '(?:[0-9]*)', preg_quote($postfilterurl, '/')).'/';
        $this->assertRegExp($regex, $processed);

    }

    /**
     * Test main filter function.
     * @throws coding_exception
     */
    public function test_filter() {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $maxwidth = 480;

        set_config('maxwidth', $maxwidth, 'filter_imageopt');

        $fixturefile = 'testpng_2880x1800.png';

        list ($labeltxt, $matches, $file) = $this->create_image_file_text($fixturefile);

        $context = context_helper::instance_by_id($file->get_contextid());

        // Test filter plugin img, no lazy load.
        set_config('loadonvisible', '999', 'filter_imageopt'); // 999 does not lazy load any images.
        $filter = new filter_imageopt($context, []);
        $filtered = $filter->filter($labeltxt);
        $prefilterurl = $CFG->wwwroot.'/pluginfile.php/'.$context->id.'/mod_label/intro/0/testpng_2880x1800.png';
        $this->assertContains($prefilterurl, $labeltxt);
        $postfilterurl = $this->filter_imageopt_url_from_file($file, $maxwidth);
        $regex = '/src="'.str_replace('~pathid~', '(?:[0-9]*)', preg_quote($postfilterurl, '/')).'/';
        $this->assertRegExp($regex, $filtered);

        // We need a space before src so it doesn't trigger on original-src.
        $this->assertNotContains(' src="'.$prefilterurl, $filtered);
        $this->assertNotContains('data-loadonvisible="'.$postfilterurl, $filtered);
        $this->assertNotContains('data-loadonvisible="'.$prefilterurl, $filtered);

        // Test filter plugin img,  lazy load.
        set_config('loadonvisible', '0', 'filter_imageopt');
        $filter = new filter_imageopt($context, []);
        $filtered = $filter->filter($labeltxt);
        $prefilterurl = $CFG->wwwroot.'/pluginfile.php/'.$context->id.'/mod_label/intro/0/testpng_2880x1800.png';
        $this->assertContains($prefilterurl, $labeltxt);
        $postfilterurl = $this->filter_imageopt_url_from_file($file, $maxwidth);

        $regex = '/data-loadonvisible="'.str_replace('~pathid~', '(?:[0-9]*)', preg_quote($postfilterurl, '/')).'/';
        $this->assertRegExp($regex, $filtered);

        $this->assertNotContains('data-loadonvisible="'.$prefilterurl, $filtered);
        $this->assertNotContains('src="'.$postfilterurl, $filtered);
        $this->assertNotContains('src="'.$prefilterurl, $filtered);
    }
}