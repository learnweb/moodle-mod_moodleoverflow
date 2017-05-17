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

$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);
$edit           = optional_param('edit', 0, PARAM_INT);
$reply          = optional_param('reply', 0, PARAM_INT);
$confirm        = optional_param('confirm', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/post.php', array(
        'reply'          => $reply,
        'moodleoverflow' => $moodleoverflow,
        'edit'           => $edit,
        'confirm'        => $confirm,
));

// These params will be passed as hidden variables later in the form.
$pageparams = array('moodleoverflow' => $moodleoverflow, 'reply' => $reply, 'edit' => $edit);

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
    echo $OUTPUT->confirm(get_string('noguestpost', 'forum').'<br /><br />'.get_string('liketologin'), get_login_url(), 'view.php?m=' . $moodleoverflow->id);
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
    $post->discussion     = 0; // Not defined yet.
    $post->parent         = 0;
    $post->subject        = ''; // Not defined yet.
    $post->userid         = $USER->id;
    $post->message        = ''; // Not defined yet.

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);


// Second posibility: User is replying to a discussion in a moodleoverflow instance.
} else if (!empty($reply)) {

    // Check if the post exists.
    if (!$parent = moodleoverflow_get_post_full($reply)) {
        print_error('invalidparentpostid', 'moodleoverflow');
    }

    // Check if the post is related to a valid discussion.
    if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'moodleoverflow');
    }

    // Check if the post is related to a valid moodleoverflow instance.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
        print_error('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Check for the related course.
    if (!$course = $DB->get_record('course', array('id' => $discussion->course))) {
        print_error('invalidcourseid');
    }

    // Check for the related coursemodule.
    if (!$cm = get_coursemodule_from_instance("moodleoverflow", $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Set the page's context.
    $PAGE->set_cm($cm, $course, $moodleoverflow);

    // Retrieve the other contexts.
    $modulecontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // Check if the user can start a new discussion.
    if (!moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $modulecontext)) {

        // Catch unenrolled user.
        if (!isguestuser() AND !is_enrolled($coursecontext)) {
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

    // Load all the $post variables.
    $post = new stdClass();
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->discussion = $parent->discussion;
    $post->parent = $parent->id;
    $post->subject = $parent->subject;
    $post->userid = $USER->id;
    $post->message = '';

    // Set the subject.
    $strre = get_string('re', 'moodleoverflow');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre . ' ' . $post->subject;
    }

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);

// Third posibility: User is editing a post of the discussion in a moodleoverflow instance.
} else if (!empty($edit)) {

    // Check the post exitst.
    if (! $post = forum_get_post_full($edit)) {
        print_error('invalidpostid', 'moodleoverflow');
    }

    // Check the parent post.
    if ($post->parent) {
        if (! $parent = forum_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'moodleoverflow');
        }
    }

    // Check if the post is related to a valid discussion.
    if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion))) {
        print_error('notpartofdiscussion', 'moodleoverflow');
    }

    // Check if the post is related to a valid moodleoverflow instance.
    if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
        print_error('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Check if the post is related to a valid course.
    if (! $course = $DB->get_record('course', array('id' => $discussion->course))) {
        print_error('invalidcourseid');
    }

    // Get the coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Get the modulecontext.
    $modulecontext = context_module::instance($cm->id);

    // Set the pagecontext.
    $PAGE->set_cm($cm, $course, $moodleoverflow);

    // Editing the own post?
    if (($post->userid <> $USER->id) AND !has_capability('mod/moodleoverflow:editanypost', $modulecontext)) {
        print_error('cannoteditposts', 'moodleoverflow');
    }

    // Load all $post variables.
    $post->edit           = $edit;
    $post->course         = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;

    $post = trusttext_pre_edit($post, 'message', $modulecontext);

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);


// Last posibility: the action is not known.
} else {
    print_error('unknownaction');
}

// Second step: The user must be logged on properly. Must be enrolled to the course as well.
require_login($course, false, $cm);

