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

use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\event\post_deleted;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\review;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Get all discussions in a moodleoverflow instance.
 *
 * @param object $cm
 * @param int $page
 * @param int $perpage
 *
 * @return array
 */
function moodleoverflow_get_discussions($cm, $page = -1, $perpage = 0) {
    global $DB, $CFG, $USER;

    // TODO Refactor variable naming. $discussion->id is first post and $discussion->discussion is discussion id?

    // User must have the permission to view the discussions.
    $modcontext = context_module::instance($cm->id);
    if (!capabilities::has(capabilities::VIEW_DISCUSSION, $modcontext)) {
        return array();
    }

    // Filter some defaults.
    if ($perpage <= 0) {
        $limitfrom = 0;
        $limitamount = $perpage;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitamount = $perpage;
    } else {
        $limitfrom = 0;
        $limitamount = 0;
    }

    // Get all name fields as sql string snippet.
    if ($CFG->branch >= 311) {
        $allnames = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    } else {
        $allnames = get_all_user_name_fields(true, 'u');
    }
    $postdata = 'p.id, p.modified, p.discussion, p.userid, p.reviewed';
    $discussiondata = 'd.name, d.timemodified, d.timestart, d.usermodified, d.firstpost';
    $userdata = 'u.email, u.picture, u.imagealt';

    if ($CFG->branch >= 311) {
        $usermodifiedfields = \core_user\fields::for_name()->get_sql('um', false, 'um',
                '', false)->selects .
            ', um.email AS umemail, um.picture AS umpicture, um.imagealt AS umimagealt';
    } else {
        $usermodifiedfields = get_all_user_name_fields(true, 'um', null, 'um') .
            ', um.email AS umemail, um.picture AS umpicture, um.imagealt AS umimagealt';
    }

    $params = [$cm->instance];
    $whereconditions = ['d.moodleoverflow = ?', 'p.parent = 0'];

    if (!capabilities::has(capabilities::REVIEW_POST, $modcontext)) {
        $whereconditions[] = '(p.reviewed = 1 OR p.userid = ?)';
        $params[] = $USER->id;
    }

    $wheresql = join(' AND ', $whereconditions);

    // Retrieve and return all discussions from the database.
    $sql = "SELECT $postdata, $discussiondata, $allnames, $userdata, $usermodifiedfields
              FROM {moodleoverflow_discussions} d
                   JOIN {moodleoverflow_posts} p ON p.discussion = d.id
                   LEFT JOIN {user} u ON p.userid = u.id
                   LEFT JOIN {user} um ON d.usermodified = um.id
              WHERE $wheresql
           ORDER BY d.timestart DESC, d.id DESC";

    return $DB->get_records_sql($sql, $params, $limitfrom, $limitamount);
}

/**
 * Prints latest moodleoverflow discussions.
 *
 * @param object $moodleoverflow MoodleOverflow to be printed.
 * @param object $cm
 * @param int    $page           Page mode, page to display (optional).
 * @param int    $perpage        The maximum number of discussions per page (optional).
 */
