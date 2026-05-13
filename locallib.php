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

use core_availability\info_module;
use core_user\fields;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\models\post;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;

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
 * Checks if a user can see a specific post.
 *
 * @param post $post
 * @param object $cm
 * @param ?int $userid
 *
 * @return bool
 */
function moodleoverflow_user_can_see_post(post $post, object $cm, ?int $userid = null) {
    global $USER;
    $userid = $userid ?? $USER->id;
    $modulecontext = context_module::instance($cm->id);

    // Get capabilites.
    $canview = capabilities::has(capabilities::VIEW_DISCUSSION, $modulecontext, $userid);
    $canreview = capabilities::has(capabilities::REVIEW_POST, $modulecontext, $userid);
    $isvisible = info_module::is_user_visible($cm, $userid, false);

    return ($canview && ($post->reviewed == 1 || $post->get_userid() == $userid || $canreview)) && $isvisible;
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
 * Checks whether the user can reply to posts in a discussion.
 *
 * @param context $modulecontext
 * @param object $posttoreplyto
 * @param bool $considerreviewstatus
 * @param int $userid
 * @return bool Whether the user can reply
 * @throws coding_exception
 */
function moodleoverflow_user_can_post($modulecontext, $posttoreplyto, $considerreviewstatus = true, $userid = null) {
    global $USER;
    $userid = $userid ?? $USER->id;
    $canpost = has_capability('mod/moodleoverflow:replypost', $modulecontext, $userid);
    return  $canpost && (!$considerreviewstatus || $posttoreplyto->reviewed == 1);
}

/**
 * Updates user grade.
 *
 * @param object $moodleoverflow
 * @param int $postuserrating
 * @param int $postinguser
 * @return void
 */
function moodleoverflow_update_user_grade(object $moodleoverflow, int $postuserrating, int $postinguser): void {
    global $DB;
    // Check whether moodleoverflow object has the added params.
    if ($moodleoverflow->grademaxgrade > 0 && $moodleoverflow->gradescalefactor > 0) {
        // Calculate the posting user's updated grade.
        $grade = min($postuserrating / $moodleoverflow->gradescalefactor, $moodleoverflow->grademaxgrade);
        // Save updated grade on local table.
        $lookup = ['userid' => $postinguser, 'moodleoverflowid' => $moodleoverflow->id];
        if ($existing = $DB->get_record('moodleoverflow_grades', $lookup)) {
            $existing->grade = $grade;
            $DB->update_record('moodleoverflow_grades', $existing);
        } else {
            $DB->insert_record('moodleoverflow_grades', (object) array_merge($lookup, ['grade' => $grade]));
        }
        // Update gradebook.
        moodleoverflow_update_grades($moodleoverflow, $postinguser);
    }
}

/**
 * Updates all grades for context module.
 *
 * @param int $moodleoverflowid
 *
 */
function moodleoverflow_update_all_grades_for_cm($moodleoverflowid) {
    global $DB;

    $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflowid]);

    // Check whether moodleoverflow object has the added params.
    if ($moodleoverflow->grademaxgrade > 0 && $moodleoverflow->gradescalefactor > 0) {
        // Get all users id.
        $params = ['moodleoverflowid' => $moodleoverflowid, 'moodleoverflowid2' => $moodleoverflowid];
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

            // Get user reputation.
            $userrating = ratings::moodleoverflow_get_reputation($moodleoverflow->id, $userid, true);

            // Calculate the posting user's updated grade.
            moodleoverflow_update_user_grade($moodleoverflow, $userrating, $userid);
        }
    }
}

/**
 * Updates all grades.
 */
function moodleoverflow_update_all_grades() {
    global $DB;
    $cmids = $DB->get_records_select('moodleoverflow', null, null, 'id');
    foreach ($cmids as $cmid) {
        moodleoverflow_update_all_grades_for_cm($cmid->id);
    }
}