$groupmode = groups_get_activity_groupmode($cm, $course);
$groupdata = groups_get_activity_allowed_groups($cm);

// Prepare the form.
$modformpost = new mod_moodleoverflow_post_form2('post.php', array('course' => $course,
                                                                  'cm' => $cm,
                                                                  'coursecontext' => $coursecontext,
                                                                  'modulecontext' => $modulecontext,
                                                                  'moodleoverflow' => $moodleoverflow,
                                                                  'post' => $post,
                                                                  'edit' => $edit), 'post', '', array('id' => 'mformmoodleoverflow'));

// TODO: Not original author.

// Is it a reply or an answer?
$formheading = '';
if (!empty($parent)) {
    $heading = get_string('yourreply', 'moodleoverflow');
    $formheading = get_string('reply', 'moodleoverflow');
} else {
    $formheading = get_string('yournewtopic', 'moodleoverflow');
}

// Check the users capabilities to manage activities.
$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);

// TODO: Subscription Status

// Set the data for the form.
$modformpost->set_data(array(
        'general' => $formheading,
        'subject' => $post->subject,
        'message' => array(),
        'userid' => $post->userid,
        'parent' => $post->parent,
        'discussion' => $post->discussion,
        'course' => $course->id
    ) + $pageparams + (isset($discussion->id) ? array('discussion' => $discussion->id) : array()));

// Redirect the user back to the discussion or the moodleoverflow instance when cancelled.
if ($modformpost->is_cancelled()) {

    // a
    if (!isset($discussion->id)) {
        redirect(new moodle_url('/mod/moodleoverflow/view.php', array('m' => $moodleoverflow->id)));
    } else {
        redirect(new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $discussion->id)));
    }

} else if ($fromform = $modformpost->get_data()) {

    // TODO: Continue here.
}

// To get here, a post need to be edited.
// The $post variable will be loaded.
// The form will be brought up.

// $course and $moodleoverflow are defined. $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record('moodleoverflow_posts', array('discussion' => $post->discussion, 'parent' => 0))) {
        print_error('cannotfindparentpost', 'moodleoverflow', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = get_string("addanewdiscussion", "moodleoverflow");
}

// Catch empty variables.
if (empty($post->edit)) {
    $post->edit = '';
}
if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $moodleoverflow->name;
}
$forcefocus = empty($reply) ? NULL : 'message';

// Format the name of the discussion.
$strdiscussionname = format_string($discussion->name).':';

// Define the page's navbar.
if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discussion.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'moodleoverflow'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'moodleoverflow'));
}

// Define the page.
$PAGE->set_title($course->shortname . ': ' . $strdiscussionname . ' ' . format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

// Output the header.
//echo $OUTPUT->header();
//echo $OUTPUT->heading(format_string($moodleoverflow->name), 2);

// Check the user's capability to reply.
if (!empty($parent) AND !moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $user, $cm)) {
    print_error('cannotreply', 'moodleoverflow');
}

// Check the users capability to create a new discussion.
if (empty($parent) AND empty($edit) AND !moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $modulecontext)) {
    print_error('cannotcreatediscussion', 'moodleoverflow');
}

//
if (!empty($parent)) {
    if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'moodleoverflow');
    }

    debugging('posted a post', DEBUG_DEVELOPER); // TODO: Delete.
    echo 'posted a post';
    if (empty($post->edit)) {
        if (moodleoverflow_user_can_see_discussion($moodleoverflow, $discussion, $modulecontext)) {
            $moodleoverflowtracked = moodleoverflow_track_is_tracked($moodleoverflow);
            debugging('print posts threaded', DEBUG_DEVELOPER); // TODO: Delete.
            echo 'print posts threaded';
        }
    }
} else {
    if (!empty($moodleoverflow->intro)) {
        echo $OUTPUT->box(format_module_intro('moodleoverflow', $moodleoverflow, $cm->id), 'generalbox', 'intro');
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}

$modformpost->display();

//echo $OUTPUT->footer();