function moodleoverflow_print_latest_discussions($moodleoverflow, $cm, $page = -1, $perpage = 25) {
    global $CFG, $USER, $OUTPUT, $PAGE;

    // Check if the course supports the module.
    if (!$cm) {
        if (!$cm = get_course_and_cm_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
    }

    // Set the context.
    $context = context_module::instance($cm->id);

    // If the perpage value is invalid, deactivate paging.
    if ($perpage <= 0) {
        $perpage = 0;
        $page = -1;
    }

    // Check some capabilities.
    $canstartdiscussion = moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $context);
    $canviewdiscussions = has_capability('mod/moodleoverflow:viewdiscussion', $context);
    $canreviewposts = has_capability('mod/moodleoverflow:reviewpost', $context);
    $canseeuserstats = has_capability('mod/moodleoverflow:viewanyrating', $context);

    // Print a button if the user is capable of starting
    // a new discussion or if the selfenrol is aviable.
    if ($canstartdiscussion) {
        $buttontext = get_string('addanewdiscussion', 'moodleoverflow');
        $buttonurl = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $moodleoverflow->id]);
        $button = new single_button($buttonurl, $buttontext, 'get');
        $button->class = 'singlebutton align-middle m-2';
        $button->formid = 'newdiscussionform';
        echo $OUTPUT->render($button);
    }

    // Print a button if the user is capable of seeing the user stats.
    if ($canseeuserstats && get_config('moodleoverflow', 'showuserstats')) {
        $userstatsbuttontext = get_string('seeuserstats', 'moodleoverflow');
        $userstatsbuttonurl = new moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $cm->id,
                                                                           'courseid' => $moodleoverflow->course,
                                                                           'mid' => $moodleoverflow->id]);
        $userstatsbutton = new single_button($userstatsbuttonurl, $userstatsbuttontext, 'get');
        $userstatsbutton->class = 'singlebutton align-middle m-2';
        echo $OUTPUT->render($userstatsbutton);
    }

    // Get all the recent discussions the user is allowed to see.
    $discussions = moodleoverflow_get_discussions($cm, $page, $perpage);

    // Get the number of replies for each discussion.
    $replies = moodleoverflow_count_discussion_replies($cm);

    // Check whether the moodleoverflow instance can be tracked and is tracked.
    if ($cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow)) {
        $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);
    } else {
        $istracked = false;
    }

    // Get an array of unread messages for the current user if the moodleoverflow instance is tracked.
    if ($istracked) {
        $unreads = moodleoverflow_get_discussions_unread($cm);
        $markallread = $CFG->wwwroot . '/mod/moodleoverflow/markposts.php?m=' . $moodleoverflow->id;
    } else {
        $unreads = array();
        $markallread = null;
    }

    if ($markallread && $unreads) {
        echo html_writer::link(new moodle_url($markallread),
            get_string('markallread_forum', 'mod_moodleoverflow'),
            ['class' => 'btn btn-secondary my-2']
        );
    }

    // Get all the recent discussions the user is allowed to see.
    $discussions = moodleoverflow_get_discussions($cm, $page, $perpage);

    // If we want paging.
    if ($page != -1) {

        // Get the number of discussions.
        $numberofdiscussions = moodleoverflow_get_discussions_count($cm);

        // Show the paging bar.
        echo $OUTPUT->paging_bar($numberofdiscussions, $page, $perpage, "view.php?id=$cm->id");
    }

    // Get the number of replies for each discussion.
    $replies = moodleoverflow_count_discussion_replies($cm);

    // Check whether the user can subscribe to the discussion.
    $cansubtodiscussion = false;
    if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/moodleoverflow:viewdiscussion', $context)
                                    && \mod_moodleoverflow\subscriptions::is_subscribable($moodleoverflow, $context)) {
        $cansubtodiscussion = true;
    }

    // Check wether the user can move a topic.
    $canmovetopic = false;
    if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/moodleoverflow:movetopic', $context)) {
        $canmovetopic = true;
    }

    // Iterate through every visible discussion.
    $i = 0;
    $preparedarray = array();
    foreach ($discussions as $discussion) {
        $preparedarray[$i] = array();

        // Handle anonymized discussions.
        if ($discussion->userid == 0) {
            $discussion->name = get_string('privacy:anonym_discussion_name', 'mod_moodleoverflow');
        }

        // Set the amount of replies for every discussion.
        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // Set the right text.
        $preparedarray[$i]['answertext'] = ($discussion->replies == 1) ? 'answer' : 'answers';

        // Set the amount of unread messages for each discussion.
        if (!$istracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        // Check if the question owner marked the question as helpful.
        $markedhelpful = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->discussion, false);
        $preparedarray[$i]['starterlink'] = null;
        if ($markedhelpful) {
            $link = '/mod/moodleoverflow/discussion.php?d=';
            $markedhelpful = $markedhelpful[array_key_first($markedhelpful)];

            $preparedarray[$i]['starterlink'] = new moodle_url($link .
                $markedhelpful->discussionid . '#p' . $markedhelpful->postid);
        }

        // Check if a teacher marked a post as solved.
        $markedsolution = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->discussion, true);
        $preparedarray[$i]['teacherlink'] = null;
        if ($markedsolution) {
            $link = '/mod/moodleoverflow/discussion.php?d=';
            $markedsolution = $markedsolution[array_key_first($markedsolution)];

            $preparedarray[$i]['teacherlink'] = new moodle_url($link .
                $markedsolution->discussionid . '#p' . $markedsolution->postid);
        }

        // Check if a single post was marked by the question owner and a teacher.
        $statusboth = false;
        if ($markedhelpful && $markedsolution) {
            if ($markedhelpful->postid == $markedsolution->postid) {
                $statusboth = true;
            }
        }

        // Get the amount of votes for the discussion.
        $votes = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($discussion->discussion, $discussion->id);
        $votes = $votes->upvotes - $votes->downvotes;
        $preparedarray[$i]['votetext'] = ($votes == 1) ? 'vote' : 'votes';

        // Use the discussions name instead of the subject of the first post.
        $discussion->subject = $discussion->name;

        // Format the subjectname and the link to the topic.
        $preparedarray[$i]['subjecttext'] = format_string($discussion->subject);
        $preparedarray[$i]['subjectlink'] = $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $discussion->discussion;

        // Get information about the user who started the discussion.
        $startuser = new stdClass();
        if ($CFG->branch >= 311) {
            $startuserfields = \core_user\fields::get_picture_fields();
        } else {
            $startuserfields = explode(',', user_picture::fields());
        }

        $startuser = username_load_fields_from_object($startuser, $discussion, null, $startuserfields);
        $startuser->id = $discussion->userid;

        // Discussion was anonymized.
        if ($startuser->id == 0 || $moodleoverflow->anonymous != anonymous::NOT_ANONYMOUS) {
            // Get his picture, his name and the link to his profile.
            if ($startuser->id == $USER->id) {
                $preparedarray[$i]['username'] = get_string('anonym_you', 'mod_moodleoverflow');
                // Needs to be included for reputation to update properly.
                $preparedarray[$i]['userlink'] = $CFG->wwwroot . '/user/view.php?id=' .
                    $discussion->userid . '&course=' . $moodleoverflow->course;

            } else {
                $preparedarray[$i]['username'] = get_string('privacy:anonym_user_name', 'mod_moodleoverflow');
                $preparedarray[$i]['userlink'] = null;
            }
        } else {
            // Get his picture, his name and the link to his profile.
            $preparedarray[$i]['picture'] = $OUTPUT->user_picture($startuser, array('courseid' => $moodleoverflow->course,
                                                                                    'link' => false));
            $preparedarray[$i]['username'] = fullname($startuser, has_capability('moodle/site:viewfullnames', $context));
            $preparedarray[$i]['userlink'] = $CFG->wwwroot . '/user/view.php?id=' .
                $discussion->userid . '&course=' . $moodleoverflow->course;
        }

        // Get the amount of replies and the link to the discussion.
        $preparedarray[$i]['replyamount'] = $discussion->replies;
        $preparedarray[$i]['questionunderreview'] = $discussion->reviewed == 0;

        // Are there unread messages? Create a link to them.
        $preparedarray[$i]['unreadamount'] = $discussion->unread;
        $preparedarray[$i]['unread'] = ($preparedarray[$i]['unreadamount'] > 0) ? true : false;
        $preparedarray[$i]['unreadlink'] = $CFG->wwwroot .
            '/mod/moodleoverflow/discussion.php?d=' . $discussion->discussion . '#unread';
        $link = '/mod/moodleoverflow/markposts.php?m=';
        $preparedarray[$i]['markreadlink'] = $CFG->wwwroot . $link . $moodleoverflow->id . '&d=' . $discussion->discussion;

        // Check the date of the latest post. Just in case the database is not consistent.
        $usedate = (empty($discussion->timemodified)) ? $discussion->modified : $discussion->timemodified;

        // Get the name and the link to the profile of the user, that is related to the latest post.
        $usermodified = new stdClass();
        $usermodified->id = $discussion->usermodified;

        if ($usermodified->id == 0 || $moodleoverflow->anonymous) {
            if ($usermodified->id == $USER->id) {
                $preparedarray[$i]['lastpostusername'] = null;
                $preparedarray[$i]['lastpostuserlink'] = null;
            } else {
                $preparedarray[$i]['lastpostusername'] = null;
                $preparedarray[$i]['lastpostuserlink'] = null;
            }
        } else {
            $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um');
            $preparedarray[$i]['lastpostusername'] = fullname($usermodified);
            $preparedarray[$i]['lastpostuserlink'] = $CFG->wwwroot . '/user/view.php?id=' .
                $discussion->usermodified . '&course=' . $moodleoverflow->course;
        }

        // Get the date of the latest post of the discussion.
        $parenturl = (empty($discussion->lastpostid)) ? '' : '&parent=' . $discussion->lastpostid;
        $preparedarray[$i]['lastpostdate'] = userdate($usedate, get_string('strftimerecentfull'));
        $preparedarray[$i]['lastpostlink'] = $preparedarray[$i]['subjectlink'] . $parenturl;

        // Check whether the discussion is subscribed.
        $preparedarray[$i]['discussionsubicon'] = false;
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/moodleoverflow:viewdiscussion', $context)) {
            // Discussion subscription.
            if (\mod_moodleoverflow\subscriptions::is_subscribable($moodleoverflow, $context)) {
                $preparedarray[$i]['discussionsubicon'] = \mod_moodleoverflow\subscriptions::get_discussion_subscription_icon(
                    $moodleoverflow, $context, $discussion->discussion);
            }
        }

        if ($canreviewposts) {
            $reviewinfo = review::get_short_review_info_for_discussion($discussion->discussion);
            $preparedarray[$i]['needreview'] = $reviewinfo->count;
            $preparedarray[$i]['reviewlink'] = (new moodle_url('/mod/moodleoverflow/discussion.php', [
                'd' => $discussion->discussion
            ], 'p' . $reviewinfo->first))->out(false);
        }

        // Build linktopopup to move a topic.
        $linktopopup = $CFG->wwwroot . '/mod/moodleoverflow/view.php?id=' . $cm->id . '&movetopopup=' . $discussion->discussion;
        $preparedarray[$i]['linktopopup'] = $linktopopup;

        // Add all created data to an array.
        $preparedarray[$i]['markedhelpful'] = $markedhelpful;
        $preparedarray[$i]['markedsolution'] = $markedsolution;
        $preparedarray[$i]['statusboth'] = $statusboth;
        $preparedarray[$i]['votes'] = $votes;

        // Did the user rated this post?
        $rating = \mod_moodleoverflow\ratings::moodleoverflow_user_rated($discussion->firstpost);

        $firstpost = moodleoverflow_get_post_full($discussion->firstpost);

        $preparedarray[$i]['userupvoted'] = ($rating->rating ?? null) == RATING_UPVOTE;
        $preparedarray[$i]['userdownvoted'] = ($rating->rating ?? null) == RATING_DOWNVOTE;
        $preparedarray[$i]['canchange'] = \mod_moodleoverflow\ratings::moodleoverflow_user_can_rate($firstpost, $context) &&
                $startuser->id != $USER->id;
        $preparedarray[$i]['postid'] = $discussion->firstpost;

        // Go to the next discussion.
        $i++;
    }

    // Include the renderer.
    $renderer = $PAGE->get_renderer('mod_moodleoverflow');

    // Collect the needed data being submitted to the template.
    $mustachedata = new stdClass();
    $mustachedata->cantrack = $cantrack;
    $mustachedata->canviewdiscussions = $canviewdiscussions;
    $mustachedata->canreview = $canreviewposts;
    $mustachedata->discussions = $preparedarray;
    $mustachedata->hasdiscussions = (count($discussions) >= 0) ? true : false;
    $mustachedata->istracked = $istracked;
    $mustachedata->markallread = $markallread;
    $mustachedata->cansubtodiscussion = $cansubtodiscussion;
    $mustachedata->canmovetopic = $canmovetopic;
    $mustachedata->cannormoveorsub = ((!$canmovetopic) && (!$cansubtodiscussion));
    // Print the template.
    echo $renderer->render_discussion_list($mustachedata);

    // Show the paging bar if paging is activated.
    if ($page != -1) {
        echo $OUTPUT->paging_bar($numberofdiscussions, $page, $perpage, "view.php?id=$cm->id");
    }
}

