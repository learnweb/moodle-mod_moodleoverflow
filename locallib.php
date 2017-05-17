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
           ORDER BY d.timestart DESC, d.id DESC";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitamount);
}

/**
 * @param object $moodleoverflow MoodleOverflow to be printed.
 * @param $cm
 * @paran int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page (optional).
 */
function moodleoverflow_print_latest_discussions($moodleoverflow, $cm, $page = -1, $perpage = 25) {
    global $CFG, $USER, $OUTPUT, $PAGE;

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
        $buttonurl = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $moodleoverflow->id]);
        $button = new single_button($buttonurl, $buttontext, 'get');
        $button->class = 'singlebutton moodleoverflowaddnew';
        $button->formid = 'newdiscussionform';
        echo $OUTPUT->render($button);
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

    // Iterate through every visible discussion.
    $i = 0;
    $rowcount = 0;
    $preparedarray = array();
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

        // Increase the rowcount.
        $rowcount = ($rowcount + 1) % 2;

        // Format the subjectname and the link to the topic.
        $subjecttext = format_string($discussion->subject);
        $subjectlink = $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $discussion->discussion;

        // Get information about the user who started the discussion.
        $startuser = new stdClass();
        $startuserfields = explode(',', user_picture::fields());
        $startuser = username_load_fields_from_object($startuser, $discussion, null, $startuserfields);
        $startuser->id = $discussion->userid;

        // Get his picture, his name and the link to his profile.
        $userpicture = $OUTPUT->user_picture($startuser, array('courseid' => $moodleoverflow->course));
        $username = fullname($startuser, has_capability('moodle/site:viewfullnames', $context));
        $userlink = $CFG->wwwroot . '/user/view.php?id=' . $discussion->userid . '&course=' . $moodleoverflow->course;

        // Get the amount of replies and the link to the discussion.
        $replyamount = $discussion->replies;
        $replylink = $subjectlink;

        // Are there unread messages? Create a link to them.
        $unreadamount = $discussion->unread;
        $hasunreads = ($unreadamount > 0) ? true : false;
        $unreadlink = $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $discussion->discussion . '#unread';

        // Check the date of the latest post. Just in case the database is not consistent.
        $usedate = (empty($discussion->timemodified)) ? $discussion->modified : $discussion->timemodified;

        // Get the name and the link to the profile of the user, that is related to the latest post.
        $usermodified = new stdClass();
        $usermodified->id = $discussion->usermodified;
        $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um');
        $usermodifiedname = fullname($usermodified);
        $usermodifiedlink = $CFG->wwwroot . '/user/view.php?id=' . $discussion->usermodified . '&course=' . $moodleoverflow->course;

        // Get the date of the latest post of the discussion.
        $parenturl = (empty($discussion->lastpostid)) ? '' : '&parent=' . $discussion->lastpostid;
        $lastpostdate = userdate($usedate, get_string('strftimerecentfull'));
        $lastpostlink = $subjectlink . $parenturl;

        // Add all created data to an array.
        $preparedarray[$i] = array();
        $preparedarray[$i]['rowcount'] = $rowcount;
        $preparedarray[$i]['subjecttext'] = $subjecttext;
        $preparedarray[$i]['subjectlink'] = $subjectlink;
        $preparedarray[$i]['picture'] = $userpicture;
        $preparedarray[$i]['username'] = $username;
        $preparedarray[$i]['userlink'] = $userlink;
        $preparedarray[$i]['replyamount'] = $replyamount;
        $preparedarray[$i]['replylink'] = $replylink;
        $preparedarray[$i]['unread'] = $hasunreads;
        $preparedarray[$i]['unreadamount'] = $unreadamount;
        $preparedarray[$i]['unreadlink'] = $unreadlink;
        $preparedarray[$i]['lastpostuserlink'] = $usermodifiedlink;
        $preparedarray[$i]['lastpostusername'] = $usermodifiedname;
        $preparedarray[$i]['lastpostlink'] = $lastpostlink;
        $preparedarray[$i]['lastpostdate'] = $lastpostdate;

        // Go to the next discussion.
        $i++;
    }

    // Include the renderer.
    $renderer = $PAGE->get_renderer('mod_moodleoverflow');

    // Collect the needed data being submitted to the template.
    $mustachedata = new stdClass();
    $mustachedata->cantrack = $cantrack;
    $mustachedata->canviewdiscussions = $canviewdiscussions;
    $mustachedata->discussions = $preparedarray;
    $mustachedata->hasdiscussions = (count($discussions) >= 0) ? true : false;
    $mustachedata->istracked = $istracked;

    // Print the template.
    echo $renderer->render_discussion_list($mustachedata);

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
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
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
 * Checks the site settings and the moodleoverflow settings (if requested).
 *
 * @param bool $moodleoverflow
 * @return boolean
 */
