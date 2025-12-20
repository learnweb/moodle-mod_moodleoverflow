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
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// LEARNWEB-TODO: Adapt functions to the new way of working with posts and discussions (Replace the post/discussion functions).
use core_completion\api;
use mod_moodleoverflow\subscriptions;

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/locallib.php');

// Readtracking constants.
define('MOODLEOVERFLOW_TRACKING_OFF', 0);
define('MOODLEOVERFLOW_TRACKING_OPTIONAL', 1);
define('MOODLEOVERFLOW_TRACKING_FORCED', 2);

// Subscription constants.
define('MOODLEOVERFLOW_CHOOSESUBSCRIBE', 0);
define('MOODLEOVERFLOW_FORCESUBSCRIBE', 1);
define('MOODLEOVERFLOW_INITIALSUBSCRIBE', 2);
define('MOODLEOVERFLOW_DISALLOWSUBSCRIBE', 3);

// Mailing state constants.
define('MOODLEOVERFLOW_MAILED_PENDING', 0);
define('MOODLEOVERFLOW_MAILED_SUCCESS', 1);
define('MOODLEOVERFLOW_MAILED_ERROR', 2);
define('MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS', 3);

// Constants for the post rating.
define('MOODLEOVERFLOW_PREFERENCE_STARTER', 0);
define('MOODLEOVERFLOW_PREFERENCE_TEACHER', 1);

// Reputation constants.
define('MOODLEOVERFLOW_REPUTATION_MODULE', 0);
define('MOODLEOVERFLOW_REPUTATION_COURSE', 1);

// Allow ratings?
define('MOODLEOVERFLOW_RATING_FORBID', 0);
define('MOODLEOVERFLOW_RATING_ALLOW', 1);

// Allow reputations?
define('MOODLEOVERFLOW_REPUTATION_FORBID', 0);
define('MOODLEOVERFLOW_REPUTATION_ALLOW', 1);

// Allow negative reputations?
define('MOODLEOVERFLOW_REPUTATION_POSITIVE', 0);
define('MOODLEOVERFLOW_REPUTATION_NEGATIVE', 1);

// Rating constants.
define('RATING_NEUTRAL', 0);
define('RATING_DOWNVOTE', 1);
define('RATING_REMOVE_DOWNVOTE', 10);
define('RATING_UPVOTE', 2);
define('RATING_REMOVE_UPVOTE', 20);
define('RATING_SOLVED', 3);
define('RATING_REMOVE_SOLVED', 30);
define('RATING_HELPFUL', 4);
define('RATING_REMOVE_HELPFUL', 40);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature.
 *
 * See {plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 *
 * @return mixed true if the feature is supported, null if unknown
 */
function moodleoverflow_supports(string $feature): mixed {

    if (defined('FEATURE_MOD_PURPOSE')) {
        if ($feature == FEATURE_MOD_PURPOSE) {
            return MOD_PURPOSE_COLLABORATION;
        }
    }

    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the moodleoverflow into the database.
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass                    $data Submitted data from the form in mod_form.php
 *
 * @return int The id of the newly inserted moodleoverflow record
 */
function moodleoverflow_add_instance(stdClass $data) {
    global $DB;

    // Set the current time.
    $data->timecreated = time();

    // You may have to add extra stuff in here.
    $mid = $DB->insert_record('moodleoverflow', $data);

    $completiontime = !empty($data->completionexpected) ? $data->completionexpected : null;
    api::update_completion_date_event($data->coursemodule, 'moodleoverflow', $mid, $completiontime);

    return $mid;
}

/**
 * Handle changes following the creation of a moodleoverflow instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param context_module   $context        The context of the moodleoverflow
 * @param stdClass $moodleoverflow The moodleoverflow object
 */
function moodleoverflow_instance_created($context, $moodleoverflow) {

    // Check if users are forced to be subscribed to the moodleoverflow instance.
    if ($moodleoverflow->forcesubscribe == MOODLEOVERFLOW_INITIALSUBSCRIBE) {
        // Get a list of all potential subscribers.
        $users = subscriptions::get_potential_subscribers($context, 'u.id, u.email');

        // Subscribe all potential subscribers to this moodleoverflow.
        foreach ($users as $user) {
            subscriptions::subscribe_user($user->id, $moodleoverflow, $context);
        }
    }
}

/**
 * Updates an instance of the moodleoverflow in the database.
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass                         $data           The data from the form in mod_form.php
 *
 * @return boolean Success/Fail
 */
function moodleoverflow_update_instance(stdClass $data): bool {
    global $DB;

    $data->timemodified = time();

    // Get the moodleoverflow id.
    $data->id = $data->instance;

    // Get the old record.
    $oldmoodleoverflow = $DB->get_record('moodleoverflow', ['id' => $data->id]);

    // Find the context of the module.
    $modulecontext = context_module::instance($data->coursemodule);

    // Check if the subscription state has changed.
    if ($data->forcesubscribe != $oldmoodleoverflow->forcesubscribe) {
        if ($data->forcesubscribe == MOODLEOVERFLOW_INITIALSUBSCRIBE) {
            // Get a list of potential subscribers.
            $users = subscriptions::get_potential_subscribers($modulecontext, 'u.id, u.email', '');

            // Subscribe all those users to the moodleoverflow instance.
            foreach ($users as $user) {
                subscriptions::subscribe_user($user->id, $data, $modulecontext);
            }
        } else if ($data->forcesubscribe == MOODLEOVERFLOW_CHOOSESUBSCRIBE) {
            // Delete all current subscribers.
            $DB->delete_records('moodleoverflow_subscriptions', ['moodleoverflow' => $data->id]);
        }
    }

    // Update the moodleoverflow instance in the database.
    $result = $DB->update_record('moodleoverflow', $data);

    moodleoverflow_grade_item_update($data);

    // Update all grades.
    moodleoverflow_update_all_grades_for_cm($data->id);

    $completiontime = !empty($data->completionexpected) ? $data->completionexpected : null;
    api::update_completion_date_event($data->coursemodule, 'moodleoverflow', $data->id, $completiontime);

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
 *
 * @return bool
 */
function moodleoverflow_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$DB->get_records('moodleoverflow')) {
            return true;
        }
    } else {
        if (!$DB->get_records('moodleoverflow', ['course' => $courseid])) {
            return true;
        }
    }

    return true;
}

