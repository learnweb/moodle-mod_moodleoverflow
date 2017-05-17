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
 * @package   mod_moodleoverflow
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

// Declare optional parameters.
$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/post.php', array(
    'moodleoverflow' => $moodleoverflow
));

// These params will be passed as hidden variables later in the form.
$pageparams = array('moodleoverflow' => $moodleoverflow);

// Get the system context instance.
$systemcontext = context_system::instance();

// Catch guests.
if (!isloggedin() OR isguestuser()) {

    // The user is starting a new discussion in a moodleoverflow instance.
    if (!empty($moodleoverflow)) {

        // Check the moodleoverflow instance is valid.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
            print_error('invalidmoodleoverflowid', 'moodleoverflow');
        }
    }

    // Get the related course.
    if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
        print_error('invalidcourseid');
    }

    // Get the related coursemodule and its context.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Get the context of the module.
    $modulecontext = context_module::instance($cm->id);

    // Set parameters for the page.
    $PAGE->set_cm($cm, $course, $moodleoverflow);
    $PAGE->set_context($modulecontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);

    // The guest needs to login.
    echo $OUTPUT->header();
    $strlogin = get_string('noguestpost', 'forum') . '<br /><br />' . get_string('liketologin');
    echo $OUTPUT->confirm($strlogin, get_login_url(), 'view.php?m=' . $moodleoverflow->id);
    echo $OUTPUT->footer();
    exit;
}

// First step: A general login is needed to post something.
require_login(0, false);

// First posibility: User is starting a new discussion in a moodleoverflow instance.
if (!empty($moodleoverflow)) {

    // Check the moodleoverflow instance is valid.
    if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
        print_error('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Get the related course.
    if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
        print_error('invalidcourseid');
    }

    // Get the related coursemodule.
    if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Retrieve the contexts.
    $modulecontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // Check if the user can start a new discussion.
    if (! moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $modulecontext)) {

        // Catch unenrolled user.
        if (!isguestuser() AND !is_enrolled($cousecontext)) {
            if (enrol_selfenrol_available($course->id)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array(
                    'id' => $course->id,
                    'returnurl' => '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id
                )), get_string('youneedtoenrol'));
            }
        }

        // Notify the user, that he can not post a new discussion.
        print_error('nopostmoodleoverflow', 'moodleoverflow');
    }

    // Where is the user coming from?
    $SESSION->fromurl = get_local_referer(false);

    // Load all the $post variables.
    $post = new stdClass();
    $post->course         = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->discussion     = 0;
    $post->parent         = 0;
    $post->subject        = '';
    $post->userid         = $USER->id;
    $post->message        = '';

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);

} else {    // Last posibility: the action is not known.
    print_error('unknownaction');
}

// Second step: The user must be logged on properly. Must be enrolled to the course as well.
require_login($course, false, $cm);

// Prepare the form.
$formarray = array(
        'course'         => $course,
        'cm'             => $cm,
        'coursecontext'  => $coursecontext,
        'modulecontext'  => $modulecontext,
        'moodleoverflow' => $moodleoverflow,
        'post'           => $post
);
$mformpost = new mod_moodleoverflow_post_form('post.php', $formarray, 'post', '', array('id' => 'mformmoodleoverflow'));

// Find string.
$formheading = get_string('yournewtopic', 'moodleoverflow');

// Set data for the form.
$mformpost->set_data(array(
    'general' => $formheading,
    'subject' => $post->subject,
    'message' => array(
        'text'   => '', // Edit: $currenttext.
        'format' => editors_get_preferred_format(),
        'itemid' => '', // Edit: $draftid_editor.
    ),
    'userid' => $post->userid,
    'parent' => $post->parent,
    'discussion' => $post->discussion,
    'course' => $course->id) +
    $pageparams +
    (isset($discussion->id) ? array($discussion->id) : array())
    );

// Is it canceled?
if ($mformpost->is_cancelled()) {
    if (!isset($discussion->id)) {
        redirect(new moodle_url('/mod/moodleoverflow/view.php', array('m' => $moodleoverflow->id)));
    } else {
        redirect(new moodle_url('/mod/moodleoverflow/discuss.php', array('d' => $discussion->id)));
    }
}

// Is it submitted?
if ($fromform = $mformpost->get_data()) {

    // Redirect url in case of occuring errors.
    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/moodleoverflow/view.php?m=$moodleoverflow->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    // Format the submitted data.
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    $fromform->messagetrust = trusttext_trusted($modulecontext);

    //
    // ADD A NEW DISCUSSION.
    //

    // The location to redirect the user after successfully posting.
    $redirectto = new moodle_url('view.php', array('m' => $fromform->moodleoverflow));

    // TODO: mailnow?

    $discussion = $fromform;
    $discussion->name = $fromform->subject;

    // Check if the user is allowed to post here.
    if (!moodleoverflow_user_can_post_discussion($moodleoverflow)) {
        print_error('cannotcreatediscussion', 'moodleoverflow');
    }

    // Check if the creation of the new discussion failed.
    if (! $discussion->id = moodleoverflow_add_discussion($discussion)) {

        print_error('couldnotadd', 'moodleoverflow', $errordestination);

    } else {    // The creation of the new discussion was successful.

        $params = array(
            'context' => $modulecontext,
            'objectid' => $discussion->id,
            'other' => array(
                'moodleoverflowid' => $moodleoverflow->id,
            )
        );

        $message = '<p>'.get_string("postaddedsuccess", "moodleoverflow") . '</p>';

        // TODO: Create Event.
        // TODO: Mailnow?
        // TODO: Subscribe-Message.
    }

    // TODO:  Completion Status?

    // Redirect back to te discussion.
    redirect(
        moodleoverflow_go_back_to($redirectto->out()),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// If the script gets to this point, nothing has been submitted.
// We have to display the form.
// $course and $moodleoverflow are defined.
// $discussion is only used for replying and editing.

// Define the message to be displayed above the form.
$toppost = new stdClass();
$toppost->subject = get_string("addanewdiscussion", "moodleoverflow");

// Initiate the page.
$PAGE->set_title("$course->shortname: $moodleoverflow->name " . format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

// Display the header.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($moodleoverflow->name), 2);

// Check the capabilities of the user again, just to be sure.
if (!moodleoverflow_user_can_post_discussion($moodleoverflow)) {
    print_error('cannotcreatediscussion', 'moodleoverflow');
}

// Show the description of the instance.
if (!empty($moodleoverflow->intro)) {
    echo $OUTPUT->box(format_module_intro('moodleoverflow', $moodleoverflow, $cm->id), 'generalbox', 'intro');
}

// Display the form.
$mformpost->display();

// Display the footer.
echo $OUTPUT->footer();