function moodleoverflow_track_can_track_moodleoverflows($moodleoverflow = null) {
    global $USER, $CFG;

    // Check if readtracking is disabled for the module.
    if (empty($CFG->moodleoverflow_trackreadposts)) {
        return false;
    }

    // Guests are not allowed to track moodleoverflows.
    if (isguestuser($USER) OR empty($USER->id)) {
        return false;
    }

    // If no specific moodleoverflow is submitted, check the modules basic settings.
    if (is_null($moodleoverflow)) {
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
 *
 * @param object $moodleoverflow
 * @return bool
 */
function moodleoverflow_track_is_tracked($moodleoverflow) {
    global $USER, $CFG, $DB;

    // Guests cannot track a moodleoverflow.
    if (isguestuser($USER) OR empty($USER->id)) {
        return false;
    }

    // Check if the moodleoverflow can be generally tracked.
    if (!moodleoverflow_track_can_track_moodleoverflows($moodleoverflow)) {
        return false;
    }

    // Check the settings of the moodleoverflow instance.
    $allowed = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
    $forced  = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);
    $userpreference = $DB->get_record('moodleoverflow_subscriptions',
        array('userid' => $USER->id, 'moodleoverflow' => $moodleoverflow->id));

    // Return the boolean.
    if ($CFG->moodleoverflow_allowforcedreadtracking) {
        return ($forced || ($allowed && $userpreference !== false));
    } else {
        return (($allowed || $forced) && $userpreference !== false);
    }
}

// TODO Currently unused.
/**
 * CURRENTLY UNUSED
 *
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
                    echo '<a title="' . get_string('markalldread', 'moodleoverflow') . '" href="' . $CFG->wwwroot .
                        '/mod/moodleoverflow/markposts.php?m=' . $moodleoverflow->id . '&amp;d=' . $post->discussion .
                        '&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' . sesskey() . '">' . '<img src="' .
                        $OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="' . get_string('markalldread', 'moodleoverflow') . '" /></a>';
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

/**
 * Checks if a user can see a specific post.
 *
 * @param $moodleoverflow
 * @param $discussion
 * @param $post
 * @param null $user
 * @param $cm
 * @return bool
 */
function moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $user = null, $cm) {
    global $CFG, $USER, $DB;

    // Retrieve the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Fetch the moodleoverflow instance object.
    if (is_numeric($moodleoverflow)) {
        debugging('missing full moodleoverflow', DEBUG_DEVELOPER); // TODO: Delete.
        if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
            return false;
        }
    }

    // Fetch the discussion object.
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER); // TODO: Delete.
        if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $discussion))) {
            return false;
        }
    }

    // Fetch the post object.
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER); // TODO: Delete.
        if (! $post = $DB->get_record('moodleoverflow_posts', array('id' => $post))) {
            return false;
        }
    }

    // Get the postid if not set.
    if (!isset($post->id) AND isset($post->parent)) {
        $post->id = $post->parent;
    }

    // Find the coursemodule.
    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER); // TODO: Delete.
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            print_error('invalidcoursemodule');
        }
    }

    // Make sure a user is set.
    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // Check if the user can view the discussion.
    $canviewdiscussion = !empty($cm->cache->caps['mod/moodleoverflow:viewdiscussion']) ||
        has_capability('mod/moodleoverflow:viewdiscussion', $modulecontext, $user->id);
    if (!$canviewdiscussion &&
        !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    // Check the coursemodule settings.
    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    // The user has the capability to see the discussion.
    return true;

}