/**
 * Removes an instance of the moodleoverflow from the database.
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 *
 * @return boolean Success/Failure
 */
function moodleoverflow_delete_instance($id) {
    global $DB;

    // Initiate the variables.
    $result = true;

    // Get the needed objects.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $id])) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
        return false;
    }

    // Get the context module.
    $context = context_module::instance($cm->id);

    // Delete all connected files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    // Delete the subscription elements.
    $DB->delete_records('moodleoverflow_subscriptions', ['moodleoverflow' => $moodleoverflow->id]);
    $DB->delete_records('moodleoverflow_discuss_subs', ['moodleoverflow' => $moodleoverflow->id]);
    $DB->delete_records('moodleoverflow_grades', ['moodleoverflowid' => $moodleoverflow->id]);

    // Delete the discussion recursivly.
    if ($discussions = $DB->get_records('moodleoverflow_discussions', ['moodleoverflow' => $moodleoverflow->id])) {
        require_once('locallib.php');
        foreach ($discussions as $discussion) {
            if (!moodleoverflow_delete_discussion($discussion, $cm, $moodleoverflow)) {
                $result = false;
            }
        }
    }

    // Delete the read records.
    \mod_moodleoverflow\readtracking::moodleoverflow_delete_read_records(-1, -1, -1, $moodleoverflow->id);

    // Delete the moodleoverflow instance.
    if (!$DB->delete_records('moodleoverflow', ['id' => $moodleoverflow->id])) {
        $result = false;
    }

    // Return whether the deletion was successful.
    return $result;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass         $course         The course record
 * @param stdClass         $user           The user record
 * @param cm_info|stdClass $mod            The course module info object or record
 * @param stdClass         $moodleoverflow The moodleoverflow instance record
 *
 * @return stdClass|null
 */
function moodleoverflow_user_outline($course, $user, $mod, $moodleoverflow) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';

    return $return;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in moodleoverflow activities and print it out.
 *
 * @param stdClass $course        The course record
 * @param bool     $viewfullnames Should we display full names
 * @param int      $timestart     Print activity since this timestamp
 *
 * @return boolean True if anything was printed, otherwise false
 */