/**
 * Prints a popup with a menu of other moodleoverflow in the course.
 * Menu to move a topic to another moodleoverflow forum.
 *
 * @param object $course
 * @param object $cm
 * @param int $movetopopup forum where forum list is being printed.
 */
function moodleoverflow_print_forum_list($course, $cm, $movetopopup) {
    global $CFG, $DB, $PAGE;
    $forumarray = array(array());
    $currentforum = $DB->get_record('moodleoverflow_discussions', array('id' => $movetopopup), 'moodleoverflow');
    $currentdiscussion = $DB->get_record('moodleoverflow_discussions', array('id' => $movetopopup), 'name');
    $forums = $DB->get_records('moodleoverflow', array('course' => $course->id));
    $amountforums = count($forums);

    if ($amountforums > 1) {
        // Write the moodleoverflow-names in an array.
        $i = 0;
        foreach ($forums as $forum) {
            if ($forum->id == $currentforum->moodleoverflow) {
                continue;
            } else {
                $forumarray[$i]['name'] = $forum->name;
                $movetoforum = $CFG->wwwroot . '/mod/moodleoverflow/view.php?id=' . $cm->id . '&movetopopup='
                                             . $movetopopup . '&movetoforum=' . $forum->id;
                $forumarray[$i]['movetoforum'] = $movetoforum;
            }
            $i++;
        }
        $amountforums = true;
    } else {
        $amountforums = false;
    }

    // Build popup.
    $renderer = $PAGE->get_renderer('mod_moodleoverflow');
    $mustachedata = new stdClass();
    $mustachedata->hasforums = $amountforums;
    $mustachedata->forums = $forumarray;
    $mustachedata->currentdiscussion = $currentdiscussion->name;
    echo $renderer->render_forum_list($mustachedata);
}


/**
 * Returns an array of counts of replies for each discussion.
 *
 * @param object $cm coursemodule
 *
 * @return array
 */
function moodleoverflow_count_discussion_replies($cm) {
    global $DB, $USER;

    $modcontext = context_module::instance($cm->id);

    $params = [$cm->instance];
    $whereconditions = ['d.moodleoverflow = ?', 'p.parent > 0'];

    if (!has_capability('mod/moodleoverflow:reviewpost', $modcontext)) {
        $whereconditions[] = '(p.reviewed = 1 OR p.userid = ?)';
        $params[] = $USER->id;
    }

    $wheresql = join(' AND ', $whereconditions);

    $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
              FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
             WHERE $wheresql
          GROUP BY p.discussion";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Check if the user is capable of starting a new discussion.
 *
 * @param object $moodleoverflow
 * @param object $cm
 * @param object $context
 *
 * @return bool
 */
function moodleoverflow_user_can_post_discussion($moodleoverflow, $cm = null, $context = null) {

    // Guests an not-logged-in users can not psot.
    if (isguestuser() || !isloggedin()) {
        return false;
    }

    // Get the coursemodule.
    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
    }

    // Get the context if not set in the parameters.
    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // Check the capability.
    if (has_capability('mod/moodleoverflow:startdiscussion', $context)) {
        return true;
    } else {
        return false;
    }
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
            'cutoffdate' => $cutoffdate
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
        return array();
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
    global $DB, $CFG;

    if ($CFG->branch >= 311) {
        $allnames = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    } else {
        $allnames = get_all_user_name_fields(true, 'u');
    }
    $sql = "SELECT p.*, d.moodleoverflow, $allnames, u.email, u.picture, u.imagealt
              FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
              LEFT JOIN {user} u ON p.userid = u.id
                  WHERE p.id = :postid";
    $params = array();
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
 *
 * @return bool
 */
function moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm) {
    global $USER, $DB;

    // Retrieve the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Fetch the moodleoverflow instance object.
    if (is_numeric($moodleoverflow)) {
        debugging('missing full moodleoverflow', DEBUG_DEVELOPER);
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
            return false;
        }
    }

    // Fetch the discussion object.
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $discussion))) {
            return false;
        }
    }

    // Fetch the post object.
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('moodleoverflow_posts', array('id' => $post))) {
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
    if (!capabilities::has(capabilities::VIEW_DISCUSSION, $modulecontext)) {
        return false;
    }

    if (!($post->reviewed == 1 || $post->userid == $USER->id ||
        capabilities::has(capabilities::REVIEW_POST, $modulecontext))) {
        return false;
    }

    // The user has the capability to see the discussion.
    return \core_availability\info_module::is_user_visible($cm, $USER->id, false);

}

/**
 * Check if a user can see a specific discussion.
 *
 * @param object $moodleoverflow
 * @param object $discussion
 * @param context $context
 *
 * @return bool
 */
function moodleoverflow_user_can_see_discussion($moodleoverflow, $discussion, $context) {
    global $DB;

    // Retrieve the moodleoverflow object.
    if (is_numeric($moodleoverflow)) {
        debugging('missing full moodleoverflow', DEBUG_DEVELOPER);
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
            return false;
        }
    }

    // Retrieve the discussion object.
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $discussion))) {
            return false;
        }
    }

    // Retrieve the coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    return capabilities::has(capabilities::VIEW_DISCUSSION, $context);
}

