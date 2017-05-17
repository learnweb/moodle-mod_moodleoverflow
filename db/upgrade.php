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

    if ($oldversion < 2017050201) {

        // Define table moodleoverflow_read to be created.
        $table = new xmldb_table('moodleoverflow_read');

        // Adding fields to table moodleoverflow_read.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moodleoverflowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('discussionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('firstread', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastread', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table moodleoverflow_read.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table moodleoverflow_read.
        $table->add_index('userid-moodleoverflowid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'moodleoverflowid'));
        $table->add_index('userid-discussionid', XMLDB_INDEX_NOTUNIQUE, array('userid', 'discussionid'));
        $table->add_index('postid-userid', XMLDB_INDEX_NOTUNIQUE, array('postid', 'userid'));

        // Conditionally launch create table for moodleoverflow_read.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050201, 'moodleoverflow');
    }

    // Create moodleoverflow_subscriptions.
    if ($oldversion < 2017050402) {

        // Define table moodleoverflow_subscriptions to be created.
        $table = new xmldb_table('moodleoverflow_subscriptions');

        // Adding fields to table moodleoverflow_subscriptions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('moodleoverflow', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table moodleoverflow_subscriptions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('moodleoverflow', XMLDB_KEY_FOREIGN, array('moodleoverflow'), 'moodleoverflow', array('id'));

        // Adding indexes to table moodleoverflow_subscriptions.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for moodleoverflow_subscriptions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050402, 'moodleoverflow');
    }

    // Create moodleoverflow_discuss_subs.
    if ($oldversion < 2017050403) {

        // Define table moodleoverflow_discuss_subs to be created.
        $table = new xmldb_table('moodleoverflow_discuss_subs');

        // Adding fields to table moodleoverflow_discuss_subs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('moodleoverflow', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('preference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table moodleoverflow_discuss_subs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('moodleoverflow', XMLDB_KEY_FOREIGN, array('moodleoverflow'), 'moodleoverflow', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'moodleoverflow_discussions', array('id'));
        $table->add_key('user_discussions', XMLDB_KEY_UNIQUE, array('userid', 'discussion'));

        // Conditionally launch create table for moodleoverflow_discuss_subs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050403, 'moodleoverflow');
    }

    // Drop the default grade field of the moodleoverflow tabke.
    if ($oldversion < 2017050405) {

        // Define field course to be dropped from moodleoverflow.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('grade');

        // Conditionally launch drop field course.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050405, 'moodleoverflow');
    }

    // Add the fields trackingtype and forcesubscription to moodleoverflow.
    if ($oldversion < 2017050406) {

        // Define field forcesubscribe to be added to moodleoverflow.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('forcesubscribe', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field forcesubscribe.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field trackingtype to be added to moodleoverflow.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('trackingtype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'forcesubscribe');

        // Conditionally launch add field trackingtype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050406, 'moodleoverflow');
    }

    // Change the default value of the trackingtype field in the moodleoverflow table.
    if ($oldversion < 2017050407) {

        // Changing the default of field trackingtype on table moodleoverflow to 1.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('trackingtype', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'forcesubscribe');

        // Launch change of default for field trackingtype.
        $dbman->change_field_default($table, $field);

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050407, 'moodleoverflow');
    }

    // Add the usermodified-field to moodleoverflow_discussions.
    if ($oldversion < 2017050413) {

        // Define field usermodified to be added to moodleoverflow_discussions.
        $table = new xmldb_table('moodleoverflow_discussions');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timestart');

        // Conditionally launch add field usermodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017050413, 'moodleoverflow');
    }

    // Add the messageformat-field to moodleoverflow_posts.
    if ($oldversion < 2017051001) {

        // Define field messageformat to be added to moodleoverflow_posts.
        $table = new xmldb_table('moodleoverflow_posts');
        $field = new xmldb_field('messageformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'message');

        // Conditionally launch add field messageformat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2017051001, 'moodleoverflow');
    }


    return true;
}
