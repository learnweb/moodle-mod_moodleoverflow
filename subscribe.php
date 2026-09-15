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
 * Subscribe to or unsubscribe from a moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\subscriptions;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');

global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT;

// Define required and optional params.
$id = required_param('id', PARAM_INT);             // The moodleoverflow to set subscription on.
$user = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey = optional_param('sesskey', null, PARAM_RAW);
$returnurl = optional_param('returnurl', null, PARAM_LOCALURL);

// Set the url to return to the same action.
$url = new moodle_url('/mod/moodleoverflow/subscribe.php', ['id' => $id]);
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
}

// Set the pages URL.
$PAGE->set_url($url);

// Get all necessary objects.
$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $moodleoverflow->course], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

// To subscribe to a moodleoverflow or a discussion, the user needs to be logged in.
require_login($course, false, $cm);

$discussion = null;
if (!is_null($discussionid)) {
    $discussion = moodleoverflow_get_record_or_exception(
        'moodleoverflow_discussions',
        ['id' => $discussionid, 'moodleoverflow' => $moodleoverflow->id],
        'invaliddiscussionid'
    );
}

// Define variables.
$notify = [];
$notify['success'] = \core\output\notification::NOTIFY_SUCCESS;
$notify['error'] = \core\output\notification::NOTIFY_ERROR;
$strings = [];
$strings['subscribeenrolledonly'] = get_string('subscribeenrolledonly', 'moodleoverflow');
$strings['everyoneisnowsubscribed'] = get_string('everyoneisnowsubscribed', 'moodleoverflow');

// Check if the user was requesting the subscription himself.
if ($user) {
    // A manager requested the subscription.

    // Check the login.
    require_sesskey();

    // Check the users capabilities.
    if (!has_capability('mod/moodleoverflow:managesubscriptions', $context)) {
        throw new moodle_exception('nopermissiontosubscribe', 'moodleoverflow');
    }

    // Retrieve the user from the database.
    $user = $DB->get_record('user', ['id' => $user], '*', MUST_EXIST);
} else {
    // The user requested the subscription himself.
    $user = $USER;
}

// Check if the user is already subscribed.
$issubscribed = subscriptions::is_subscribed($user->id, $moodleoverflow, $context, $discussionid);

// Guests, visitors and not enrolled people cannot subscribe.
$isenrolled = is_enrolled($context, $user, '', true);
if (!$isenrolled) {
    // Prepare the output.
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);

    // Redirect guest users to a login page.
    if (isguestuser()) {
        echo $OUTPUT->header();
        $message = $strings['subscribeenrolledonly'] . '<br /><br />' . get_string('liketologin');
        $url = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $id]);
        echo $OUTPUT->confirm($message, get_login_url(), $url);
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place. Just redirect.
        $url = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $id]);
        redirect($url, $strings['subscribeenrolledonly'], null, $notify['error']);
    }
}

// Create the url to redirect the user back to where he is coming from.
$urlindex = 'index.php?id=' . $course->id;
$urlview = 'view.php?m=' . $id;
$returnto = optional_param('backtoindex', 0, PARAM_INT) ? $urlindex : $urlview;
if ($returnurl) {
    $returnto = $returnurl;
}

// Redirect the user back if the user is forced to be subscribed.
$isforced = subscriptions::is_forcesubscribed($moodleoverflow);
if ($isforced && has_capability('mod/moodleoverflow:allowforcesubscribe', $context, $user)) {
    redirect($returnto, $strings['everyoneisnowsubscribed'], null, $notify['success']);
}

// Create an info object.
$info = new stdClass();
$info->name = fullname($user);
$info->moodleoverflow = format_string($moodleoverflow->name);

