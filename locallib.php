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

use core_user\fields;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\event\post_deleted;
use mod_moodleoverflow\models\discussion;
use mod_moodleoverflow\output\discussion_card;
use mod_moodleoverflow\ratings;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\review;
use mod_moodleoverflow\subscriptions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/lib.php');

/**
 * Prints latest moodleoverflow discussions.
 *
 * @param object $moodleoverflow MoodleOverflow to be printed.
 * @param object $cm
 * @param int    $page           Page mode, page to display (optional).
 * @param int    $perpage        The maximum number of discussions per page (optional).
 */
function moodleoverflow_print_latest_discussions($moodleoverflow, $cm, $page = -1, $perpage = 25) {
    global $OUTPUT, $PAGE, $DB, $USER;

    // Set the context.
    $context = context_module::instance($cm->id);

    // If the perpage value is invalid, deactivate paging.
    if ($perpage <= 0) {
        $perpage = 0;
        $page = -1;
    }
    $usepaging = ($perpage > 0 && $page !== -1);
    $limitfrom = $usepaging ? $page * $perpage : 0;
    $limitamount = $usepaging ? $perpage : 0;

    // Check some capabilities and create other check variables.
    $canstartdiscussion = !(isguestuser() || !isloggedin()) && has_capability('mod/moodleoverflow:startdiscussion', $context);
    $canseestats = has_capability('mod/moodleoverflow:viewanyrating', $context) && get_config('moodleoverflow', 'showuserstats');
    $cantrack = readtracking::can_track_moodleoverflows($moodleoverflow);
    $istracked = $cantrack && readtracking::moodleoverflow_is_tracked($moodleoverflow);

    // Create links.
    $startdiscussion = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $moodleoverflow->id]);
    $markallreadlink = new moodle_url("/mod/moodleoverflow/markposts.php?m=$moodleoverflow->id");

    // Get information about the moodleoverflow. This includes: discussioncount, unread posts, discussions its replies .
    $discussioncount = moodleoverflow_get_discussions_count($cm);
    $unreads = $istracked ? moodleoverflow_get_discussions_unread($cm) : [];

    // Get moodleoverflow where discussions can be moved.
    $destinations = [];
    $instances = get_fast_modinfo($moodleoverflow->course)->get_instances_of('moodleoverflow');
    $params = ['course' => $moodleoverflow->course, 'anonymous' => $moodleoverflow->anonymous, 'currentid' => $moodleoverflow->id];
    $sql = "SELECT *
            FROM {moodleoverflow}
            WHERE course = :course
                AND anonymous >= :anonymous
                AND id != :currentid";
    foreach ($DB->get_records_sql($sql, $params) as $modflow) {
        if (empty($instances[$modflow->id]->deletioninprogress)) {
            $destinations[] = ['name' => $modflow->name, 'modflowid' => $modflow->id];
        }
    }

    // Iterate through every visible discussion.
    $canreview = capabilities::has(capabilities::REVIEW_POST, $context) ? 1 : 0;
    $items = [];
    $sql = "SELECT d.*
            FROM {moodleoverflow_discussions} d
            JOIN {moodleoverflow_posts} p ON p.discussion = d.id
            WHERE d.moodleoverflow = ?
                AND p.parent = 0
                AND (? = 1 OR (p.reviewed = 1 OR p.userid = ?))
            ORDER BY d.timestart DESC, d.id DESC";
    $discussions = $DB->get_records_sql($sql, [$moodleoverflow->id, $canreview, $USER->id], $limitfrom, $limitamount);
    foreach ($discussions as $discussion) {
        $items[] = $OUTPUT->render(new discussion_card(discussion::from_record($discussion), $context, !empty($destinations)));
    }

    // Collect the needed data being submitted to the template.
    $mustachedata = (object) [
        'discussions' => $items,
        'hasdiscussions' => count($discussions) >= 0,
        'startdiscussion' => $canstartdiscussion ? ['link' => $startdiscussion->out()] : [],
        'markallread' => $unreads ? ['link' => $markallreadlink->out()] : [],
        'stats' => $canseestats ? ['link' => (new moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $cm->id]))->out()] : [],
        'paging_bar' => ($page != -1) ? $OUTPUT->paging_bar($discussioncount, $page, $perpage, "view.php?id=$cm->id") : false,
        'destinations' => $destinations,
    ];

    // Print the template.
    $PAGE->requires->js_call_amd('mod_moodleoverflow/topicmove', 'init');
    echo $PAGE->get_renderer('mod_moodleoverflow')->render_discussion_list($mustachedata);
}