/**
 * Creates a new moodleoverflow discussion.
 *
 * @param stdClass $discussion The discussion object
 * @param context_module $modulecontext
 * @param int      $userid     The user ID
 *
 * @return bool|int The id of the created discussion
 */
function moodleoverflow_add_discussion($discussion, $modulecontext, $userid = null) {
    global $DB, $USER;

    // Get the current time.
    $timenow = time();

    // Get the current user.
    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post of the discussion is stored
    // as a real post. The discussion links to it.

    // Retrieve the module instance.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
        return false;
    }

    // Retrieve the coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Create the post-object.
    $post = new stdClass();
    $post->discussion = 0;
    $post->parent = 0;
    $post->userid = $userid;
    $post->created = $timenow;
    $post->modified = $timenow;
    $post->message = $discussion->message;
    $post->attachments = $discussion->attachments;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->course = $moodleoverflow->course;

    // Set to not reviewed, if questions should be reviewed, and user is not a reviewer themselves.
    if (review::get_review_level($moodleoverflow) >= review::QUESTIONS &&
            !capabilities::has(capabilities::REVIEW_POST, $modulecontext, $userid)) {
        $post->reviewed = 0;
    }

    // Submit the post to the database and get its id.
    $post->id = $DB->insert_record('moodleoverflow_posts', $post);

    // Create the discussion object.
    $discussionobject = new stdClass();
    $discussionobject->course = $discussion->course;
    $discussionobject->moodleoverflow = $discussion->moodleoverflow;
    $discussionobject->name = $discussion->name;
    $discussionobject->firstpost = $post->id;
    $discussionobject->userid = $post->userid;
    $discussionobject->timemodified = $timenow;
    $discussionobject->timestart = $timenow;
    $discussionobject->usermodified = $post->userid;

    // Submit the discussion to the database and get its id.
    $post->discussion = $DB->insert_record('moodleoverflow_discussions', $discussionobject);

    // Link the post to the discussion.
    $DB->set_field('moodleoverflow_posts', 'discussion', $post->discussion, array('id' => $post->id));

    moodleoverflow_add_attachment($post, $moodleoverflow, $cm);

    // Mark the created post as read.
    $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow);
    $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);
    if ($cantrack && $istracked) {
        readtracking::moodleoverflow_mark_post_read($post->userid, $post);
    }

    // Trigger event.
    $params = array(
        'context' => $modulecontext,
        'objectid' => $post->discussion,
    );

    $event = \mod_moodleoverflow\event\discussion_viewed::create($params);
    $event->trigger();

    // Return the id of the discussion.
    return $post->discussion;
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
 * @param object $modulecontext
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
 * Prints a moodleoverflow discussion.
 *
 * @param stdClass $course         The course object
 * @param object   $cm
 * @param stdClass $moodleoverflow The moodleoverflow object
 * @param stdClass $discussion     The discussion object
 * @param stdClass $post           The post object
 * @param bool     $multiplemarks  The setting of multiplemarks (default: multiplemarks are not allowed)
 */
function moodleoverflow_print_discussion($course, $cm, $moodleoverflow, $discussion, $post, $multiplemarks = false) {
    global $USER;
    // Check if the current is the starter of the discussion.
    $ownpost = (isloggedin() && ($USER->id == $post->userid));

    // Fetch the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Is the forum tracked?
    $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);

    // Retrieve all posts of the discussion.
    $posts = moodleoverflow_get_all_discussion_posts($discussion->id, $istracked, $modulecontext);

    $usermapping = anonymous::get_userid_mapping($moodleoverflow, $discussion->id);

    // Start with the parent post.
    $post = $posts[$post->id];

    $answercount = 0;

    // Lets clear all posts above level 2.
    // Check if there are answers.
    if (isset($post->children)) {

        // Itereate through all answers.
        foreach ($post->children as $aid => $a) {
            $answercount += 1;

            // Check for each answer if they have children as well.
            if (isset($post->children[$aid]->children)) {

                // Iterate through all comments.
                foreach ($post->children[$aid]->children as $cid => $c) {

                    // Delete the children of the comments.
                    if (isset($post->children[$aid]->children[$cid]->children)) {
                        unset($post->children[$aid]->children[$cid]->children);
                    }
                }
            }
        }
    }

    // Format the subject.
    $post->moodleoverflow = $moodleoverflow->id;
    $post->subject = format_string($post->subject);

    // Check if the post was read.
    $postread = !empty($post->postread);

    // Print the starting post.
    echo moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course,
        $ownpost, false, '', '', $postread, true, $istracked, 0, $usermapping, 0, $multiplemarks);

    // Print answer divider.
    if ($answercount == 1) {
        $answerstring = get_string('answer', 'moodleoverflow', $answercount);
    } else {
        $answerstring = get_string('answers', 'moodleoverflow', $answercount);
    }
    echo "<br><h2>$answerstring</h2>";

    echo '<div id="moodleoverflow-posts">';

    // Print the other posts.
    echo moodleoverflow_print_posts_nested($course, $cm, $moodleoverflow, $discussion, $post, $istracked, $posts,
        null, $usermapping, $multiplemarks);

    echo '</div>';
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
    global $DB, $USER, $CFG;

    // Initiate tracking settings.
    $params = array();
    $trackingselector = "";
    $trackingjoin = "";
    $params = array();

    // If tracking is enabled, another join is needed.
    if ($tracking) {
        $trackingselector = ", mr.id AS postread";
        $trackingjoin = "LEFT JOIN {moodleoverflow_read} mr ON (mr.postid = p.id AND mr.userid = :userid)";
        $params['userid'] = $USER->id;
    }

    // Get all username fields.
    if ($CFG->branch >= 311) {
        $allnames = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    } else {
        $allnames = get_all_user_name_fields(true, 'u');
    }

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
        return array();
    }

    // Load all ratings.
    $discussionratings = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($discussionid);

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
    $posts = \mod_moodleoverflow\ratings::moodleoverflow_sort_answers_by_ratings($posts);

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
            $posts[$post->parent]->children = array();
        }

        // Increase the level of the current post.
        $posts[$post->parent]->children[$postid] =& $posts[$postid];
    }

    // Return the object.
    return $posts;
}