// Check if the user is subscribed to the moodleoverflow.
// The action is to unsubscribe the user.
if ($issubscribed) {
    // Check if there is a sesskey.
    if (is_null($sesskey)) {
        // Perpare the output.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        // Create an url to get back to the view.
        $viewurl = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $id]);

        // Was a discussion id submitted?
        if ($discussionid) {
            // Create a new info object.
            $info2 = new stdClass();
            $info2->moodleoverflow = format_string($moodleoverflow->name);
            $info2->discussion = format_string($discussion->name);

            // Create a confirm statement.
            $string = get_string('confirmunsubscribediscussion', 'moodleoverflow', $info2);
            echo $OUTPUT->confirm($string, $PAGE->url, $viewurl);
        } else {
            // The discussion is not involved.

            // Create a confirm statement.
            $string = get_string('confirmunsubscribe', 'moodleoverflow', format_string($moodleoverflow->name));
            echo $OUTPUT->confirm($string, $PAGE->url, $viewurl);
        }

        // Print the rest of the page.
        echo $OUTPUT->footer();
        exit;
    }

    // From now on, a valid session key needs to be set.
    require_sesskey();

    // Check if a discussion id is submitted.
    if ($discussionid === null) {
        // Unsubscribe the user from the moodleoverflow.
        subscriptions::unsubscribe_user($user->id, $moodleoverflow, $context, true);
        redirect($returnto, get_string('nownotsubscribed', 'moodleoverflow', $info), null, $notify['success']);
    } else {
        // Unsubscribe the user from the discussion. False only means that nothing had to change.
        subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context);
        $info->discussion = $discussion->name;
        redirect($returnto, get_string('discussionnownotsubscribed', 'moodleoverflow', $info), null, $notify['success']);
    }
} else {
    // The user needs to be subscribed.

    // Check the capabilities.
    $capabilities = [];
    $capabilities['managesubscriptions'] = has_capability('mod/moodleoverflow:managesubscriptions', $context);
    $capabilities['viewdiscussion'] = has_capability('mod/moodleoverflow:viewdiscussion', $context, $user);

    // Check if subscriptionsare allowed.
    $disabled = subscriptions::subscription_disabled($moodleoverflow);
    if ($disabled && !$capabilities['managesubscriptions']) {
        throw new moodle_exception('disallowsubscribe', 'moodleoverflow', get_local_referer(false));
    }

    // Check if the user can view discussions.
    if (!$capabilities['viewdiscussion']) {
        throw new moodle_exception('noviewdiscussionspermission', 'moodleoverflow', get_local_referer(false));
    }

    // Check the session key.
    if (is_null($sesskey)) {
        // Prepare the output.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        // Create the url to redirect the user back to.
        $viewurl = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $id]);

        // Check whether a discussion is referenced.
        if ($discussionid) {
            // Create a new info object.
            $info2 = new stdClass();
            $info2->moodleoverflow = format_string($moodleoverflow->name);
            $info2->discussion = format_string($discussion->name);

            // Create a confirm dialog.
            $string = get_string('confirmsubscribediscussion', 'moodleoverflow', $info2);
            echo $OUTPUT->confirm($string, $PAGE->url, $viewurl);
        } else {
            // No discussion is referenced.

            // Create a confirm dialog.
            $string = get_string('confirmsubscribe', 'moodleoverflow', format_string($moodleoverflow->name));
            echo $OUTPUT->confirm($string, $PAGE->url, $viewurl);
        }

        // Print the missing part of the page.
        echo $OUTPUT->footer();
        exit;
    }

    // From now on, there needs to be a valid session key.
    require_sesskey();

    // Check if the subscription is refered to a discussion.
    if ($discussionid == null) {
        // Subscribe the user to the moodleoverflow instance.
        subscriptions::subscribe_user($user->id, $moodleoverflow, $context, true);
        redirect($returnto, get_string('nowsubscribed', 'moodleoverflow', $info), null, $notify['success']);
    } else {
        $info->discussion = $discussion->name;

        // Subscribe the user to the discussion.
        subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect($returnto, get_string('discussionnowsubscribed', 'moodleoverflow', $info), null, $notify['success']);
    }
}
