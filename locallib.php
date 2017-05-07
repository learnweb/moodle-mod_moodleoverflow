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
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

/**
 * Get all discussions in a moodleoverflow instance.
 *
 * @param object $cm
 * @param int $page
 * @param int $perpage
 * @return array
 */
function moodleoverflow_get_discussions($cm, $page = -1, $perpage = 0) {
    global $DB, $USER;

    $params = array($cm->instance);

    // User must have the permission to view the discussions.
    $modcontext = context_module::instance($cm->id);
    if (!has_capability('mod/moodleoverflow:viewdiscussion', $modcontext)) {
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
    $allnames = get_all_user_name_fields(true, 'u');
    $postdata = 'p.id, p.modified, p.discussion, p.userid';
    $discussiondata = 'd.name, d.timemodified, d.timestart, d.usermodified';
    $userdata = 'u.email, u.picture, u.imagealt';

    $usermodifiedfields = get_all_user_name_fields(true, 'um', null, 'um') .
        ', um.email AS umemail, um.picture AS umpicture, um.imagealt AS umimagealt';
    $usermodifiedtable = " LEFT JOIN {user} um ON (d.usermodified = um.id)";

    // Retrieve and return all discussions from the database.
    $sql = "SELECT $postdata, $discussiondata, $allnames, $userdata, $usermodifiedfields
              FROM {moodleoverflow_discussions} d
                   JOIN {moodleoverflow_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $usermodifiedtable
              WHERE d.moodleoverflow = ? AND p.parent = 0
           ORDER BY d.timestart, d.id DESC";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitamount);
}

/**
 * @param object $moodleoverflow MoodleOverflow to be printed.
 * @param $cm
 * @paran int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page (optional).
 */
function moodleoverflow_print_latest_discussions($moodleoverflow, $cm, $page = -1, $perpage = 25) {
    global $CFG, $USER, $OUTPUT;

    // Check if the course supports the module.
    if (!$cm) {
        if (!$cm = get_course_and_cm_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            pint_error('invalidcoursemodule');
        }
    }

    // Set the context.
    $context = context_module::instance($cm->id);

    // If the perpage value is invalid, deactivate paging.
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    // Check some capabilities.
    $canstartdiscussion = moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $context);
    $canviewdiscussions  = has_capability('mod/moodleoverflow:viewdiscussion', $context);

    // Print a button if the user is capable of starting
    // a new discussion or if the selfenrol is aviable.
    if ($canstartdiscussion) {
        $buttontext = get_string('addanewdiscussion', 'moodleoverflow');
        $buttonurl = new moodle_url('/mod/moodleoverflow/post.php', ['m' => $moodleoverflow->id]);
        $button = new single_button($buttonurl, $buttontext, 'get');
        $button->class = 'singlebutton moodleoverflowaddnew';
        $button->formid = 'newdiscussionform';
        echo $OUTPUT->render($button);
    }

    // Get all the recent discussions the user is allowed to see.
    $discussions = moodleoverflow_get_discussions($cm, $page, $perpage);

    // Display a message if there are no recent discussions.
    if (!$discussions) {
        echo '<div class="moodleoverflowdiscussions">';
        echo '('.get_string('nodiscussions', 'moodleoverflow').'.)';
        echo "</div>\n";
        return;
    }

    // If we want paging.
    if ($page != -1) {

        // Get the number of discussions.
        $numberofdiscussions = moodleoverflow_get_discussions_count($cm);

        // Show the paging bar.
        echo $OUTPUT->paging_bar($numberofdiscussions, $page, $perpage, "view.php?id=$cm->id");
    }

    // Get the number of replies for each discussion.
    $replies = moodleoverflow_count_discussion_replies($moodleoverflow->id);

    // Check whether the moodleoverflow instance can be tracked and is tracked.
    if ($cantrack = moodleoverflow_track_can_track_moodleoverflows($moodleoverflow)) {
        $istracked = moodleoverflow_track_is_tracked($moodleoverflow);
    } else {
        $istracked = false;
    }

    // Get an array of unread messages for the current user if the moodleoverflow instance is tracked.
    if ($istracked) {
        $unreads = moodleoverflow_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    // Print the table.
    echo '<table cellspacing="0" class="moodleoverflowheaderlist">';
    echo '<thead>';
    echo '<tr>';
    echo '<th class="header topic" scope="col">'.get_string('headerdiscussion', 'moodleoverflow').'</th>';
    echo '<th class="header author" colspan="2" scope="col">'.get_string('headerstartedby', 'moodleoverflow').'</th>';

    // Check if the user is allowed to view the discussions.
    if ($canviewdiscussions) {

        // Display the amount of replies.
        echo '<th class="header replies" scope="col">' . get_string('headerreplies', 'moodleoverflow') . '</th>';

        // Display the unread column if the moodleoverflow can be tracked.
        if ($cantrack) {
            echo '<th class="header replies" scope="col">' . get_string('headerunread', 'moodleoverflow');

            // Display a symbol to mark all messages displayed if the forum is tracked.
            if ($istracked) {
                echo '<a title="' . get_string('markallread', 'moodleoverflow') .
                    '" href="' . $CFG->wwwroot.'/mod/moodleoverflow/markposts.php?m=' .
                    $moodleoverflow->id . '&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' .
                    sesskey() . '" >' . '<img src="' . $OUTPUT->pix_url('t/markasread') .
                    '" class="iconsmall" alt="' . get_string('markallread', 'moodleoverflow') . '" /></a>';
            }
            echo '</th>';
        }
    }

    echo '<th class="header lastpost" scope="col">' . get_string('headerlastpost', 'moodleoverflow') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Iterate through every visible discussion.
    foreach ($discussions as $discussion) {

        // Set the amount of replies for every discussion.
        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

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

        // Use the discussions name instead of the subject of the first post.
        $discussion->subject = $discussion->name;

        // Print the list of discussions.
        moodleoverflow_print_discussion_header($discussion, $moodleoverflow, $cantrack, $istracked, $context);
    }

    // Close the table.
    echo '</tbody>';
    echo '</table>';

    // Show the paging bar if paging is activated.
    if ($page != -1) {
        echo $OUTPUT->paging_bar($numberofdiscussions, $page, $perpage, "view.php?id=$cm->id");
    }
}

/**
 * Returns an array of counts of replies for each discussion.
 *
 * @global object $DB
 * @param int $moodleoverflowid
 * @return array
 */
function moodleoverflow_count_discussion_replies($moodleoverflowid) {
    global $DB;

    $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
              FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
             WHERE p.parent > 0 AND d.moodleoverflow = ?
          GROUP BY p.discussion";

    return $DB->get_records_sql($sql, array($moodleoverflowid));
}

/**
 * Check if the user is capable of starting a new discussion.
 *
 * @param object $moodleoverflow
 * @param object $cm
 * @param object $context
 * @return bool
 */
function moodleoverflow_user_can_post_discussion($moodleoverflow, $cm = null, $context = null) {

    // Guests an not-logged-in users can not psot.
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    // Get the coursemodule.
    if (!$cm) {
        if (!$cm = get_course_and_cm_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            pint_error('invalidcoursemodule');
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
 * @return array
 */
function moodleoverflow_get_discussions_count($cm) {
    global $DB;

    $params = array($cm->instance);

    $sql = 'SELECT COUNT(d.id)
              FROM {moodleoverflow_discussions} d
                   JOIN {moodleoverflow_posts} p ON p.discussion = d.id
             WHERE d.moodleoverflow = ? AND p.parent = 0';

    return $DB->get_field_sql($sql, $params);
}

/**
 * Returns an array of unread messages for the current user.
 *
 * @param object $cm
 * @return array
 */
function moodleoverflow_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    // Get the current timestamp and the oldpost-timestamp.
    $params = array();
    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->moodleoverflow_oldpostdays * 24 * 60 * 60);

    // Define the sql-query.
    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {moodleoverflow_discussions} d
                   JOIN {moodleoverflow_posts} p ON p.discussion = d.id
                   LEFT JOIN {moodleoverflow_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.moodleoverflow = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    // Return the unread messages as an array.
    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {

        // If there are no unread messages, return an empty array.
        return array();
    }
}

/**
 * Determine if a user can track moodleoverflows and optionally a particular forum.
 * Checks the site settings, the user settings and the moodleoverflow settings (if
 * requested).
 *
 * @param bool $moodleoverflow
 * @param bool $user
 * @return boolean
 */
function moodleoverflow_track_can_track_moodleoverflows($moodleoverflow = false, $user = false) {
    global $USER, $CFG;

    // Check if readtracking is enabled for the module.
    if (empty($CFG->moodleoverflow_trackreadposts)) {
        return false;
    }

    // Check if the user is set.
    if ($user === false) {
        $user = $USER;
    }

    // Guests are not allowed to track moodleoverflows.
    if (isguestuser($user) OR empty($user->id)) {
        return false;
    }

    // If no specific moodleoverflow is submitted, check the modules basic settings.
    if ($moodleoverflow === false) {
        return (bool)$CFG->moodleoverflow_allowforcedreadtracking;
    }

    // Check the settings of the moodleoverflow instance.
    $allowed = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
    $forced  = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);

    // Return a boolean whether read tracking is allowed/forced.
    return ($allowed || $forced);
}

/**
 * Tells whether a specific moodleoverflow is tracked by the user.
 * A user can optionally be specified. If not specified, the current user is assumed.
 *
 * @param object $moodleoverflow
 * @param object $user
 * @return bool
 */
function moodleoverflow_track_is_tracked($moodleoverflow, $user = false) {
    global $USER, $CFG, $DB;

    // If no user is specified, use the current user.
    if ($user === false) {
        $user = $USER;
    }

    // Guests cannot track a moodleoverflow.
    if (isguestuser($user) OR empty($user->id)) {
        return false;
    }

    // Check if the moodleoverflow can be generally tracked.
    if (!moodleoverflow_track_can_track_moodleoverflows($moodleoverflow, $user)) {
        return false;
    }

    // Check the settings of the moodleoverflow instance.
    $allowed = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
    $forced  = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);
    $userpreference = $DB->get_record('moodleoverflow_subscriptions',
        array('userid' => $user->id, 'moodleoverflow' => $moodleoverflow->id));

    // Return the boolean.
    if ($CFG->moodleoverflow_allowforcedreadtracking) {
        return ($forced || ($allowed && $userpreference !== false));
    } else {
        return (($allowed || $forced) && $userpreference !== false);
    }
}

/**
 * This function prints the overview of a discussion in the moodleoverflow listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: moodleoverflow_print_latest_discussions().
 *
 * @param object reference $post
 * @param object $moodleoverflow
 * @param bool $cantrack
 * @param bool $istracked
 * @param object $context
 */
function moodleoverflow_print_discussion_header(&$post, $moodleoverflow, $cantrack = true, $istracked = true, $context = null) {
    global $COURSE, $USER, $CFG, $OUTPUT, $PAGE;

    // Static variables.
    static $rowcount;
    static $strmarkalldread;

    // Check the context.
    if (empty($context)) {
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            print_error('invalidcoursemodule');
        }
        $context = context_module::instance($cm->id);
    }

    // Check the static variables.
    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkallread = get_string('markalldread', 'moodleoverflow');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    // Check capabilities.
    $canview = has_capability('mod/moodleoverflow:viewdiscussion', $context);

    // Filter the subject of the discussion.
    $post->subject = format_string($post->subject, true);

    // Start a new row within the table.
    echo "\n\n";
    echo '<tr class="discussion r' . $rowcount . '" >';

    // Print the subject of the topic.
    echo '<td class="topic starter">';
    echo '<a href="' . $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $post->discussion . '">' . $post->subject . '</a>';
    echo "</td>\n";

    // Picture of the user that started the discussion.
    $startuser = new stdClass();
    $startuserfields = explode(',', user_picture::fields());
    $startuser = username_load_fields_from_object($startuser, $post, null, $startuserfields);
    $startuser->id = $post->userid;
    echo '<td class="picture">';
    echo $OUTPUT->user_picture($startuser, array('courseid' => $moodleoverflow->course));
    echo "</td>\n";

    // Display the username.
    $fullname = fullname($startuser, has_capability('moodle/site:viewfullnames', $context));
    echo '<td class="author">';
    echo '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $post->userid .
        '&amp;course=' . $moodleoverflow->course . '">' . $fullname . '</a>';
    echo "</td>\n";

    // Show the reply-columns only if the user has the capability to.
    if (has_capability('mod/forum:viewdiscussion', $context)) {

        // Amount of replies.
        echo '<td class="replies">';
        echo '<a href="' . $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' .
            $post->discussion . '">' . $post->replies . '</a>';
        echo "</td>\n";

        // Display the column for unread replies.
        if ($cantrack) {
            echo '<td class="replies">';

            // Dont display the amount of unread messages, if the discussion is not tracked.
            if (!$istracked) {
                echo '<span class="read">-</span>';
            } else {

                // Link the text if there are unread replies.
                if ($post->unread > 0) {

                    // Display the amount of unread messages.
                    echo '<span class="unread">';
                    echo '<a href="' . $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=';
                    echo $post->discussion . '#unread">' . $post->unread . '</a>';

                    // Display the icon to mark all as read.
                    echo '<a title="' . $strmarkallread . '" href="' . $CFG->wwwroot .
                        '/mod/moodleoverflow/markposts.php?m=' . $moodleoverflow->id . '&amp;d=' . $post->discussion .
                        '&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' . sesskey() . '">' . '<img src="' .
                        $OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="' . $strmarkallread . '" /></a>';
                    echo '</span>';

                } else {

                    // Else there are no unread messages.
                    echo '<span class="read">';
                    echo $post->unread;
                    echo '</span>';
                }
            }
            echo "</td>\n";
        }
    }

    // Display the latest post.
    echo '<td class="lastpost">';

    // Check the date. Just in case the database is not consistent.
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;

    // Get the name of the user, that is related to the latest post.
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');
    echo '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $post->usermodified . '&amp;course=' .
        $moodleoverflow->course .  '">' . fullname($usermodified) . '</a><br />';

    // Get the date of the latest post of the discussion.
    $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent=' . $post->lastpostid;
    echo '<a href="' . $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $post->discussion .
        $parenturl . '">' . userdate($usedate, get_string('strftimerecentfull')) . '</a>';
    echo "</td>\n";

    // Enrolled users can subscribe to single discussions.
    // ToDo: Wait for feedback. Then check this.

    echo "</tr>\n\n";
}

/**
 * Gets a post with all info ready for moodleoverflow_print_post.
 * Most of these joins are just to get the forum id.
 *
 * @param $postid
 * @return mixed array of posts or false
 */
function moodleoverflow_get_post_full($postid) {
    global $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT p.*, d.moodleoverflow, $allnames, u.email, u.picture, u.imagealt
              FROM {moodleoverflow_posts} p
                   JOIN {moodleoverflow_discussions} d ON p.discussion = d.id
              LEFT JOIN {user} u ON p.userid = u.id
                  WHERE p.id = ?";

    return $DB->get_record_sql($sql, array($postid));
}