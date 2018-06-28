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

defined('MOODLE_INTERNAL') || die();

/**
 * Filter
 *
 * @package   filter_imageopt
 * @copyright Copyright (c) 2018 Citricity Ltd
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_filter_imageopt_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018061803) {

        // Define table filter_imageopt to be created.
        $table = new xmldb_table('filter_imageopt');

        // Adding fields to table filter_imageopt.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('urlpath', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeprocessed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table filter_imageopt.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table filter_imageopt.
        $table->add_index('urlpath', XMLDB_INDEX_UNIQUE, array('urlpath'));

        // Conditionally launch create table for filter_imageopt.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Imageopt savepoint reached.
        upgrade_plugin_savepoint(true, 2018061803, 'filter', 'imageopt');
    }

    return true;
}