/**
 * Prints a moodleoverflow post.
 * @param object $post
 * @param object $discussion
 * @param object $moodleoverflow
 * @param object $cm
 * @param object $course
 * @param object $ownpost
 * @param bool $link
 * @param string $footer
 * @param string $highlight
 * @param bool $postisread
 * @param bool $dummyifcantsee
 * @param bool $istracked
 * @param bool $iscomment
 * @param array $usermapping
 * @param int $level
 * @param bool $multiplemarks setting of multiplemarks
 * @return void|null
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course,
                                   $ownpost = false, $link = false,
                                   $footer = '', $highlight = '', $postisread = null,
                                   $dummyifcantsee = true, $istracked = false,
                                   $iscomment = false, $usermapping = [], $level = 0, $multiplemarks = false) {
    global $USER, $CFG, $OUTPUT, $PAGE;

    // Require the filelib.
    require_once($CFG->libdir . '/filelib.php');

    // String cache.
    static $str;

    // Print the 'unread' only on time.
    static $firstunreadanchorprinted = false;

    // Declare the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Post was anonymized.
    if ($post->userid == 0) {
        $post->message = get_string('privacy:anonym_post_message', 'mod_moodleoverflow');
    }

    // Add some informationto the post.
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $mcid = $modulecontext->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $mcid, 'mod_moodleoverflow', 'post', $post->id);

    // Check if the user has the capability to see posts.
    if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)) {

        // No dummy message is requested.
        if (!$dummyifcantsee) {
            echo '';

            return;
        }

        // Include the renderer to display the dummy content.
        $renderer = $PAGE->get_renderer('mod_moodleoverflow');

        // Collect the needed data being submitted to the template.
        $mustachedata = new stdClass();

        // Print the template.
        return $renderer->render_post_dummy_cantsee($mustachedata);
    }

    // Check if the strings have been cached.
    if (empty($str)) {
        $str = new stdClass();
        $str->edit = get_string('edit', 'moodleoverflow');
        $str->delete = get_string('delete', 'moodleoverflow');
        $str->reply = get_string('reply', 'moodleoverflow');
        $str->replyfirst = get_string('replyfirst', 'moodleoverflow');
        $str->parent = get_string('parent', 'moodleoverflow');
        $str->markread = get_string('markread', 'moodleoverflow');
        $str->markunread = get_string('markunread', 'moodleoverflow');
        $str->marksolved = get_string('marksolved', 'moodleoverflow');
        $str->alsomarksolved = get_string('alsomarksolved', 'moodleoverflow');
        $str->marknotsolved = get_string('marknotsolved', 'moodleoverflow');
        $str->markhelpful = get_string('markhelpful', 'moodleoverflow');
        $str->alsomarkhelpful = get_string('alsomarkhelpful', 'moodleoverflow');
        $str->marknothelpful = get_string('marknothelpful', 'moodleoverflow');
    }

    // Get the current link without unnecessary parameters.
    $discussionlink = new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $post->discussion));

    // Build the object that represents the posting user.
    $postinguser = new stdClass();
    if ($CFG->branch >= 311) {
        $postinguserfields = \core_user\fields::get_picture_fields();
    } else {
        $postinguserfields = explode(',', user_picture::fields());
    }
    $postinguser = username_load_fields_from_object($postinguser, $post, null, $postinguserfields);

    // Post was anonymized.
    if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
        $postinguser->id = null;
        if ($post->userid == $USER->id) {
            $postinguser->fullname = get_string('anonym_you', 'mod_moodleoverflow');
            $postinguser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));
        } else {
            $postinguser->fullname = $usermapping[(int) $post->userid];
            $postinguser->profilelink = null;
        }
    } else {
        $postinguser->fullname = fullname($postinguser, capabilities::has('moodle/site:viewfullnames', $modulecontext));
        $postinguser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));
        $postinguser->id = $post->userid;
    }

    // Prepare an array of commands.
    $commands = array();

    // Create a permalink.
    $permalink = new moodle_url($discussionlink);
    $permalink->set_anchor('p' . $post->id);

    // Check if multiplemarks are allowed, if so, check if there are already marked posts.
    $helpfulposts = false;
    $solvedposts = false;
    if ($multiplemarks) {
        $helpfulposts = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->id, false);
        $solvedposts = \mod_moodleoverflow\ratings::moodleoverflow_discussion_is_solved($discussion->id, true);
    }

    // If the user has started the discussion, he can mark the answer as helpful.
    $canmarkhelpful = (($USER->id == $discussion->userid) && ($USER->id != $post->userid) &&
        ($iscomment != $post->parent) && !empty($post->parent));
    if ($canmarkhelpful) {
        // When the post is already marked, remove the mark instead.
        $link = '/mod/moodleoverflow/discussion.php';
        if ($post->markedhelpful) {
            $commands[] = html_writer::tag('a', $str->marknothelpful,
                    array('class' => 'markhelpful onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'helpful'));
        } else {
            // If there are already marked posts, change the string of the button.
            if ($helpfulposts) {
                $commands[] = html_writer::tag('a', $str->alsomarkhelpful,
                    array('class' => 'markhelpful onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'helpful'));
            } else {
                $commands[] = html_writer::tag('a', $str->markhelpful,
                    array('class' => 'markhelpful onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'helpful'));
            }
        }
    }

    // A teacher can mark an answer as solved.
    $canmarksolved = (($iscomment != $post->parent) && !empty($post->parent)
                                                    && capabilities::has(capabilities::MARK_SOLVED, $modulecontext));
    if ($canmarksolved) {

        // When the post is already marked, remove the mark instead.
        $link = '/mod/moodleoverflow/discussion.php';
        if ($post->markedsolution) {
            $commands[] = html_writer::tag('a', $str->marknotsolved,
                    array('class' => 'marksolved onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'solved'));
        } else {
            // If there are already marked posts, change the string of the button.
            if ($solvedposts) {
                $commands[] = html_writer::tag('a', $str->alsomarksolved,
                    array('class' => 'marksolved onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'solved'));
            } else {
                $commands[] = html_writer::tag('a', $str->marksolved,
                    array('class' => 'marksolved onlyifreviewed', 'role' => 'button', 'data-moodleoverflow-action' => 'solved'));
            }
        }
    }

    // Calculate the age of the post.
    $age = time() - $post->created;

    // Make a link to edit your own post within the given time and not already reviewed.
    if (($ownpost && ($age < get_config('moodleoverflow', 'maxeditingtime')) &&
                    (!review::should_post_be_reviewed($post, $moodleoverflow) || !$post->reviewed))
        || capabilities::has(capabilities::EDIT_ANY_POST, $modulecontext)
    ) {
        $editurl = new moodle_url('/mod/moodleoverflow/post.php', array('edit' => $post->id));
        $commands[] = array('url' => $editurl, 'text' => $str->edit);
    }

    // Give the option to delete a post.
    $notold = ($age < get_config('moodleoverflow', 'maxeditingtime'));
    if (($ownpost && $notold && capabilities::has(capabilities::DELETE_OWN_POST, $modulecontext)) ||
        capabilities::has(capabilities::DELETE_ANY_POST, $modulecontext)) {

        $link = '/mod/moodleoverflow/post.php';
        $commands[] = array('url' => new moodle_url($link, array('delete' => $post->id)), 'text' => $str->delete);
    }

    // Give the option to reply to a post.
    if (moodleoverflow_user_can_post($modulecontext, $post, false)) {

        $attributes = [
                'class' => 'onlyifreviewed'
        ];

        // Answer to the parent post.
        if (empty($post->parent)) {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
            $commands[] = array('url' => $replyurl, 'text' => $str->replyfirst, 'attributes' => $attributes);

            // If the post is a comment, answer to the parent post.
        } else if (!$iscomment) {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
            $commands[] = array('url' => $replyurl, 'text' => $str->reply, 'attributes' => $attributes);

            // Else simple respond to the answer.
        } else {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $iscomment));
            $commands[] = array('url' => $replyurl, 'text' => $str->reply, 'attributes' => $attributes);
        }
    }

    // Initiate the output variables.
    $mustachedata = new stdClass();
    $mustachedata->istracked = $istracked;
    $mustachedata->isread = false;
    $mustachedata->isfirstunread = false;
    $mustachedata->isfirstpost = false;
    $mustachedata->iscomment = (!empty($post->parent) && ($iscomment == $post->parent));
    $mustachedata->permalink = $permalink;

    // Get the ratings.
    $mustachedata->votes = $post->upvotes - $post->downvotes;

    // Check if the post is marked.
    $mustachedata->markedhelpful = $post->markedhelpful;
    $mustachedata->markedsolution = $post->markedsolution;

    // Did the user rated this post?
    $rating = \mod_moodleoverflow\ratings::moodleoverflow_user_rated($post->id);

    // Initiate the variables.
    $mustachedata->userupvoted = false;
    $mustachedata->userdownvoted = false;
    $mustachedata->canchange = $USER->id != $post->userid;

    // Check the actual rating.
    if ($rating) {

        // Convert the object.
        $rating = $rating->rating;

        // Did the user upvoted or downvoted this post?
        // The user upvoted the post.
        if ($rating == 1) {
            $mustachedata->userdownvoted = true;
        } else if ($rating == 2) {
            $mustachedata->userupvoted = true;
        }
    }

    // Check the reading status of the post.
    $postclass = '';
    if ($istracked) {
        if ($postisread) {
            $postclass .= ' read';
            $mustachedata->isread = true;
        } else {
            $postclass .= ' unread';

            // Anchor the first unread post of a discussion.
            if (!$firstunreadanchorprinted) {
                $mustachedata->isfirstunread = true;
                $firstunreadanchorprinted = true;
            }
        }
    }
    if ($post->markedhelpful) {
        $postclass .= ' markedhelpful';
    }
    if ($post->markedsolution) {
        $postclass .= ' markedsolution';
    }
    $mustachedata->postclass = $postclass;

    // Is this the firstpost?
    if (empty($post->parent)) {
        $mustachedata->isfirstpost = true;
    }

    // Create an element for the user which posted the post.
    $postbyuser = new stdClass();
    $postbyuser->post = $post->subject;

    // Anonymization already handled in $postinguser->fullname.
    $postbyuser->user = $postinguser->fullname;

    $mustachedata->discussionby = get_string('postbyuser', 'moodleoverflow', $postbyuser);

    // Set basic variables of the post.
    $mustachedata->postid = $post->id;
    $mustachedata->subject = format_string($post->subject);

    // Post was anonymized.
    if (!anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
        // User picture.
        $mustachedata->picture = $OUTPUT->user_picture($postinguser, ['courseid' => $course->id]);
    }

    // The rating of the user.
    if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
        $postuserrating = null;
    } else {
        $postuserrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $postinguser->id);
    }

    // The name of the user and the date modified.
    $mustachedata->bydate = userdate($post->modified);
    $mustachedata->byshortdate = userdate($post->modified, get_string('strftimedatetimeshort', 'core_langconfig'));
    $mustachedata->byname = $postinguser->profilelink ?
        html_writer::link($postinguser->profilelink, $postinguser->fullname)
        : $postinguser->fullname;
    $mustachedata->byrating = $postuserrating;
    $mustachedata->byuserid = $postinguser->id;
    $mustachedata->showrating = $postuserrating !== null;
    if (get_config('moodleoverflow', 'allowdisablerating') == 1) {
        $mustachedata->showvotes = $moodleoverflow->allowrating;
        $mustachedata->showreputation = $moodleoverflow->allowreputation;
    } else {
        $mustachedata->showvotes = MOODLEOVERFLOW_RATING_ALLOW;
        $mustachedata->showreputation = MOODLEOVERFLOW_REPUTATION_ALLOW;
    }
    $mustachedata->questioner = $post->userid == $discussion->userid ? 'questioner' : '';

    // Set options for the post.
    $options = new stdClass();
    $options->para = false;
    $options->trusted = false;
    $options->context = $modulecontext;

    $reviewdelay = get_config('moodleoverflow', 'reviewpossibleaftertime');
    $mustachedata->reviewdelay = format_time($reviewdelay);
    $mustachedata->needsreview = !$post->reviewed;
    $reviewable = time() - $post->created > $reviewdelay;
    $mustachedata->canreview = capabilities::has(capabilities::REVIEW_POST, $modulecontext);
    $mustachedata->withinreviewperiod = $reviewable;

    // Prepare the post.
    $mustachedata->postcontent = format_text($post->message, $post->messageformat, $options, $course->id);

    // Load the attachments.
    $mustachedata->attachments = get_attachments($post, $cm);

    // Output the commands.
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text'], $command['attributes'] ?? null);
        } else {
            $commandhtml[] = $command;
        }
    }
    $mustachedata->commands = implode('', $commandhtml);

    // Print a footer if requested.
    $mustachedata->footer = $footer;

    // Mark the forum post as read.
    if ($istracked && !$postisread) {
        readtracking::moodleoverflow_mark_post_read($USER->id, $post);
    }

    $mustachedata->iscomment = $level == 2;

    // Include the renderer to display the dummy content.
    $renderer = $PAGE->get_renderer('mod_moodleoverflow');

    // Render the different elements.
    return $renderer->render_post($mustachedata);
}


/**
 * Prints all posts of the discussion in a nested form.
 *
 * @param object $course         The course object
 * @param object $cm
 * @param object $moodleoverflow The moodleoverflow object
 * @param object $discussion     The discussion object
 * @param object $parent         The object of the parent post
 * @param bool   $istracked      Whether the user tracks the discussion
 * @param array  $posts          Array of posts within the discussion
 * @param bool   $iscomment      Whether the current post is a comment
 * @param array $usermapping
 * @param bool  $multiplemarks
 * @return string
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function moodleoverflow_print_posts_nested($course, &$cm, $moodleoverflow, $discussion, $parent,
                                           $istracked, $posts, $iscomment = null, $usermapping = [], $multiplemarks = false) {
    global $USER;

    // Prepare the output.
    $output = '';

    // If there are answers.
    if (!empty($posts[$parent->id]->children)) {

        // We do not need the other parts of this variable anymore.
        $posts = $posts[$parent->id]->children;

        // Iterate through all answers.
        foreach ($posts as $post) {

            // Answers should be seperated from each other.
            // While comments should be indented.
            if (!isset($iscomment)) {
                $output .= "<div class='tmargin'>";
                $level = 1;
                $parentid = $post->id;
            } else {
                $output .= "<div class='indent'>";
                $level = 2;
                $parentid = $iscomment;
            }

            // Has the current user written the answer?
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            // Format the subject.
            $post->subject = format_string($post->subject);

            // Determine whether the post has been read by the current user.
            $postread = !empty($post->postread);

            // Print the answer.
            $output .= moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course,
                $ownpost, false, '', '', $postread, true, $istracked, $parentid, $usermapping, $level, $multiplemarks);

            // Print its children.
            $output .= moodleoverflow_print_posts_nested($course, $cm, $moodleoverflow,
                $discussion, $post, $istracked, $posts, $parentid, $usermapping, $multiplemarks);

            // End the div.
            $output .= "</div>\n";
        }
    }

    // Return the output.
    return $output;
}

/**
 * Returns attachments with information for the template
 *
 * @param object $post
 * @param object $cm
 *
 * @return array
 */