/**
 * Returns the amount of discussions of the given context module.
 *
 * @param object $cm
 *
 * @return array
 */
function moodleoverflow_get_discussions_count($cm) {
    global $DB, $USER;

    $modcontext = context_module::instance($cm->id);
    $params = [$cm->instance];
    $whereconditions = ['d.moodleoverflow = ?', 'p.parent = 0'];

    if (!has_capability('mod/moodleoverflow:reviewpost', $modcontext)) {
        $whereconditions[] = '(p.reviewed = 1 OR p.userid = ?)';
        $params[] = $USER->id;
    }

    $wheresql = join(' AND ', $whereconditions);

    $sql = 'SELECT COUNT(d.id)
              FROM {moodleoverflow_discussions} d
                   JOIN {moodleoverflow_posts} p ON p.discussion = d.id
             WHERE ' . $wheresql;

    return $DB->get_field_sql($sql, $params);
}

/**
 * Returns an array of unread messages for the current user.
 *
 * @param object $cm
 *
 * @return array
 */
function moodleoverflow_get_discussions_unread($cm) {
    global $DB, $USER;

    // Get the current timestamp and the oldpost-timestamp.
    $now = round(time(), -2);
    $cutoffdate = $now - (get_config('moodleoverflow', 'oldpostdays') * 24 * 60 * 60);

    $modcontext = context_module::instance($cm->id);

    $whereconditions = ['d.moodleoverflow = :instance', 'p.modified >= :cutoffdate', 'r.id is NULL'];
    $params = [
            'userid' => $USER->id,
            'instance' => $cm->instance,
            'cutoffdate' => $cutoffdate,
    ];

    if (!has_capability('mod/moodleoverflow:reviewpost', $modcontext)) {
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

    // Return the unread messages as an array.
    if ($unreads = $DB->get_records_sql($sql, $params)) {
        $returnarray = [];
        foreach ($unreads as $unread) {
            $returnarray[$unread->id] = $unread->unread;
        }
        return $returnarray;
    } else {
        // If there are no unread messages, return an empty array.
        return [];
    }
}

/**
 * Gets a post with all info ready for moodleoverflow_print_post.
 * Most of these joins are just to get the forum id.
 *
 * @param int $postid
 *
 * @return mixed array of posts or false
 */
function moodleoverflow_get_post_full($postid) {
    global $DB;
    $allnames = fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT p.*, d.moodleoverflow, $allnames, u.email, u.picture, u.imagealt
              FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
              LEFT JOIN {user} u ON p.userid = u.id
                  WHERE p.id = :postid";
    $params = [];
    $params['postid'] = $postid;

    $post = $DB->get_record_sql($sql, $params);
    if ($post->userid === 0) {
        $post->message = get_string('privacy:anonym_post_message', 'mod_moodleoverflow');
    }

    return $post;
}

/**
 * Checks if a user can see a specific post.
 *
 * @param object $moodleoverflow
 * @param object $discussion
 * @param object $post
 * @param object $cm
 * @param int $userid
 *
 * @return bool
 */
function moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm, $userid = null) {
    global $USER, $DB;
    if ($userid === null) {
        $userid = $USER->id;
    }

    // Retrieve the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Fetch the moodleoverflow instance object.
    if (is_numeric($moodleoverflow)) {
        debugging('missing full moodleoverflow', DEBUG_DEVELOPER);
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow])) {
            return false;
        }
    }

    // Fetch the discussion object.
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion])) {
            return false;
        }
    }

    // Fetch the post object.
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('moodleoverflow_posts', ['id' => $post])) {
            return false;
        }
    }

    // Get the postid if not set.
    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    // Find the coursemodule.
    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
    }

    // Check if the user can view the discussion.
    if (!capabilities::has(capabilities::VIEW_DISCUSSION, $modulecontext, $userid)) {
        return false;
    }

    if (
        !($post->reviewed == 1 || $post->userid == $userid ||
        capabilities::has(capabilities::REVIEW_POST, $modulecontext, $userid))
    ) {
        return false;
    }

    // The user has the capability to see the discussion.
    return \core_availability\info_module::is_user_visible($cm, $userid, false);
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

    // If not user is submitted, use the current one.
    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Check the users capability.
    if (!has_capability('mod/moodleoverflow:replypost', $modulecontext, $userid)) {
        return false;
    }
    return !$considerreviewstatus || $posttoreplyto->reviewed == 1;
}

/**
 * Get all posts in discussion including the starting post.
 *
 * @param int     $discussionid The ID of the discussion
 * @param boolean $tracking     Whether tracking is activated
 * @param context_module $modcontext Context of the module
 *
 * @return array
 */
