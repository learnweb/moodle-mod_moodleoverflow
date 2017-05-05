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

//
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

// Declare optional parameters.
$reply          = optional_param('reply', 0, PARAM_INT);
$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);


$PAGE->set_url('/mod/moodleoverflow/post.php', array(
    'reply' => $reply,
    'moodleoverflow' => $moodleoverflow,
    // ToDo: es fehlen: edit, delete, prune, name, confirm, groupid.
));

// These page_params will be passed as hidden variables later in the form.
$page_params = array('reply' => $reply, 'moodleoverflow' => $moodleoverflow); // ToDo: edit fehlt.

// Retrieve the context.
$sitecontext = context_system::instance();

// Catch unauthenticated users.
if (!isloggedin() OR isguestuser()) {

    // The user is probably coming in via email.
    if (!isloggedin() AND !get_local_referer()) {
        //require_login();
    }

    // Starting a new moodleoverflow discussion.
    if (!empty($moodleoverflow)) {
        if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
            print_error('invalidmoodleoverflowid', 'moodleoverflow');
        }

    // Writing a new reply.
    } else if (!empty($reply)) {

        // Check if there is a valid parent post.
        if (! $parent = moodleoverflow_get_post_full($reply)) {
            print_error('invalidparentpostid', 'moodleoverflow');
        }

        // Check if the parent post is related to a discussion.
        if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'moodleoverflow');
        }

        // Check if the discussion is related to a moodleoverflow instance.
        if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->forum))) {
            print_error('invalidmoodleoverflowid', 'moodleoverflow');
        }
    }

    // Check if the moodleoverflow instance is related to a course.
    if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
        print_error('invalidcourseid');
    }

    // Generate a context module for the instance and the course.
    if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    // Print the page.
    $PAGE->set_cm($cm, $course, $moodleoverflow);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'moodleoverflow') . '<br /><br />' .
        get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit();
}

// Require a login.
require_login(0, false);

// Starting a new discussion.
if (!empty($moodleoverflow)) {

    // Check if the moodleoverflow instance exists.
    if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflow))) {
        print_error('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Check if the moodleoverflow instance is related to a course.
    if (! $course = $DB->get_record('course', array('id' => $moodleoverflow->course))) {
        print_error('invalidcourseid');
    }

    // Check if the coursemodule can be retrieved.
    if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // If the user cant post in this discussion, allow him to enrol himself.
    if (! moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array(
                            'id' => $course->id,
                            'returnurl' => '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id
                        )), get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostmoodleoverflow', 'moodleoverflow');
    }

    // Check if the activity is actually hidden.
    if (!$cm->visible AND !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Set the referer.
    $SESSION->formurl = get_local_referer(false);

    // Load the $post variable.
    $post = new stdClass();
    $post->course = $course-id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->discussion = 0; // The discussion # is not defined yet.
    $post->parent = 0;
    $post->subject = '';
    $post->userid = $USER->id;
    $post->message = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust = 0;

    // Allow to calculate the correct url later.
    unset($SESSION->fromdiscussion);

// Writing a new reply.
} else if (!empty($reply)) {
    echo 'New reply.';

// Editing own post.
} else if (!empty($edit)) {
    echo 'Editing own post.';

// Deleting a posts.
} else if (!empty($delete)) {
    echo 'Deleting a post.';

// Pruning.
} else if (!empty($prune)) {
    echo 'Pruning.';

// Other
} else {
    print_error('unknowaction');
}

// Check if the coursecontext has yet been set.
if (!isset($coursecontext)) {
    $coursecontext = context_course::instance($moodleoverflow->course);
}

// The user must be logged on properly from now on.
// Retrieve the coursemodule.
if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

// Do not allow guest users to access.
// This is already checked. But just in case.
if (isguestuser()) {
    print_error('noguest');
}













