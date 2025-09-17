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
 * The file that is opened in Moodle when the user interacts with posts
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\post\post_control;
// Include config and locallib.
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\review;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
global $CFG, $USER, $DB, $PAGE, $SESSION, $OUTPUT;
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

// Declare optional url parameters.
$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);
$reply = optional_param('reply', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$SESSION->errorreturnurl = get_local_referer(false);
$PAGE->set_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $moodleoverflow, 'reply' => $reply, 'edit' => $edit,
                                                      'delete' => $delete, 'confirm' => $confirm, ]);

// These params will be passed as hidden variables later in the form.
$pageparams = ['moodleoverflow' => $moodleoverflow, 'reply' => $reply, 'edit' => $edit];

// Get the system context instance.
$systemcontext = context_system::instance();

// Create a post_control object to control and lead the process.
$postcontrol = new post_control();

// Put all interaction parameters in one object for the post_control.
$urlparameter = new stdClass();
$urlparameter->create = $moodleoverflow;
$urlparameter->reply = $reply;
$urlparameter->edit = $edit;
$urlparameter->delete = $delete;

// Catch guests.
if (!isloggedin() || isguestuser()) {
    // Gather information and set the page right so that user can be redirected to the right site.
    $information = $urlparameter->create ? $postcontrol->catch_guest(false, $urlparameter->create)
                                  : $postcontrol->catch_guest($urlparameter->reply ?: $urlparameter->edit ?: $urlparameter->delete);

    // The guest needs to login.
    $strlogin = get_string('noguestpost', 'forum') . '<br /><br />' . get_string('liketologin');
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        $strlogin,
        get_login_url(),
        $CFG->wwwroot . '/mod/moodleoverflow/view.php?m= ' . $information->moodleoverflow->id
    );
    echo $OUTPUT->footer();
    exit;
}

// Require a general login to post something.
// LEARNWEB-TODO: should course or id really be zero?.
require_login(0, false);

// Now the post_control checks which interaction is wanted and builds a prepost.
try {
    $postcontrol->detect_interaction($urlparameter);
} catch (moodle_exception $e) {
    $postcontrol->error_handling($e->getMessage());
}
// Get attributes from the postcontrol.
$information = $postcontrol->get_information();
$prepost = $postcontrol->get_prepost();

// If a post is being deleted, delete it immediately.
if ($postcontrol->get_interaction() == 'delete') {
    // Has the user confirmed the deletion?
    if (!empty($confirm) && confirm_sesskey()) {
        try {
            $postcontrol->execute_delete();
        } catch (moodle_exception $e) {
            $postcontrol->error_handling($e->getMessage());
        }
    } else {
        // Deletion needs to be confirmed.
        $postcontrol->confirm_delete();

        // Display a confirmation request depending on the number of posts that are being deleted.
        $information = $postcontrol->get_information();
        echo $OUTPUT->header();
        if ($information->deletetype == 'plural') {
            echo $OUTPUT->confirm(
                get_string('deletesureplural', 'moodleoverflow', $information->replycount + 1),
                'post.php?delete=' . $delete . '&confirm=' . $delete,
                $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $information->discussion->get_id() .
                '#p' . $information->relatedpost->get_id()
            );
        } else {
            echo $OUTPUT->confirm(
                get_string('deletesure', 'moodleoverflow', $information->replycount),
                "post.php?delete=$delete&confirm=$delete",
                $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $information->discussion->get_id() .
                '#p' . $information->relatedpost->get_id()
            );
        }
        echo $OUTPUT->footer();
    }
    exit;
}

// A post will be created or edited. For that the post_control builds a post_form.
$mformpost = $postcontrol->build_postform($pageparams);

// The User now entered information in the form. The post.php now needs to process the information and call the right function.

// If the interaction was cancelled, the user needs to be redirected.
if ($mformpost->is_cancelled()) {
    if (!isset($prepost->discussionid)) {
        redirect(new moodle_url('/mod/moodleoverflow/view.php', ['m' => $prepost->moodleoverflowid]));
    } else {
        redirect(new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $prepost->discussionid]));
    }
}

// If the post_form is submitted, the post_control executes the right function.
if ($fromform = $mformpost->get_data()) {
    try {
        $postcontrol->execute_interaction($fromform);
    } catch (moodle_exception $e) {
        $postcontrol->error_handling($e->getMessage());
    }
    exit;
}

// If the script gets to this point, nothing has been submitted.
// The post_form will be displayed.

// Define the message to be displayed above the form.
$toppost = new stdClass();
$toppost->subject = get_string('addanewdiscussion', 'moodleoverflow');

// Initiate the page.
$PAGE->set_title($information->course->shortname . ': ' .
                 $information->moodleoverflow->name . ' ' .
                 format_string($toppost->subject));
$PAGE->set_heading($information->course->fullname);
$PAGE->add_body_class('limitedwidth');

// Display all.
echo $OUTPUT->header();
$mformpost->display();
echo $OUTPUT->footer();
