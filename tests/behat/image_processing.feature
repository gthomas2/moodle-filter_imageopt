# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# Tests for image optimiser filter.
#
# @package    filter_imageopt
# @copyright  Copyright (c) 2017 Guy Thomas.
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@filter @filter_imageopt
Feature: When the image optimiser filter is enabled, images are placeheld until visible and re-sampled to appropriate widths.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the image optimiser filter is enabled
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  @javascript
  Scenario: Images are optimised as required.
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I create a label resource with fixture images "testpng_2880x1800.png,testpng_400x250.png"
    And I reload the page
    And the image "testpng_2880x1800.png" has been optimised
    And the image "testpng_400x250.png" has not been optimised
    And I directly open the image "testpng_2880x1800.png" in the test label in course "C1"
    # If the image was opened successfully then there should not be a html element on the page.
    And I wait until "#page-wrapper" "css_element" does not exist
    And I am on site homepage
    And I log out
    And I log in as "student1"
    And I directly open the image "testpng_2880x1800.png" in the test label in course "C1"
    # The page is reloaded to nuke image cache.
    And I reload the page
    # If the image could not be opened due to access rights, we should have a html element on a page with appropriate options.
    And I wait until "#page-wrapper" "css_element" exists
    And I should see "You can not enrol yourself in this course"
    And I log out
    And I directly open the image "testpng_2880x1800.png" in the test label in course "C1"    
    # The page is reloaded to nuke image cache.
    And I reload the page
    And I wait until "#page-wrapper" "css_element" exists
    And I should see "Some courses may allow guest access" in the "#page-login-index" "css_element"