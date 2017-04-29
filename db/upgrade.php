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
 * This file keeps track of upgrades to the moodleoverflow module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute moodleoverflow upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_moodleoverflow_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.


    // Create moodleoverflow_discussions.
    if ($oldversion < 2017042901) {

        // Define table moodleoverflow_discussions to be created.
        $table = new xmldb_table('moodleoverflow_discussions');

        // Adding fields to table moodleoverflow_discussions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moodleoverflow', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('firstpost', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table moodleoverflow_discussions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('moodleoverflow', XMLDB_KEY_FOREIGN, array('moodleoverflow'), 'moodleoverflow', array('id'));

        // Adding indexes to table moodleoverflow_discussions.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));

        // Conditionally launch create table for moodleoverflow_discussions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017042901, 'moodleoverflow');
    }

    // Create moodleoverflow_posts.
    if ($oldversion < 2017042902) {

        // Define table moodleoverflow_posts to be created.
        $table = new xmldb_table('moodleoverflow_posts');

        // Adding fields to table moodleoverflow_posts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('parent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('created', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table moodleoverflow_posts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'moodleoverflow_discussions', array('id'));
        $table->add_key('parent', XMLDB_KEY_FOREIGN, array('parent'), 'moodleoverflow_posts', array('id'));

        // Adding indexes to table moodleoverflow_posts.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('created', XMLDB_INDEX_NOTUNIQUE, array('created'));

        // Conditionally launch create table for moodleoverflow_posts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017042902, 'moodleoverflow');
    }



    return true;
}