function get_attachments($post, $cm) {
    global $CFG, $OUTPUT;
    $attachments = array();

    if (empty($post->attachment)) {
        return array();
    }

    if (!$context = context_module::instance($cm->id)) {
        return array();
    }

    $fs = get_file_storage();

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment', $post->id, "filename", false);
    if ($files) {
        $i = 0;
        foreach ($files as $file) {
            $attachments[$i] = array();
            $attachments[$i]['filename'] = $file->get_filename();

            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file),
                get_mimetype_description($file), 'moodle',
                array('class' => 'icon'));
            $path = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                                                     $file->get_itemid(), $file->get_filepath(), $file->get_filename());

            $attachments[$i]['icon'] = $iconimage;
            $attachments[$i]['filepath'] = $path;

            if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                // Image attachments don't get printed as links.
                $attachments[$i]['image'] = true;
            } else {
                $attachments[$i]['image'] = false;
            }
            $i += 1;
        }
    }
    return $attachments;
}

/**
 * If successful, this function returns the name of the file
 *
 * @param object $post is a full post record, including course and forum
 * @param object $forum
 * @param object $cm
 *
 * @return bool
 */
function moodleoverflow_add_attachment($post, $forum, $cm) {
    global $DB;

    if (empty($post->attachments)) {
        return true;   // Nothing to do.
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount'] > 0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_moodleoverflow', 'attachment', $post->id,
        mod_moodleoverflow_post_form::attachment_options($forum));

    $DB->set_field('moodleoverflow_posts', 'attachment', $present, array('id' => $post->id));

    return true;
}