function moodleoverflow_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Returns all other caps used in the module.
 *
 * For example, this could be ['moodle/site:accessallgroups'] if the
 * module uses that capability.
 *
 * @return array
 */
function moodleoverflow_get_extra_capabilities() {
    return [];
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by { file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 *
 * @return array of [(string)filearea] => (string)description
 */
function moodleoverflow_get_file_areas($course, $cm, $context) {
    return [
        'attachment' => get_string('areaattachment', 'mod_moodleoverflow'),
        'post' => get_string('areapost', 'mod_moodleoverflow'),
    ];
}

/**
 * File browsing support for moodleoverflow file areas.
 *
 * @package  mod_moodleoverflow
 * @category files
 *
 * @param file_browser $browser
 * @param array        $areas
 * @param stdClass     $course
 * @param stdClass     $cm
 * @param stdClass     $context
 * @param string       $filearea
 * @param int          $itemid
 * @param string       $filepath
 * @param string       $filename
 *
 * @return file_info instance or null if not found
 */
function moodleoverflow_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the moodleoverflow file areas.
 *
 * @package  mod_moodleoverflow
 * @category files
 *
 * @param stdClass $course        the course object
 * @param stdClass $cm            the course module object
 * @param stdClass $context       the moodleoverflow's context
 * @param string   $filearea      the name of the file area
 * @param array    $args          extra arguments (itemid, path)
 * @param bool     $forcedownload whether or not force download
 * @param array    $options       additional options affecting the file serving
 */
function moodleoverflow_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_course_login($course, true, $cm);

    $areas = moodleoverflow_get_file_areas($course, $cm, $context);
    // Filearea must contain a real area.
    if (!isset($areas[$filearea])) {
        return false;
    }

    $filename = array_pop($args);
    $itemid = array_pop($args);

    // Check if post, discussion or moodleoverflow still exists.
    if (!$post = $DB->get_record('moodleoverflow_posts', ['id' => $itemid])) {
        return false;
    }
    if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $post->discussion])) {
        return false;
    }
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $cm->instance])) {
        return false;
    }

    if (!$args) {
        // Empty path, use root.
        $filepath = '/';
    } else {
        // Assemble filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }
    $fs = get_file_storage();

    $file = $fs->get_file($context->id, 'mod_moodleoverflow', $filearea, $itemid, $filepath, $filename);

    // Make sure we're allowed to see it...
    if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 86400, 0, true, $options); // Download MUST be forced - security!
}

/* Navigation API */