/**
 * Function to sort an array with a quicksort algorithm. This function is a recursive function that needs to
 * be called from outside.
 *
 * @param array $array The array to be sorted. It is passed by reference.
 * @param int $low The lowest index of the array. The first call should set it to 0.
 * @param int $high The highest index of the array. The first call should set it to the length of the array - 1.
 *
 * @param string $key The key/attribute after what the algorithm sorts. The key should be an comparable integer.
 * @param string $order The order of the sorting. It can be 'asc' or 'desc'.
 * @return void
 */
function moodleoverflow_quick_array_sort(&$array, $low, $high, $key, $order) {
    if ($low >= $high) {
        return;
    }
    $left = $low;
    $right = $high;
    $pivot = $array[intval(($low + $high) / 2)]->$key;

    $compare = function ($a, $b) use ($order) {
        if ($order == 'asc') {
            return $a < $b;
        } else {
            return $a > $b;
        }
    };

    do {
        while ($compare($array[$left]->$key, $pivot)) {
            $left++;
        }
        while ($compare($pivot, $array[$right]->$key)) {
            $right--;
        }
        if ($left <= $right) {
            $temp = $array[$right];
            $array[$right] = $array[$left];
            $array[$left] = $temp;
            $right--;
            $left++;
        }
    } while ($left <= $right);
    if ($low < $right) {
        moodleoverflow_quick_array_sort($array, $low, $right, $key, $order);
    }
    if ($high > $left) {
        moodleoverflow_quick_array_sort($array, $left, $high, $key, $order);
    }
}

/**
 * Function to get a record from the database and throw an exception, if the record is not available. The error string is
 * retrieved from moodleoverflow but can be retrieved from the core too.
 * @param string $table                 The table to get the record from
 * @param array $options                Conditions for the record
 * @param string $exceptionstring       Name of the moodleoverflow exception that should be thrown in case there is no record.
 * @param string $fields                Optional fields that are retrieved from the found record.
 * @param bool $coreexception           Optional param if exception is from the core exceptions.
 * @return mixed $record                The found record
 */
function moodleoverflow_get_record_or_exception($table, $options, $exceptionstring, $fields = '*', $coreexception = false) {
    global $DB;
    if (!$record = $DB->get_record($table, $options, $fields)) {
        throw new moodle_exception($exceptionstring, $coreexception ? 0 : 'moodleoverflow');
    }
    return $record;
}

/**
 * Function to retrieve a config and throw an exception, if the config is not found.
 * @param string $plugin            Plugin that has the configuration
 * @param string $configname        Name of configuration
 * @param string $errorcode         Error code/name of the exception
 * @param string $exceptionmodule   Module that has the exception.
 * @return mixed $config
 */
function moodleoverflow_get_config_or_exception($plugin, $configname, $errorcode, $exceptionmodule) {
    if (!$config = get_config($plugin, $configname)) {
        throw new moodle_exception($errorcode, $exceptionmodule);
    }
    return $config;
}

/**
 * Function that throws an exception if a given check is true.
 * @param bool $check               The result of a boolean check.
 * @param string $errorcode         Error code/name of the exception
 * @param string $coreexception     Optional param if exception is from the core exceptions and not moodleoverflow.
 * @return void
 */
function moodleoverflow_throw_exception_with_check($check, $errorcode, $coreexception = false) {
    if ($check) {
        throw new moodle_exception($errorcode, $coreexception ? 0 : 'moodleoverflow');
    }
}

/**
 * Function that catches unenrolled users and redirects them to the enrolment page.
 * @param context $coursecontext     The context of the course.
 * @param int $courseid             Id of the course that the user needs to enrol.
 * @param string $returnurl         The url to return to after the user has been enrolled.
 * @return void
 */
function moodleoverflow_catch_unenrolled_user($coursecontext, $courseid, $returnurl) {
    global $SESSION;
    if (!isguestuser() && !is_enrolled($coursecontext)) {
        if (enrol_selfenrol_available($courseid)) {
            $SESSION->wantsurl = qualified_me();
            $SESSION->enrolcancel = get_local_referer(false);
            $url = new \moodle_url('/enrol/index.php', ['id' => $courseid, 'returnurl' => $returnurl]);
            redirect($url, get_string('youneedtoenrol'));
        }
    }
}