/**
 * Adds a new post in an existing discussion.
 * @param object $post The post object
 * @return bool|int The Id of the post if operation was successful
 * @throws coding_exception
 * @throws dml_exception
 */
function moodleoverflow_add_new_post($post) {
    global $USER, $DB;

    // We do not check if these variables exist because this function
    // is just called from one function which checks all these variables.
    $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion));
    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow));
    $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);

    // Add some variables to the post.
    $post->created = $post->modified = time();
    $post->userid = $USER->id;
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }

    // Set to not reviewed, if posts should be reviewed, and user is not a reviewer themselves.
    if (review::get_review_level($moodleoverflow) == review::EVERYTHING &&
            !has_capability('mod/moodleoverflow:reviewpost', context_module::instance($cm->id))) {
        $post->reviewed = 0;
    } else {
        $post->reviewed = 1;
    }

    // Add the post to the database.
    $post->id = $DB->insert_record('moodleoverflow_posts', $post);
    $DB->set_field('moodleoverflow_posts', 'message', $post->message, array('id' => $post->id));
    moodleoverflow_add_attachment($post, $moodleoverflow, $cm);

    if ($post->reviewed) {
        // Update the discussion.
        $DB->set_field('moodleoverflow_discussions', 'timemodified', $post->modified, array('id' => $post->discussion));
        $DB->set_field('moodleoverflow_discussions', 'usermodified', $post->userid, array('id' => $post->discussion));
    }

    // Mark the created post as read if the user is tracking the discussion.
    $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow);
    $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);
    if ($cantrack && $istracked) {
        readtracking::moodleoverflow_mark_post_read($post->userid, $post);
    }

    // Return the id of the created post.
    return $post->id;
}

/**
 * Updates a specific post.
 *
 * Capabilities are not checked, because this is happening in the post.php.
 *
 * @param object $newpost The new post object
 *
 * @return bool Whether the update was successful
 */
function moodleoverflow_update_post($newpost) {
    global $DB, $USER;

    // Retrieve not submitted variables.
    $post = $DB->get_record('moodleoverflow_posts', array('id' => $newpost->id));
    $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion));
    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow));

    // Allowed modifiable fields.
    $modifiablefields = [
        'message',
        'messageformat',
    ];

    // Iteratate through all modifiable fields and update the values.
    foreach ($modifiablefields as $field) {
        if (isset($newpost->{$field})) {
            $post->{$field} = $newpost->{$field};
        }
    }

    $post->modified = time();
    if ($newpost->reviewed ?? $post->reviewed) {
        // Update the date and the user of the post and the discussion.
        $discussion->timemodified = $post->modified;
        $discussion->usermodified = $post->userid;
    }

    // When editing the starting post of a discussion.
    if (!$post->parent) {
        $discussion->name = $newpost->subject;
    }

    // Update the post and the corresponding discussion.
    $DB->update_record('moodleoverflow_posts', $post);
    $DB->update_record('moodleoverflow_discussions', $discussion);

    $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
    moodleoverflow_add_attachment($newpost, $moodleoverflow, $cm);

    // Mark the edited post as read.
    $cantrack = readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow);
    $istracked = readtracking::moodleoverflow_is_tracked($moodleoverflow);
    if ($cantrack && $istracked) {
        readtracking::moodleoverflow_mark_post_read($USER->id, $post);
    }

    // The post has been edited successfully.
    return true;
}

/**
 * Count all replies of a post.
 *
 * @param object $post The post object
 * @param bool $onlyreviewed Whether to count only reviewed posts.
 *
 * @return int Amount of replies
 */
function moodleoverflow_count_replies($post, $onlyreviewed) {
    global $DB;

    $conditions = ['parent' => $post->id];

    if ($onlyreviewed) {
        $conditions['reviewed'] = '1';
    }

    // Return the amount of replies.
    return $DB->count_records('moodleoverflow_posts', $conditions);
}

/**
 * Deletes a discussion and handles all associated cleanups.
 *
 * @param object $discussion     The discussion object
 * @param object $course         The course object
 * @param object $cm
 * @param object $moodleoverflow The moodleoverflow object
 *
 * @return bool Whether the deletion was successful.
 */
function moodleoverflow_delete_discussion($discussion, $course, $cm, $moodleoverflow) {
    global $DB;

    // Initiate a pointer.
    $result = true;

    // Get all posts related to the discussion.
    if ($posts = $DB->get_records('moodleoverflow_posts', array('discussion' => $discussion->id))) {

        // Iterate through them and delete each one.
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->moodleoverflow = $discussion->moodleoverflow;
            if (!moodleoverflow_delete_post($post, 'ignore', $cm, $moodleoverflow)) {

                // If the deletion failed, change the pointer.
                $result = false;
            }
        }
    }

    // Delete the read-records for the discussion.
    readtracking::moodleoverflow_delete_read_records(-1, -1, $discussion->id);

    // Remove the subscriptions for this discussion.
    $DB->delete_records('moodleoverflow_discuss_subs', array('discussion' => $discussion->id));
    if (!$DB->delete_records('moodleoverflow_discussions', array('id' => $discussion->id))) {
        $result = false;
    }

    // Return if there deletion was successful.
    return $result;
}

/**
 * Deletes a single moodleoverflow post.
 *
 * @param object $post                  The post
 * @param bool   $deletechildren        The child posts
 * @param object $cm                    The course module
 * @param object $moodleoverflow        The moodleoverflow
 *
 * @return bool Whether the deletion was successful
 */
