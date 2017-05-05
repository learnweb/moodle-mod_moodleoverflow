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
 * Library of interface functions and constants for module moodleoverflow
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the moodleoverflow specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * MOODLEOVERFLOW_TRACKING - Constants
 * MOODLEOVERFLOW_TRACKING_OFF - Tracking is not aviable for this moodleoverflow.
 * MOODLEOVERFLOW_TRACKING_OPTIONAL -  Tracking is based on user preference.
 * MOODLEOVERFLOW_TRACKING_FORCED - Tracking is on, regardless of the user setting.
 * This is treated as MOODLEOVERFLOW_TRACKING_OPTIONAL if $CFG->moodleoverflow_allowforcedreadtracking is off.
 */
define('MOODLEOVERFLOW_TRACKING_OFF', 0);
define('MOODLEOVERFLOW_TRACKING_OPTIONAL', 1);
define('MOODLEOVERFLOW_TRACKING_FORCED', 2);


/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function moodleoverflow_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the moodleoverflow into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $moodleoverflow Submitted data from the form in mod_form.php
 * @param mod_moodleoverflow_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted moodleoverflow record
 */
function moodleoverflow_add_instance(stdClass $moodleoverflow, mod_moodleoverflow_mod_form $mform = null) {
    global $DB;

    $moodleoverflow->timecreated = time();

    // You may have to add extra stuff in here.

    $moodleoverflow->id = $DB->insert_record('moodleoverflow', $moodleoverflow);

    moodleoverflow_grade_item_update($moodleoverflow);

    return $moodleoverflow->id;
}

/**
 * Updates an instance of the moodleoverflow in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $moodleoverflow An object from the form in mod_form.php
 * @param mod_moodleoverflow_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function moodleoverflow_update_instance(stdClass $moodleoverflow, mod_moodleoverflow_mod_form $mform = null) {
    global $DB;

    $moodleoverflow->timemodified = time();
    $moodleoverflow->id = $moodleoverflow->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('moodleoverflow', $moodleoverflow);

    moodleoverflow_grade_item_update($moodleoverflow);

    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every moodleoverflow event in the site is checked, else
 * only moodleoverflow events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid Course ID
 * @return bool
 */
function moodleoverflow_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$moodleoverflows = $DB->get_records('moodleoverflow')) {
            return true;
        }
    } else {
        if (!$moodleoverflows = $DB->get_records('moodleoverflow', array('course' => $courseid))) {
            return true;
        }
    }

    /*
    foreach ($moodleoverflows as $moodleoverflow) {
        // Create a function such as the one below to deal with updating calendar events.
        // moodleoverflow_update_events($moodleoverflow);
    }
    */

    return true;
}

/**
 * Removes an instance of the moodleoverflow from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function moodleoverflow_delete_instance($id) {
    global $DB;

    if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.

    $DB->delete_records('moodleoverflow', array('id' => $moodleoverflow->id));

    moodleoverflow_grade_item_delete($moodleoverflow);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $moodleoverflow The moodleoverflow instance record
 * @return stdClass|null
 */
function moodleoverflow_user_outline($course, $user, $mod, $moodleoverflow) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $moodleoverflow the module instance record
 */
function moodleoverflow_user_complete($course, $user, $mod, $moodleoverflow) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in moodleoverflow activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function moodleoverflow_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link moodleoverflow_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function moodleoverflow_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link moodleoverflow_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function moodleoverflow_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function moodleoverflow_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function moodleoverflow_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of moodleoverflow?
 *
 * This function returns if a scale is being used by one moodleoverflow
 * if it has support for grading and scales.
 *
 * @param int $moodleoverflowid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given moodleoverflow instance
 */
function moodleoverflow_scale_used($moodleoverflowid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('moodleoverflow', array('id' => $moodleoverflowid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of moodleoverflow.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any moodleoverflow instance
 */
function moodleoverflow_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('moodleoverflow', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given moodleoverflow instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $moodleoverflow instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function moodleoverflow_grade_item_update(stdClass $moodleoverflow, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($moodleoverflow->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($moodleoverflow->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $moodleoverflow->grade;
        $item['grademin']  = 0;
    } else if ($moodleoverflow->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$moodleoverflow->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/moodleoverflow', $moodleoverflow->course, 'mod', 'moodleoverflow',
            $moodleoverflow->id, 0, null, $item);
}

/**
 * Delete grade item for given moodleoverflow instance
 *
 * @param stdClass $moodleoverflow instance object
 * @return grade_item
 */
function moodleoverflow_grade_item_delete($moodleoverflow) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/moodleoverflow', $moodleoverflow->course, 'mod', 'moodleoverflow',
            $moodleoverflow->id, 0, null, array('deleted' => 1));
}

/**
 * Update moodleoverflow grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $moodleoverflow instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function moodleoverflow_update_grades(stdClass $moodleoverflow, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/moodleoverflow', $moodleoverflow->course, 'mod', 'moodleoverflow', $moodleoverflow->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function moodleoverflow_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for moodleoverflow file areas
 *
 * @package mod_moodleoverflow
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function moodleoverflow_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the moodleoverflow file areas
 *
 * @package mod_moodleoverflow
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the moodleoverflow's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function moodleoverflow_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding moodleoverflow nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the moodleoverflow module instance
 * @param stdClass $course current course record
 * @param stdClass $module current moodleoverflow instance record
 * @param cm_info $cm course module information
 */
function moodleoverflow_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the moodleoverflow settings
 *
 * This function is called when the context for the page is a moodleoverflow module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $moodleoverflownode moodleoverflow administration node
 */
function moodleoverflow_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $moodleoverflownode=null) {
    // TODO Delete this function and its docblock, or implement it.
}

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
        $buttonurl = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $moodleoverflow->id]);
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
                    echo '<a title="' . $strmarkallread . '" href="' . $CFG->wwwroot . '/mod/moodleoverflow/markposts.php?m=' .
                         $moodleoverflow->id . '&amp;d=' . $post->discussion - '&amp;mark=read&amp;returnpage=view.php&amp;sesskey=' .
                         sesskey() . '">' . '<img src="' . $OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="' .
                         $strmarkallread . '" /></a>';
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
    if ((!is_guest($context, $USER) && isloggedin()) && $canview ) {
        // ToDo: Wait for feedback. Then check this.
    }

    echo "</tr>\n\n";
}