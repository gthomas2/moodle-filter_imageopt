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
 * Image optimiser
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use filter_imageopt\image;
use filter_imageopt\local;

/**
 * Image optimiser - main filter class.
 * @package   filter_imageopt
 * @author    Guy Thomas <brudinie@gmail.com>
 * @copyright Copyright (c) Guy Thomas.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_imageopt extends moodle_text_filter {

    /**
     * @var stdClass - filter config
     */
    private $config;

    public function __construct(context $context, array $localconfig) {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');

        $this->config = get_config('filter_imageopt');
        if (!isset($this->config->widthattribute)) {
            $this->config->widthattribute = image::WIDTHATTPRSERVELTMAX;
        }
        $this->config->widthattribute = intval($this->config->widthattribute);
        if (!isset($this->config->maxwidth)) {
            $this->config->maxwidth = 480;
        }

        parent::__construct($context, $localconfig);
    }

    private function empty_image($width, $height) {
        // @codingStandardsIgnoreStart
        $svg = <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="$width" height="$height" viewBox="0 0 $width $height">
</svg>
EOF;
        // @codingStandardsIgnoreEnd

        $svg = str_replace("\n", ' ', $svg); // Strip new lines from svg so that it can be used in URLs.

        return $svg;
    }

    /**
     * Add width and height to img tag and return modified tag with width and height
     * @param string $img
     * @param int $width
     * @param int $height
     * @return string
     * @throws file_exception
     */
    private function img_add_width_height($img, $width, $height) {
        $maxwidth = $this->config->maxwidth;

        if (stripos($img, ' width') !== false) {
            if ($this->config->widthattribute === image::WIDTHATTPRSERVELTMAX) {
                // Note - we cannot check for percentage widths as they are responsively variable.
                $regex = '/(?<=\<img)(?:.*)width(?:\s|)=(?:"|\')(\d*)(?:px|)(?:"|\')/';
                $matches = [];
                preg_match($regex, $img, $matches);
                if (!empty($matches[1])) {
                    $checkwidth = $matches[1];
                    if ($checkwidth < $maxwidth) {
                        // This image already has a width attribute and that width is less than the max width.
                        return $img;
                    }
                }
            } else {
                // Return img tag as is with width preserved.
                return $img;
            }
        }

        if ($width > $maxwidth) {
            $ratio = $height / $width;
            $width = $maxwidth;
            $height = $width * $ratio;
        } else {
            return $img;
        }

        $matches = [];
        $regex = '/(?<=img )(?:|.*)(width(?:|\s)=(?:|\s)"(|\d*)")(?:|.*)(height(?:|\s)=(?:|\s)"(|\d*)")/';
        $match = preg_match($regex, $img, $matches);
        if ($match) {
            $img = str_ireplace($matches[1], 'width="'.$width.'"', $img);
            $img = str_ireplace($matches[3], 'height="'.$height.'"', $img);
        } else {
            $img = str_ireplace('<img ', '<img width="'.$width.'" height="'.$height.'" ', $img);
        }

        return $img;
    }

    /**
     * Create image optimiser url - will take an original file and resize it then forward on.
     * @param stored_file $file
     * @param string $originalsrc
     * @return moodle_url
     */
    public function image_opt_url(stored_file $file, $originalsrc) {
        global $CFG;

        $maxwidth = $this->config->maxwidth;
        $filename = $file->get_filename();
        $contextid = $file->get_contextid();

        $originalpath = local::get_img_path_from_src($originalsrc);
        $urlpathid = local::add_url_path_to_queue($originalpath);

        $url = $CFG->wwwroot.'/pluginfile.php/'.$contextid.'/filter_imageopt/'.$maxwidth.'/'.
                $urlpathid.'/'.$filename;

        return new moodle_url($url);
    }

    private function process_img_tag(array $match) {
        global $CFG;

        $fs = get_file_storage();

        $maxwidth = $this->config->maxwidth;

        $optimisedavailable = false;

        // Don't process images that aren't in this site or don't have a relative path.
        if (stripos($match[2], $CFG->wwwroot) === false && substr($match[2], 0, 1) != '/') {
            return $match[0];
        }

        $file = local::get_img_file($match[3]);

        if (!$file) {
            return $match[0];
        }

        // Generally, if anything is being exported then we don't want to mess with it.
        if ($file->get_filearea() === 'export') {
            return $match[0];
        }

        if (stripos($match[3], 'imageopt/'.$maxwidth.'/') !== false) {
            return $match[0];
        }

        $imageinfo = (object) $file->get_imageinfo();
        if ($imageinfo->width <= $maxwidth) {
            return $match[0];
        }

        $optimisedpath = local::get_optimised_path($match[3]);
        $optimisedavailable = local::get_img_file($optimisedpath);

        $originalsrc = $match[2];

        if ($optimisedavailable) {
            $optimisedsrc = local::get_optimised_src($file, $originalsrc, $optimisedpath);
        } else {
            $optimisedsrc = $this->image_opt_url($file, $originalsrc);
        }

        if (empty($this->config->loadonvisible) || $this->config->loadonvisible < 999) {
            return $this->apply_loadonvisible($match, $file, $originalsrc, $optimisedsrc, $optimisedavailable);
        } else {
            return $this->apply_img_tag($match, $file, $originalsrc, $optimisedsrc);
        }
    }

    /**
     * Place hold images so that they are loaded when visible.
     * @param array $match (0 - full img tag, 1 src tag and contents, 2 - contents of src, 3 - pluginfile.php/)
     * @param stored_file $file
     * @param string $originalsrc
     * @param string $optimisedsrc
     * @param bool $optimisedavailable
     * @return string
     */
    private function apply_loadonvisible(array $match, stored_file $file, $originalsrc, $optimisedsrc,
                                         $optimisedavailable = false) {
        global $PAGE;

        static $jsloaded = false;
        static $imgcount = 0;

        $imgcount ++;

        // This is so we can make the first couple of images load immediately without placeholding.
        if ($imgcount <= $this->config->loadonvisible) {
            return $this->apply_img_tag($match, $file, $originalsrc, $optimisedsrc);
        }

        if (!$jsloaded) {
            $PAGE->requires->js_call_amd('filter_imageopt/imageopt', 'init');
        }

        $jsloaded = true;

        // Full image tag + attributes, etc.
        $img = $match[0];

        // If this text already has load on visible applied then just return it.
        if (stripos('data-loadonvisible', $match[0]) !== false) {
            return ($img);
        }

        $maxwidth = $this->config->maxwidth;

        if (!$file) {
            return $img;
        }
        $imageinfo = (object) $file->get_imageinfo();
        if (!$imageinfo || !isset($imageinfo->width)) {
            return ($img);
        }
        $width = $imageinfo->width;
        $height = $imageinfo->height;
        $img = $this->img_add_width_height($img, $width, $height);

        // Replace img src attribute and add data-loadonvisible.
        if (!$file) {
            $loadonvisible = $match[2];
        } else {
            $loadonvisible = $optimisedsrc;
        }

        $optimisedavailable = $optimisedavailable ? 1 : 0;

        $img = str_ireplace('<img ',
                '<img data-loadonvisible="'.$loadonvisible.'" data-optimised="'.$optimisedavailable.'" ', $img);
        $img = str_ireplace($match[1], 'src="data:image/svg+xml;utf8,'.s($this->empty_image($width, $height)).'"', $img);

        return ($img);
    }

    /**
     * Process the image tag so that it has the new resize url and appropriate width / height settings.
     * @param array $match (0 - full img tag, 1 src tag and contents, 2 - contents of src, 3 - pluginfile.php/)
     * @param stored_file $file
     * @param string $originalsrc
     * @param string $optimisedsrc
     * @return string
     */
    private function apply_img_tag($match, stored_file $file, $originalsrc, $optimisedsrc) {

        if (stripos($match[3], 'optimised/') !== false) {
            // Already processed.
            return $match[0];
        }

        raise_memory_limit(MEMORY_EXTRA);

        $file = local::get_img_file($match[3]);
        if (!$file) {
            return $match[0];
        }

        $imageinfo = (object) $file->get_imageinfo();
        if (empty($imageinfo) || !isset($imageinfo->width)) {
            return $match[0];
        }

        $width = $imageinfo->width;
        $height = $imageinfo->height;

        $maxwidth = $this->config->maxwidth;

        if ($imageinfo->width < $maxwidth) {
            return $match[0];
        }

        $newsrc = $optimisedsrc;

        $img = $this->img_add_width_height($match[0], $width, $height);

        $img = str_replace($match[2], $newsrc, $img);

        $img = str_ireplace('<img ', '<img data-originalsrc="'.$originalsrc.'" ', $img);

        return $img;
    }

    /**
     * Filter content.
     *
     * @param string $text HTML to be processed.
     * @param array $options
     * @return string String containing processed HTML.
     */
    public function filter($text, array $options = array()) {
        if (stripos($text, '<img') === false || strpos($text, 'pluginfile.php') === false) {
            return $text;
        }

        $filtered = $text; // We need to return the original value if regex fails!

        $search = local::REGEXP_IMGSRC;
        $filtered = preg_replace_callback($search, 'self::process_img_tag', $filtered);

        if (empty($filtered)) {
            return $text;
        }
        return $filtered;
    }
}