function moodleoverflow_delete_post($post, $deletechildren, $cm, $moodleoverflow) {
    global $DB, $USER;

    // Iterate through all children and delete them.
    // In case something does not work we throw the error as it should be known that something went ... terribly wrong.
    // All DB transactions are rolled back.
    try {
        $transaction = $DB->start_delegated_transaction();

        $childposts = $DB->get_records('moodleoverflow_posts', array('parent' => $post->id));
        if ($deletechildren && $childposts) {
            foreach ($childposts as $childpost) {
                moodleoverflow_delete_post($childpost, true, $cm, $moodleoverflow);
            }
        }

        // Delete the ratings.
        $DB->delete_records('moodleoverflow_ratings', array('postid' => $post->id));

        // Delete the post.
        if ($DB->delete_records('moodleoverflow_posts', array('id' => $post->id))) {
            // Delete the read records.
            readtracking::moodleoverflow_delete_read_records(-1, $post->id);

            // Delete the attachments.
            // First delete the actual files on the disk.
            $fs = get_file_storage();
            $context = context_module::instance($cm->id);
            $attachments = $fs->get_area_files($context->id, 'mod_moodleoverflow', 'attachment',
                $post->id, "filename", true);
            foreach ($attachments as $attachment) {
                // Get file
                $file = $fs->get_file($context->id, 'mod_moodleoverflow', 'attachment', $post->id,
                    $attachment->get_filepath(), $attachment->get_filename());
                // Delete it if it exists
                if ($file) {
                    $file->delete();
                }
            }

            // Just in case, check for the new last post of the discussion.
            moodleoverflow_discussion_update_last_post($post->discussion);

            // Get the context module.
            $modulecontext = context_module::instance($cm->id);

            // Trigger the post deletion event.
            $params = array(
                'context' => $modulecontext,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $post->discussion,
                    'moodleoverflowid' => $moodleoverflow->id
                )
            );
            if ($post->userid !== $USER->id) {
                $params['relateduserid'] = $post->userid;
            }
            $event = post_deleted::create($params);
            $event->trigger();

            // The post has been deleted.
            $transaction->allow_commit();
            return true;
        }
    } catch (Exception $e) {
        $transaction->rollback($e);
    }

    // Deleting the post failed.
    return false;
}

/**
 * Sets the last post for a given discussion.
 *
 * @param int $discussionid The discussion ID
 *
 * @return bool Whether the last post needs to be updated
 */
function moodleoverflow_discussion_update_last_post($discussionid) {
    global $DB;

    // Check if the given discussion exists.
    if (!$DB->record_exists('moodleoverflow_discussions', array('id' => $discussionid))) {
        return false;
    }

    // Find the last reviewed post of the discussion. (even if user has review capability, because it is written to DB).
    $sql = "SELECT id, userid, modified
              FROM {moodleoverflow_posts}
             WHERE discussion = ?
               AND reviewed = 1
          ORDER BY modified DESC";

    // Find the new last post of the discussion.
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);

        // Create an discussion object.
        $discussionobject = new stdClass();
        $discussionobject->id = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;

        // Update the discussion.
        $DB->update_record('moodleoverflow_discussions', $discussionobject);

        return $lastpost->id;
    }

    // Just in case, return false.
    return false;
}

/**
 * Save the referer for later redirection.
 */
function moodleoverflow_set_return() {
    global $CFG, $SESSION;

    // Get the referer.
    if (!isset($SESSION->fromdiscussion)) {
        $referer = get_local_referer(false);

        // If the referer is not a login screen, save it.
        if (!strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}

/**
 * Count the amount of discussions per moodleoverflow.
 *
 * @param object $moodleoverflow
 * @param object $course
 *
 * @return int|mixed
 */
function moodleoverflow_count_discussions($moodleoverflow, $course) {
    global $CFG, $DB;

    // Create a cache.
    static $cache = array();

    // Initiate variables.
    $params = array($course->id);

    // Check whether the cache for the moodleoverflow is set.
    if (!isset($cache[$course->id])) {

        // Count the number of discussions.
        $sql = "SELECT m.id, COUNT(d.id) as dcount
                  FROM {moodleoverflow} m
                  JOIN {moodleoverflow_discussions} d on d.moodleoverflow = m.id
                 WHERE m.course = ?
              GROUP BY m.id";
        $counts = $DB->get_records_sql($sql, $params);

        // Check whether there are discussions.
        if ($counts) {

            // Loop through all records.
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }

            // Cache the course.
            $cache[$course->id] = $counts;

        } else {
            // There are no records.

            // Save the result into the cache.
            $cache[$course->id] = array();
        }
    }

    // Check whether there are discussions.
    if (empty($cache[$course->id][$moodleoverflow->id])) {
        return 0;
    }

    // Require the course library.
    require_once($CFG->dirroot . '/course/lib.php');

    // Count the discussions.
    $sql = "SELECT COUNT(d.id)
            FROM {moodleoverflow_discussions} d
            WHERE d.moodleoverflow = ?";
    $amount = $DB->get_field_sql($sql, array($moodleoverflow->id));

    // Return the amount.
    return $amount;
}

/**
 * Updates user grade.
 *
 * @param object $moodleoverflow
 * @param int $postuserrating
 * @param object $postinguser
 *
 */
function moodleoverflow_update_user_grade($moodleoverflow, $postuserrating, $postinguser) {

    // Check whether moodleoverflow object has the added params.
    if ($moodleoverflow->grademaxgrade > 0 && $moodleoverflow->gradescalefactor > 0) {
        moodleoverflow_update_user_grade_on_db($moodleoverflow, $postuserrating, $postinguser);
    }
}

/**
 * Updates user grade in database.
 *
 * @param object $moodleoverflow
 * @param int $postuserrating
 * @param int $userid
 *
 */
function moodleoverflow_update_user_grade_on_db($moodleoverflow, $postuserrating, $userid) {
    global $DB;

    // Calculate the posting user's updated grade.
    $grade = $postuserrating / $moodleoverflow->gradescalefactor;

    if ($grade > $moodleoverflow->grademaxgrade) {

        $grade = $moodleoverflow->grademaxgrade;
    }

    // Save updated grade on local table.
    if ($DB->record_exists('moodleoverflow_grades', array('userid' => $userid, 'moodleoverflowid' => $moodleoverflow->id))) {

        $DB->set_field('moodleoverflow_grades', 'grade', $grade, array('userid' => $userid,
            'moodleoverflowid' => $moodleoverflow->id));

    } else {

        $gradedataobject = new stdClass();
        $gradedataobject->moodleoverflowid = $moodleoverflow->id;
        $gradedataobject->userid = $userid;
        $gradedataobject->grade = $grade;
        $DB->insert_record('moodleoverflow_grades', $gradedataobject, false);
    }

    // Update gradebook.
    moodleoverflow_update_grades($moodleoverflow, $userid);
}

/**
 * Updates all grades for context module.
 *
 * @param int $moodleoverflowid
 *
 */
function moodleoverflow_update_all_grades_for_cm($moodleoverflowid) {
    global $DB;

    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflowid));

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
            $userrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $userid, true);

            // Calculate the posting user's updated grade.
            moodleoverflow_update_user_grade_on_db($moodleoverflow, $userrating, $userid);
        }
    }
}

/**
 * Updates all grades.
 *
 */
function moodleoverflow_update_all_grades() {
    global $DB;
    $cmids = $DB->get_records_select('moodleoverflow', null, null, 'id');
    foreach ($cmids as $cmid) {
        moodleoverflow_update_all_grades_for_cm($cmid->id);
    }
}
