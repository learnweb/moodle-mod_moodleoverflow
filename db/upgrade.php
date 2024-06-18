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
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute moodleoverflow upgrade from the given old version
 *
 * @param int $oldversion
 *
 * @return bool
 */
function xmldb_moodleoverflow_upgrade($oldversion) {
    global $CFG;
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2017110713) {
        // Migrate config.
        set_config('manydiscussions', $CFG->moodleoverflow_manydiscussions, 'moodleoverflow');
        set_config('maxbytes', $CFG->moodleoverflow_maxbytes, 'moodleoverflow');
        set_config('maxattachments', $CFG->moodleoverflow_maxattachments, 'moodleoverflow');
        set_config('maxeditingtime', $CFG->moodleoverflow_maxeditingtime, 'moodleoverflow');
        set_config('trackingtype', $CFG->moodleoverflow_trackingtype, 'moodleoverflow');
        set_config('trackreadposts', $CFG->moodleoverflow_trackreadposts, 'moodleoverflow');
        set_config('allowforcedreadtracking', $CFG->moodleoverflow_allowforcedreadtracking, 'moodleoverflow');
        set_config('oldpostdays', $CFG->moodleoverflow_oldpostdays, 'moodleoverflow');
        set_config('cleanreadtime', $CFG->moodleoverflow_cleanreadtime, 'moodleoverflow');
        set_config('allowratingchange', $CFG->moodleoverflow_allowratingchange, 'moodleoverflow');
        set_config('votescalevote', $CFG->moodleoverflow_votescalevote, 'moodleoverflow');
        set_config('votescaledownvote', $CFG->moodleoverflow_votescaledownvote, 'moodleoverflow');
        set_config('votescaleupvote', $CFG->moodleoverflow_votescaleupvote, 'moodleoverflow');
        set_config('votescalesolved', $CFG->moodleoverflow_votescalesolved, 'moodleoverflow');
        set_config('votescalehelpful', $CFG->moodleoverflow_votescalehelpful, 'moodleoverflow');
        set_config('maxmailingtime', $CFG->moodleoverflow_maxmailingtime, 'moodleoverflow');

        // Delete old config.
        set_config('moodleoverflow_manydiscussions', null, 'moodleoverflow');
        set_config('moodleoverflow_maxbytes', null, 'moodleoverflow');
        set_config('moodleoverflow_maxattachments', null, 'moodleoverflow');
        set_config('moodleoverflow_maxeditingtime', null, 'moodleoverflow');
        set_config('moodleoverflow_trackingtype', null, 'moodleoverflow');
        set_config('moodleoverflow_trackreadposts', null, 'moodleoverflow');
        set_config('moodleoverflow_allowforcedreadtracking', null, 'moodleoverflow');
        set_config('moodleoverflow_oldpostdays', null, 'moodleoverflow');
        set_config('moodleoverflow_cleanreadtime', null, 'moodleoverflow');
        set_config('moodleoverflow_allowratingchange', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalevote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescaledownvote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescaleupvote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalesolved', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalehelpful', null, 'moodleoverflow');
        set_config('moodleoverflow_maxmailingtime', null, 'moodleoverflow');

        // Opencast savepoint reached.
        upgrade_mod_savepoint(true, 2017110713, 'moodleoverflow');
    }

    if ($oldversion < 2019052600) {

        // Define table moodleoverflow_grades to be created.
        $table = new xmldb_table('moodleoverflow_grades');

        // Adding fields to table moodleoverflow_grades.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('moodleoverflowid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('grade', XMLDB_TYPE_FLOAT, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table moodleoverflow_grades.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('moodleoverflowid', XMLDB_KEY_FOREIGN, ['moodleoverflowid'], 'moodleoverflow', ['id']);

        // Conditionally launch create table for moodleoverflow_grades.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table moodleoverflow to be edited.
        $table = new xmldb_table('moodleoverflow');

        // Define field grademaxgrade to be added to moodleoverflow.
        $field = new xmldb_field('grademaxgrade', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'allownegativereputation');

        // Conditionally launch add field grademaxgrade.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field gradescalefactor to be added to moodleoverflow.
        $field = new xmldb_field('gradescalefactor', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'grademaxgrade');

        // Conditionally launch add field gradescalefactor.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('gradecat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'gradescalefactor');

        // Conditionally launch add field gradecat.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        moodleoverflow_update_all_grades();

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2019052600, 'moodleoverflow');
    }

    if ($oldversion < 2021060800) {

        // Define table moodleoverflow to be edited.
        $table = new xmldb_table('moodleoverflow');

        // Define field anonymous to be added to moodleoverflow.
        $field = new xmldb_field('anonymous', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 0, 'gradecat');

        // Conditionally launch add field anonymous.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2021060800, 'moodleoverflow');
    }

    if ($oldversion < 2021072700) {
        // Define fields late and completed to be dropped from moodleoverflow_grades.
        $table = new xmldb_table('moodleoverflow_grades');

        $field = new xmldb_field('late');
        // Conditionally launch drop field late.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('completed');
        // Conditionally launch drop field completed.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2021072700, 'moodleoverflow');
    }

    if ($oldversion < 2021111700) {

        // Define table moodleoverflow to be edited.
        $table = new xmldb_table('moodleoverflow');

        // Define field allowrating to be added to moodleoverflow.
        $field = new xmldb_field('allowrating', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 1, 'coursewidereputation');

        // Conditionally launch add field allowrating.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field allowreputation to be added to moodleoverflow.
        $field = new xmldb_field('allowreputation', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, 1, 'allowrating');

        // Conditionally launch add field allowreputation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2021111700, 'moodleoverflow');
    }

    if ($oldversion < 2022072000) {

        // Define field needsreview to be added to moodleoverflow.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('needsreview', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'anonymous');

        // Conditionally launch add field needsreview.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field reviewed and timereviewed to be added to moodleoverflow_posts.
        $table = new xmldb_table('moodleoverflow_posts');
        $field = new xmldb_field('reviewed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'mailed');

        // Conditionally launch add field reviewed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'reviewed');

        // Conditionally launch add field timereviewed.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('mailed', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'attachment');
        // Launch change of precision for field mailed.
        $dbman->change_field_precision($table, $field);

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2022072000, 'moodleoverflow');
    }

    if ($oldversion < 2022110700) {

        if (get_capability_info('mod/moodleoverflow:reviewpost')) {
            foreach (get_archetype_roles('manager') as $role) {
                unassign_capability('mod/moodleoverflow:reviewpost', $role->id);
            }

            foreach (get_archetype_roles('teacher') as $role) {
                assign_capability(
                    'mod/moodleoverflow:reviewpost', CAP_ALLOW, $role->id, context_system::instance()
                );
            }
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2022110700, 'moodleoverflow');
    }

    if ($oldversion < 2023022400) {
        // Table for information of digest mail.
        $table = new xmldb_table('moodleoverflow_mail_info');

        // Adding fields to table moodleoverflow_mail_info.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('forumid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('forumdiscussionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('numberofposts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('forumid', XMLDB_KEY_FOREIGN, ['forumid'], 'moodleoverflow', ['id']);
        $table->add_key('forumdiscussionid', XMLDB_KEY_FOREIGN,
                         ['forumdiscussionid'], 'moodleoverflow_discussions', ['id']);

        // Conditionally launch create table for moodleoverflow_mail_info.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2023022400, 'moodleoverflow');
    }

    if ($oldversion < 2023040400) {
        // Define table moodleoverflow to be edited.
        $table = new xmldb_table('moodleoverflow');

        // Define field allowmultiplemarks to be added to moodleoverflow.
        $field = new xmldb_field('allowmultiplemarks', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'needsreview');

        // Conditionally launch add field allowmultiplemarks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2023040400, 'moodleoverflow');
    }

    if ($oldversion < 2024031105) {
        // Define table moodleoverflow to be edited.
        $table = new xmldb_table('moodleoverflow');

        // Define field limitedanswer to be added to moodleoverflow.
        $field = new xmldb_field('limitedanswer', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'allowmultiplemarks');

        // Conditionally launch add field limitedanswer.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Moodleoverflow savepoint reached.
        upgrade_mod_savepoint(true, 2024031105, 'moodleoverflow');
    }
    if ($oldversion < 2024061700) {
        // Rename the first setting, to have a start and endtime for the limited answer mode.
        $table = new xmldb_table('moodleoverflow');
        $field = new xmldb_field('limitedanswer', XMLDB_TYPE_INTEGER, '10', null, null, null, 0, 'allowmultiplemarks');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'la_starttime');
        }
        // Create the field for the end time.
        $field = new xmldb_field('la_endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, 0, 'la_starttime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2024061700, 'moodleoverflow');
    }

    return true;
}