/**
 * Extends the settings navigation with the moodleoverflow settings.
 *
 * This function is called when the context for the page is a moodleoverflow module. This is not called by AJAX
 * so it is safe to rely on the page variable.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node|null $moodleoverflownode moodleoverflow administration node
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function moodleoverflow_extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $moodleoverflownode = null) {
    global $DB, $USER;

    // Retrieve the current moodle record.
    $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $settingsnav->get_page()->cm->instance]);

    // Check if the user can subscribe to the instance.
    if (!$context = context_module::instance($settingsnav->get_page()->cm->id)) {
        throw new \moodle_exception('badcontext');
    }
    $enrolled = is_enrolled($context, $USER, '', false);
    $activeenrolled = is_enrolled($context, $USER, '', true);
    $canmanage = has_capability('mod/moodleoverflow:managesubscriptions', $context);
    $forcesubscribed = subscriptions::is_forcesubscribed($moodleoverflow);
    $subscdisabled = subscriptions::subscription_disabled($moodleoverflow);
    $cansubscribe = $activeenrolled && (!$subscdisabled || $canmanage) &&
        !($forcesubscribed && has_capability('mod/moodleoverflow:allowforcesubscribe', $context));
    $cantrack = \mod_moodleoverflow\readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow);

    // Display a link to the index.
    if ($enrolled && $activeenrolled) {
        // Generate the text of the link.
        $linktext = get_string('gotoindex', 'moodleoverflow');

        // Generate the link.
        $url = '/mod/moodleoverflow/index.php';
        $params = ['id' => $moodleoverflow->course];
        $link = new moodle_url($url, $params);

        // Add the link to the menu.
        $moodleoverflownode->add($linktext, $link, navigation_node::TYPE_SETTING);
    }

    // Display a link to subscribe or unsubscribe.
    if ($cansubscribe) {
        // Choose the linktext depending on the current state of subscription.
        $issubscribed = subscriptions::is_subscribed($USER->id, $moodleoverflow, $context);
        if ($issubscribed) {
            $linktext = get_string('unsubscribe', 'moodleoverflow');
        } else {
            $linktext = get_string('subscribe', 'moodleoverflow');
        }

        // Add the link to the menu.
        $url = new moodle_url('/mod/moodleoverflow/subscribe.php', ['id' => $moodleoverflow->id, 'sesskey' => sesskey()]);
        $moodleoverflownode->add($linktext, $url, navigation_node::TYPE_SETTING);
    }

    // Display a link to enable or disable readtracking.
    if ($enrolled && $cantrack) {
        // Check some basic capabilities.
        $isoptional = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_OPTIONAL);
        $forceallowed = get_config('moodleoverflow', 'allowforcedreadtracking');
        $isforced = ($moodleoverflow->trackingtype == MOODLEOVERFLOW_TRACKING_FORCED);

        // Check whether the readtracking state can be changed.
        if ($isoptional || (!$forceallowed && $isforced)) {
            // Generate the text of the link depending on the current state.
            $istracked = \mod_moodleoverflow\readtracking::moodleoverflow_is_tracked($moodleoverflow);
            if ($istracked) {
                $linktext = get_string('notrackmoodleoverflow', 'moodleoverflow');
            } else {
                $linktext = get_string('trackmoodleoverflow', 'moodleoverflow');
            }

            // Generate the link.
            $url = '/mod/moodleoverflow/tracking.php';
            $params = ['id' => $moodleoverflow->id, 'sesskey' => sesskey()];
            $link = new moodle_url($url, $params);

            // Add the link to the menu.
            $moodleoverflownode->add($linktext, $link, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Determine the current context if one wa not already specified.
 *
 * If a context of type context_module is specified, it is immediately returned and not checked.
 *
 * @param int            $moodleoverflowid The moodleoverflow ID
 * @param context_module $context          The current context
 *
 * @return context_module The context determined
 */
function moodleoverflow_get_context($moodleoverflowid, $context = null) {
    global $PAGE;

    // If the context does not exist, find the context.
    if (!$context || !($context instanceof context_module)) {
        // Try to take current page context to save on DB query.
        if (
            $PAGE->cm && $PAGE->cm->modname === 'moodleoverflow' && $PAGE->cm->instance == $moodleoverflowid
            && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id
        ) {
            $context = $PAGE->context;
        } else {
            // Get the context via the coursemodule.
            $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflowid);
            $context = \context_module::instance($cm->id);
        }
    }

    // Return the context.
    return $context;
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 *
 * @param cm_info $cm Course-module object
 */
function moodleoverflow_cm_info_view(cm_info $cm) {

    $cantrack = \mod_moodleoverflow\readtracking::moodleoverflow_can_track_moodleoverflows();
    $out = "";
    if (has_capability('mod/moodleoverflow:reviewpost', $cm->context)) {
        $reviewcount = \mod_moodleoverflow\review::count_outstanding_reviews_in_moodleoverflow($cm->instance);
        if ($reviewcount) {
            $out .= '<span class="mod_moodleoverflow-label-review"><a href="' . $cm->url . '">';
            $out .= get_string('amount_waiting_for_review', 'mod_moodleoverflow', $reviewcount);
            $out .= '</a></span> ';
        }
    }
    if ($cantrack) {
        $unread = \mod_moodleoverflow\readtracking::moodleoverflow_count_unread_posts_moodleoverflow($cm);
        if ($unread) {
            $out .= '<span class="mod_moodleoverflow-label-unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'moodleoverflow');
            } else {
                $out .= get_string('unreadpostsnumber', 'moodleoverflow', $unread);
            }
            $out .= '</a></span>';
        }
    }
    if ($out) {
        $cm->set_after_link($out);
    }
}

/**
 * Check if the user can create attachments in moodleoverflow.
 *
 * @param  stdClass $moodleoverflow moodleoverflow object
 * @param  context_module $context        context object
 *
 * @return bool true if the user can create attachments, false otherwise
 * @since  Moodle 3.3
 */
