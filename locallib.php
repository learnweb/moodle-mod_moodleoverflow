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
 * Internal library of functions for module moodleoverflow
 *
 * All the moodleoverflow specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\ratings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/lib.php');

/**
 * Returns the amount of discussions of the given context module.
 *
 * @param object $cm
 *
 * @return int
 */
function moodleoverflow_get_discussions_count(object $cm): int {
    global $DB, $USER;

    $params = ['instance' => $cm->instance];
    $whereconditions = ['d.moodleoverflow = :instance', 'p.parent = 0'];

    if (!has_capability('mod/moodleoverflow:reviewpost', context_module::instance($cm->id))) {
        $whereconditions[] = '(p.reviewed = 1 OR p.userid = :userid)';
        $params['userid'] = $USER->id;
    }
    $sql = 'SELECT COUNT(d.id)
            FROM {moodleoverflow_discussions} d
                JOIN {moodleoverflow_posts} p ON p.discussion = d.id
            WHERE ' . implode(' AND ', $whereconditions);
    return $DB->count_records_sql($sql, $params);
}
/**
 * Returns if there are unread messages for the current user in a moodleoverflow.
 *
 * @param object $cm
 *
 * @return bool
 */
function moodleoverflow_get_discussions_unread($cm) {
    global $DB, $USER;

    // Get the current timestamp and the oldpost-timestamp.
    $cutoffdate = round(time(), -2) - (get_config('moodleoverflow', 'oldpostdays') * 24 * 60 * 60);

    $whereconditions = ['d.moodleoverflow = :instance', 'p.modified >= :cutoffdate', 'r.id is NULL'];
    $params = ['userid' => $USER->id, 'instance' => $cm->instance, 'cutoffdate' => $cutoffdate];

    if (!has_capability('mod/moodleoverflow:reviewpost', context_module::instance($cm->id))) {
        $whereconditions[] = '(p.reviewed = 1 OR p.userid = :userid2)';
        $params['userid2'] = $USER->id;
    }

    $wheresql = join(' AND ', $whereconditions);

    // Define the sql-query.
    $sql = "SELECT d.id, COUNT(p.id) AS unread
            FROM {moodleoverflow_discussions} d
                JOIN {moodleoverflow_posts} p ON p.discussion = d.id
                LEFT JOIN {moodleoverflow_read} r ON (r.postid = p.id AND r.userid = :userid)
            WHERE $wheresql
            GROUP BY d.id";

    return !empty($DB->get_records_sql($sql, $params));
}

/**
 * Modifies the session to return back to where the user is coming from.
 *
 * @param object $default
 *
 * @return mixed
 */
function moodleoverflow_go_back_to($default) {
    global $SESSION;
    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Updates user grade.
 *
 * @param moodleoverflow $modflow
 * @param int $postuserrating
 * @param int $postinguser
 * @return void
 */
function moodleoverflow_update_user_grade(moodleoverflow $modflow, int $postuserrating, int $postinguser): void {
    global $DB;
    if (!$modflow->is_graded()) {
        return;
    }
    // Calculate the posting user's updated grade.
    $grade = min($postuserrating / $modflow->gradescalefactor, $modflow->grademaxgrade);

    // Save updated grade on local table.
    $lookup = ['userid' => $postinguser, 'moodleoverflowid' => $modflow->id];
    if ($DB->record_exists('moodleoverflow_grades', $lookup)) {
        $DB->set_field('moodleoverflow_grades', 'grade', $grade, $lookup);
    } else {
        $DB->insert_record('moodleoverflow_grades', (object) ($lookup + ['grade' => $grade]));
    }
    // Update gradebook.
    moodleoverflow_update_grades($modflow->get_db_object(), $postinguser);
}

/**
 * Updates all grades in a moodleoverflow.
 * @param moodleoverflow $modflow
 * @return void
 * @throws dml_exception
 */
function moodleoverflow_update_all_grades_for_instance(moodleoverflow $modflow): void {
    global $DB;

    // Check whether moodleoverflow object has the added params.
    if ($modflow->is_graded()) {
        // Get all users id.
        $params = ['moodleoverflowid' => $modflow->id, 'moodleoverflowid2' => $modflow->id];
        $sql = 'SELECT DISTINCT u.userid FROM (
                    SELECT p.userid as userid
                    FROM {moodleoverflow_discussions} d, {moodleoverflow_posts} p
                    WHERE d.id = p.discussion AND d.moodleoverflow = :moodleoverflowid
                    UNION
                    SELECT r.userid as userid
                    FROM {moodleoverflow_ratings} r
                    WHERE r.moodleoverflowid = :moodleoverflowid2
                ) as u';
        $userids = $DB->get_fieldset_sql($sql, $params);

        // Iterate all users.
        foreach ($userids as $userid) {
            if ($userid == 0) {
                continue;
            }
            // Calculate the posting user's updated grade.
            moodleoverflow_update_user_grade($modflow, ratings::get_reputation($modflow->id, $userid, true), $userid);
        }
    }
}

/**
 * Updates all grades.
 * @return void
 * @throws coding_exception|dml_exception
 */
function moodleoverflow_update_all_grades(): void {
    global $DB;
    foreach ($DB->get_records('moodleoverflow') as $record) {
        moodleoverflow_update_all_grades_for_instance(moodleoverflow::from_record($record));
    }
}

/**
 * Caches all language strings keys so react components can access them.
 * @return void
 */
function moodleoverflow_cache_strings(): void {
    global $PAGE;
    $strings = get_string_manager()->load_component_strings('mod_moodleoverflow', current_language());
    $PAGE->requires->strings_for_js(array_keys($strings), 'mod_moodleoverflow');
    $PAGE->requires->strings_for_js(['loading', 'noresults', 'nothingtodisplay', 'posts'], 'core');
}