/**
 * Check if a user can see a specific discussion.
 *
 * @param $moodleoverflow
 * @param $discussion
 * @param $context
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
    if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
        print_error('invalidcoursemodule');
    }

    // Check the users capability.
    if (!has_capability('mod/moodleoverflow:viewdiscussion', $context)) {
        return false;
    }

    // Allow the user to see the discussion.
    return true;
}







function moodleoverflow_add_discussion($discussion, $userid = null) {
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
    if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
        print_error('invalidcoursemodule');
    }

    // Create the post-object.
    $post = new stdClass();
    $post->discussion     = 0;
    $post->parent         = 0;
    $post->userid         = $userid;
    $post->created        = $timenow;
    $post->modified       = $timenow;
    $post->message        = $discussion->message;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->course         = $moodleoverflow->course;
    // TODO: messagetrust + messageformat?

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
    // TODO: messagetrust + messageformat?

    // Submit the discussion to the database and get its id.
    $post->discussion = $DB->insert_record('moodleoverflow_discussions', $discussionobject);

    // Link the post to the discussion.
    $DB->set_field('moodleoverflow_posts', 'discussion', $post->discussion, array('id' => $post->id));

    // Mark the created post as read.
    if (moodleoverflow_track_can_track_moodleoverflows($moodleoverflow) AND moodleoverflow_track_is_tracked($moodleoverflow)) {
        moodleoverflow_track_mark_post_read($post->userid, $post);
    }

    // TODO: Trigger content_uploaded_event.

    // Return the id of the discussion.
    return $post->discussion;
}


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

function moodleoverflow_track_mark_post_read($userid, $post) {

    // If the post is older than the limit.
    if (moodleoverflow_track_is_old_post($post)) {
        return true;
    }

    // Create a new read record.
    return moodleoverflow_track_add_read_record($userid, $post->id);

}


// Checks if a post is older than the limit.
function moodleoverflow_track_is_old_post($post) {
    global $CFG;

    // Get the current time.
    $currenttimestamp = time();

    // Calculate the time, where older posts are considered read.
    $oldposttimestamp = $currenttimestamp - ($CFG->moodleoverflow_oldpostdays * 24 * 3600);

    // Return if the post is newer than that time.
    return ($post->modified < $oldposttimestamp);
}


// Mark a post as read.
function moodleoverflow_track_add_read_record($userid, $postid) {
    global $CFG, $DB;

    // Get the current time and the cutoffdate.
    $now = time();
    $cutoffdate = $now - ($CFG->moodleoverflow_oldpostdays * 24 * 3600);

    // If there is already a read record, update it.
    if ($DB->record_exists('moodleoverflow_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "UPDATE {moodleoverflow_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }

    // Else create a new read record.
    $sql = "INSERT INTO {moodleoverflow_read} (userid, postid, discussionid, moodleoverflowid, firstread, lastread)
                 SELECT ?, p.id, p.discussion, d.moodleoverflow, ?, ?
                   FROM {moodleoverflow_posts} p
                        JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                  WHERE p.id = ? AND p.modified >= ?";
    return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));
}

// Checks whether the user can reply to posts in a discussion.
function moodleoverflow_user_can_post($moodleoverflow, $discussion, $user = null, $cm = null, $course = null, $modulecontext = null) {
    global $USER, $DB;

    // If not user is submitted, use the current one.
    if (empty($user)) {
        $user = $USER;
    }

    // Guests can not post.
    if (isguestuser($user) OR empty($user->id)) {
        return false;
    }

    // Fetch the coursemodule.
    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
            print_error('invalidcoursemodule');
        }
    }

    // Fetch the related course.
    if (!$course) {
        if (!$course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
            print_error('invalidcourseid');
        }
    }

    // Fetch the related modulecontext.
    if (!$modulecontext) {
        $modulecontext = context_module::instance($cm->id);
    }

    // TODO: Check if the discussion is locked.

    // Users with temporary guest access can not post.
    if (!is_viewing($modulecontext, $user->id) AND !is_enrolled($modulecontext, $user->id, '', true)) {
        return false;
    }

    // Check the users capability.
    if (has_capability('mod/moodleoverflow:replypost', $modulecontext, $user->id)) {
        return true;
    }

    // The user does not have the capability.
    return false;
}


// Prints a moodleoverflow discussion.
function moodleoverflow_print_discussion($course, $cm, $moodleoverflow, $discussion, $post, $canreply, $canrate) {
    global $USER, $OUTPUT;

    // Require the Rating API.
    //require_once($CFG->dirroot . '/rating/lib.php'); TODO: Include this.

    // Check if the current is the starter of the discussion.
    $ownpost = (isloggedin() AND ($USER->id == $post->userid));

    // Fetch the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Is the forum tracked?
    $istracked = moodleoverflow_track_is_tracked($moodleoverflow);

    // Retrieve all posts of the discussion.
    $posts = moodleoverflow_get_all_discussion_posts($discussion->id, $istracked);

    // Start with the parent post.
    $post = $posts[$post->id];

    // Lets clear all posts above level 2.
    // Check if there are answers.
    if (isset($post->children)) {

        // Itereate through all answers.
        foreach ($post->children as $aid => $a) {

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

    // TODO: Warum nur parent?!
    $postread = !empty($post->postread);

    // Print a button to reply to the discussion.
    if ($canreply) {
        $buttontext = get_string('addanewreply', 'moodleoverflow');
        $buttonurl = new moodle_url('/mod/moodleoverflow/post.php', ['reply' => $post->id]);
        $button = new single_button($buttonurl, $buttontext, 'get');
        $button->class = 'singlebutton moodleoverflowaddnew';
        $button->formid = 'newdiscussionform';
        echo $OUTPUT->render($button);
    }

    // Print the starting post.
    echo moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course, $ownpost, $canreply, false, '', '', $postread, true, $istracked);

    // Print the other posts.
    echo moodleoverflow_print_posts_nested($course, $cm, $moodleoverflow, $discussion, $post, $canreply, $istracked, $posts);
}

// Get all posts in discussion including the startpost.
function moodleoverflow_get_all_discussion_posts($discussionid, $tracking) {
    global $DB, $USER;

    // TODO: Delete this.
    $sort = "p.created ASC";

    // Initiate tracking settings.
    $tracking_selector = '';
    $tracking_join = '';
    $params = array();

    // If tracking is enabled, another join is needed.
    if ($tracking) {
        $tracking_selector = ", mr.id AS postread";
        $tracking_join = "LEFT JOIN {moodleoverflow_read} mr ON (mr.postid = p.id AND mr.userid = ?)";
        $params[] = $USER->id;
    }

    // Get all username fields.
    $allnames = get_all_user_name_fields(true, 'u');

    // Create the sql array.
    $params[] = $discussionid;
    $params[] = $discussionid;
    $sql = "SELECT p.*, $allnames, d.name as subject, u.email, u.picture, u.imagealt $tracking_selector
              FROM {moodleoverflow_posts} p
                   LEFT JOIN {user} u ON p.userid = u.id
                   LEFT JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                   $tracking_join
             WHERE p.discussion = ?
          ORDER BY $sort";

    // Return an empty array, if there are no posts.
    if (! $posts = $DB->get_records_sql($sql, $params)) {
        return array();
    }

    // Find all children of this post.
    foreach ($posts as $postid => $post) {

        // Is it an old post?
        if ($tracking) {
            if (moodleoverflow_track_is_old_post($post)) {
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

    // Return the objeckt.
    return $posts;
}



// Print a moodleoverflow post.
function moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course,
                                   $ownpost = false, $canreply = false, $link = false,
                                   $footer = '', $highlight = '', $postisread = null,
                                   $dummyifcantsee = true, $istracked = false, $iscomment = false) {
    global $USER, $CFG, $OUTPUT, $PAGE;

    // Require the filelib.
    require_once($CFG->libdir . '/filelib.php');

    // String cache.
    static $str;

    // Print the 'unread' only on time.
    static $firstunreadanchorprinted = false;

    // Declare the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Add some informationto the post.
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modulecontext->id, 'mod_moodleoverflow', 'post', $post->id);

    // Caching.
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass();
    }

    // Check the cached capabilities.
    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/moodleoverflow:viewdiscussion'] = has_capability('mod/moodleoverflow:viewdiscussion', $modulecontext);
        $cm->cache->caps['mod/moodleoverflow:editanypost'] = has_capability('mod/moodleoverflow:editanypost', $modulecontext);
        $cm->cache->caps['mod/moodleoverflow:deleteownpost'] = has_capability('mod/moodleoverflow:deleteownpost', $modulecontext);
        $cm->cache->caps['mod/moodleoverflow:viewanyrating'] = has_capability('mod/moodleoverflow:viewanyrating', $modulecontext);
        $cm->cache->caps['moodle/site:viewfullnames'] = has_capability('moodle/site:viewfullnames', $modulecontext);
    }

    // Check if the user has the capability to see posts.
    if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, NULL, $cm)) {

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
        $str->edit       = get_string('edit', 'moodleoverflow');
        $str->delete     = get_string('delete', 'moodleoverflow');
        $str->reply      = get_string('reply', 'moodleoverflow');
        $str->replyfirst = get_string('replyfirst', 'moodleoverflow');
        $str->parent     = get_string('parent', 'moodleoverflow');
        $str->markread   = get_string('markread', 'moodleoverflow');;
        $str->markunread = get_string('markunread', 'moodleoverflow');;
    }

    // Get the current link without unnecessary parameters.
    $discussionlink = new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $post->discussion));

    // Build the object that represents the posting user.
    $postinguser = new stdClass();
    $postinguserfields = explode(',', user_picture::fields());
    $postinguser = username_load_fields_from_object($postinguser, $post, null, $postinguserfields);
    $postinguser->id = $post->userid;
    $postinguser->fullname = fullname($postinguser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postinguser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));

    // Prepare an array of commands.
    $commands = array();

    // Create a permalink.
    $permalink = new moodle_url($discussionlink);
    $permalink->set_anchor('p' . $post->id);
    $commands[] = array('url' => $permalink, 'text' => get_string('permalink', 'moodleoverflow'));

    // TODO: Mark Read / Unread -> istracked und loggedin und cfg->usrmarksread.

    // Calculate the age of the post.
    $age = time() - $post->created;

    // Make a link to edit your own post within the given time.
    // TODO: maxeditingtime oder modulspezifische Einstellungen?
    if (($ownpost AND ($age < $CFG->maxeditingtime)) OR $cm->cache->caps['mod/moodleoverflow:editanypost']) {
        $editurl = new moodle_url('/mod/moodleoverflow/post.php', array('edit' => $post->id));
        $commands[] = array('url' => $editurl, 'text' => $str->edit);
    }

    // Give the option to reply to a post.
    if ($canreply) {

        // Answer to the parent post.
        if (empty($post->parent)) {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
            $commands[] = array('url' => $replyurl, 'text' => $str->replyfirst);

            // If the post is a comment, answer to the parent post.
        } else if (!$iscomment) {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $post->id));
            $commands[] = array('url' => $replyurl, 'text' => $str->reply);

            // Else simple respond to the answer.
        } else {
            $replyurl = new moodle_url('/mod/moodleoverflow/post.php#mformmoodleoverflow', array('reply' => $iscomment));
            $commands[] = array('url' => $replyurl, 'text' => $str->reply);
        }
    }

    // Initiate the output variables.
    $mustachedata = new stdClass();
    $mustachedata->istracked = $istracked;
    $mustachedata->isread = false;
    $mustachedata->isfirstunread = false;
    $mustachedata->isfirstpost = false;

    // Check the reading status of the post.
    $postclass = '';
    if ($istracked) {
        if ($postisread) {
            $postclass = ' read';
            $mustachedata->isread = true;
        } else {
            $postclass = ' unread';

            // Anchor the first unread post of a discussion.
            if (!$firstunreadanchorprinted) {
                $mustachedata->isfirstunread = true;
                $firstunreadanchorprinted = true;
            }
        }
    }
    $mustachedata->postclass = $postclass;

    // Is this the firstpost?
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
        $mustachedata->isfirstpost = true;
    }

    //
    $postbyuser = new stdClass();
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postinguser->fullname;
    $mustachedata->discussionby = get_string('postbyuser', 'moodleoverflow', $postbyuser);

    // Set basic variables of the post.
    $mustachedata->postid = $post->id;
    $mustachedata->subject = format_string($post->subject);

    // User picture.
    $mustachedata->picture = $OUTPUT->user_picture($postinguser, ['courseid' => $course->id]);

    // The name of the user and the date modified.
    $by = new stdClass();
    $by->date = userdate($post->modified);
    $by->name = html_writer::link($postinguser->profilelink, $postinguser->fullname);
    $mustachedata->bytext = get_string('bynameondate', 'moodleoverflow', $by);

    // Set options for the post.
    $options = new stdClass();
    $options->para = false;
    $options->trusted = false;
    $options->context = $modulecontext;

    // TODO: Shorten post?

    // Prepare the post.
    $mustachedata->postcontent = format_text($post->message, $post->messageformat, $options, $course->id);
    // TODO: Highlight?
    // TODO: Displaywordcount?
    // TODO: AttachedImages?
    // TODO: Ratings.

    // Output the commands.
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $mustachedata->commands = implode(' | ', $commandhtml);

    // TODO: Link to post if required.

    // Print a footer if requested.
    $mustachedata->footer = $footer;

    // Mark the forum post as read.
    if($istracked AND !$postisread) {
        moodleoverflow_track_mark_post_read($USER->id, $post);
    }

    // Include the renderer to display the dummy content.
    $renderer = $PAGE->get_renderer('mod_moodleoverflow');
    return $renderer->render_post($mustachedata);
}



function moodleoverflow_print_posts_nested($course, &$cm, $moodleoverflow, $discussion, $parent, $canreply, $istracked, $posts, $iscomment = false) {
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
            if (!$iscomment) {
                $output .= "<div style='margin-top: 50px'>";
                $parentid = $post->id;
            } else {
                $output .= "<div class='indent'>";
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
            $output .= moodleoverflow_print_post($post, $discussion, $moodleoverflow, $cm, $course, $ownpost, $canreply, false, '', '', $postread, true, $istracked, $parentid);

            // Print its children.
            $output .= moodleoverflow_print_posts_nested($course, $cm, $moodleoverflow, $discussion, $post, $canreply, $istracked, $posts, $parentid);

            // End the div.
            $output .= "</div>\n";
        }
    }

    // Return the output.
    return $output;
}


// Add a new post in an existing discussion.
function moodleoverflow_add_new_post($post, $mform) {
    global $USER, $DB;

    // We do not check if these variables exist because this function
    // is just called from one function which checks all these variables.
    $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion));
    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow));
    $cm         = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
    $modulecontext    = context_module::instance($cm->id);

    // Add some variables to the post.
    $post->created = $post->modified = time();
    $post->userid = $USER->id;
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }

    // Add the post to the database.
    $post->id = $DB->insert_record('moodleoverflow_posts', $post);
    $DB->set_field('moodleoverflow_posts', 'message', $post->message, array('id' => $post->id));

    // Update the discussion.
    $DB->set_field('moodleoverflow_discussions', 'timemodified', $post->modified, array('id' => $post->discussion));
    $DB->set_field('moodleoverflow_discussions', 'usermodified', $post->userid, array('id' => $post->discussion));

    // Mark the created post as read if the user is tracking the discussion.
    if (moodleoverflow_track_can_track_moodleoverflows($moodleoverflow) AND moodleoverflow_track_is_tracked($moodleoverflow)) {
        moodleoverflow_track_mark_post_read($post->userid, $post);
    }

    // TODO: Trigger event?

    // Return the id of the created post.
    return $post->id;
}