function moodleoverflow_get_all_discussion_posts($discussionid, $tracking, $modcontext) {
    global $DB, $USER;

    // Initiate tracking settings.
    $trackingselector = "";
    $trackingjoin = "";
    $params = [];

    // If tracking is enabled, another join is needed.
    if ($tracking) {
        $trackingselector = ", mr.id AS postread";
        $trackingjoin = "LEFT JOIN {moodleoverflow_read} mr ON (mr.postid = p.id AND mr.userid = :userid)";
        $params['userid'] = $USER->id;
    }

    // Get all username fields.
    $allnames = fields::for_name()->get_sql('u', false, '', '', false)->selects;

    $additionalwhere = '';

    if (!has_capability('mod/moodleoverflow:reviewpost', $modcontext)) {
        $additionalwhere = ' AND (p.reviewed = 1 OR p.userid = :userid2) ';
        $params['userid2'] = $USER->id;
    }

    // Create the sql array.
    $sql = "SELECT p.*, m.ratingpreference, $allnames, d.name as subject, u.email, u.picture, u.imagealt $trackingselector
              FROM {moodleoverflow_posts} p
                   LEFT JOIN {user} u ON p.userid = u.id
                   LEFT JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                   LEFT JOIN {moodleoverflow} m on m.id = d.moodleoverflow
                   $trackingjoin
             WHERE p.discussion = :discussion $additionalwhere
          ORDER BY p.created ASC";
    $params['discussion'] = $discussionid;

    // Return an empty array, if there are no posts.
    if (!$posts = $DB->get_records_sql($sql, $params)) {
        return [];
    }

    // Load all ratings.
    $discussionratings = ratings::moodleoverflow_get_ratings_by_discussion($discussionid);

    // Assign ratings to the posts.
    foreach ($posts as $postid => $post) {
        // Assign the ratings to the matching posts.
        $posts[$postid]->upvotes = $discussionratings[$post->id]->upvotes;
        $posts[$postid]->downvotes = $discussionratings[$post->id]->downvotes;
        $posts[$postid]->votesdifference = $posts[$postid]->upvotes - $posts[$postid]->downvotes;
        $posts[$postid]->markedhelpful = $discussionratings[$post->id]->ishelpful;
        $posts[$postid]->markedsolution = $discussionratings[$post->id]->issolved;
    }

    // Order the answers by their ratings.
    $posts = ratings::moodleoverflow_sort_answers_by_ratings($posts);

    // Find all children of this post.
    foreach ($posts as $postid => $post) {
        // Is it an old post?
        if ($tracking) {
            if (readtracking::moodleoverflow_is_old_post($post)) {
                $posts[$postid]->postread = true;
            }
        }

        // Don't iterate through the parent post.
        if (!$post->parent) {
            $posts[$postid]->level = 0;
            continue;
        }

        // If the parent post does not exist.
        if (!isset($posts[$post->parent])) {
            continue;
        }

        // Create the children array.
        if (!isset($posts[$post->parent]->children)) {
            $posts[$post->parent]->children = [];
        }

        // Increase the level of the current post.
        $posts[$post->parent]->children[$postid] =& $posts[$postid];
    }

    // Return the object.
    return $posts;
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
        $grade = $postuserrating / $moodleoverflow->gradescalefactor;

        if ($grade > $moodleoverflow->grademaxgrade) {
            $grade = $moodleoverflow->grademaxgrade;
        }

        // Save updated grade on local table.
        if ($DB->record_exists('moodleoverflow_grades', ['userid' => $postinguser, 'moodleoverflowid' => $moodleoverflow->id])) {
            $DB->set_field('moodleoverflow_grades', 'grade', $grade, ['userid' => $postinguser,
                'moodleoverflowid' => $moodleoverflow->id, ]);
        } else {
            $gradedataobject = new stdClass();
            $gradedataobject->moodleoverflowid = $moodleoverflow->id;
            $gradedataobject->userid = $postinguser;
            $gradedataobject->grade = $grade;
            $DB->insert_record('moodleoverflow_grades', $gradedataobject, false);
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
        if ($coreexception) {
            throw new moodle_exception($exceptionstring);
        } else {
            throw new moodle_exception($exceptionstring, 'moodleoverflow');
        }
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
        if ($coreexception) {
            throw new moodle_exception($errorcode);
        } else {
            throw new moodle_exception($errorcode, 'moodleoverflow');
        }
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
            redirect(new \moodle_url('/enrol/index.php', [
                'id' => $courseid,
                'returnurl' => $returnurl,
            ]), get_string('youneedtoenrol'));
        }
    }
}