function moodleoverflow_can_create_attachment($moodleoverflow, $context) {
    // If maxbytes == 1 it means no attachments at all.
    if (
        empty($moodleoverflow->maxattachments) || $moodleoverflow->maxbytes == 1 ||
        !has_capability('mod/moodleoverflow:createattachment', $context)
    ) {
        return false;
    }

    return true;
}

/**
 * Get the grades of a moodleoverflow instance for all users or a single user.
 *
 * @param stdClass $moodleoverflow moodleoverflow object
 * @param int $userid optional userid, 0 means all users.
 *
 * @return array array of grades
 */
function moodleoverflow_get_user_grades($moodleoverflow, $userid = 0) {
    global $DB;

    $params = ["moodleoverflowid" => $moodleoverflow->id];

    $sql = "SELECT u.id AS userid, g.grade AS rawgrade
              FROM {user} u
              JOIN {moodleoverflow_grades} g ON u.id = g.userid
             WHERE g.moodleoverflowid = :moodleoverflowid";

    if ($userid) {
        $sql .= ' AND u.id = :userid ';
        $params["userid"] = $userid;
    }

    return $DB->get_records_sql($sql, $params);
}

/**
 * Update grades
 *
 * @param stdClass $moodleoverflow moodleoverflow object
 * @param int $userid userid
 * @param bool $nullifnone
 *
 */
function moodleoverflow_update_grades($moodleoverflow, $userid, $nullifnone = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    // Try to get the grades to update.
    if ($grades = moodleoverflow_get_user_grades($moodleoverflow, $userid)) {
        moodleoverflow_grade_item_update($moodleoverflow, $grades);
    } else if ($userid && $nullifnone) {
        // Insert a grade with rawgrade = null. As described in Gradebook API.
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        moodleoverflow_grade_item_update($moodleoverflow, $grade);
    } else {
        moodleoverflow_grade_item_update($moodleoverflow);
    }
}

/**
 * Update plugin's grade item
 *
 * @param stdClass $moodleoverflow moodleoverflow object
 * @param array $grades array of grades
 *
 * @return int grade_update function success code
 */
function moodleoverflow_grade_item_update($moodleoverflow, $grades = null) {
    global $CFG, $DB;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    $params = ['itemname' => $moodleoverflow->name, 'idnumber' => $moodleoverflow->id];

    if ($moodleoverflow->grademaxgrade <= 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else if ($moodleoverflow->grademaxgrade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $moodleoverflow->grademaxgrade;
        $params['grademin'] = 0;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $gradeupdate = grade_update(
        'mod/moodleoverflow',
        $moodleoverflow->course,
        'mod',
        'moodleoverflow',
        $moodleoverflow->id,
        0,
        $grades,
        $params
    );

    // Modify grade item category id.
    if (!is_null($moodleoverflow->gradecat) && $moodleoverflow->gradecat > 0) {
        $params = ['itemname' => $moodleoverflow->name, 'idnumber' => $moodleoverflow->id];
        $DB->set_field('grade_items', 'categoryid', $moodleoverflow->gradecat, $params);
    }

    return $gradeupdate;
}

/**
 * Map icons for font-awesome themes.
 */
function moodleoverflow_get_fontawesome_icon_map() {
    return [
        'mod_moodleoverflow:i/commenting' => 'fa-commenting',
        'mod_moodleoverflow:i/pending-big' => 'fa-clock-o text-danger moodleoverflow-icon-2x',
        'mod_moodleoverflow:i/status-helpful' => 'fa-thumbs-up moodleoverflow-icon-1_5x moodleoverflow-text-orange',
        'mod_moodleoverflow:i/status-solved' => 'fa-check moodleoverflow-icon-1_5x moodleoverflow-text-green',
        'mod_moodleoverflow:i/reply' => 'fa-reply',
        'mod_moodleoverflow:i/subscribed' => 'fa-bell moodleoverflow-icon-1_5x',
        'mod_moodleoverflow:i/unsubscribed' => 'fa-bell-slash-o moodleoverflow-icon-1_5x',
        'mod_moodleoverflow:i/vote-up' => 'fa-chevron-up moodleoverflow-icon-2x moodleoverflow-icon-no-margin',
        'mod_moodleoverflow:i/vote-down' => 'fa-chevron-down moodleoverflow-icon-2x moodleoverflow-icon-no-margin',
    ];
}
