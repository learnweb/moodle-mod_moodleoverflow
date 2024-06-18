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
 * The file to manage posts.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// TODO refactor this. For more readability, and to avoid security issues.

// Include config and locallib.
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\review;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
global $CFG, $USER, $DB, $PAGE, $SESSION, $OUTPUT;
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

// Declare optional parameters.
$moodleoverflow = optional_param('moodleoverflow', 0, PARAM_INT);
$reply = optional_param('reply', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$count = 0;
$count += $moodleoverflow ? 1 : 0;
$count += $reply ? 1 : 0;
$count += $edit ? 1 : 0;
$count += $delete ? 1 : 0;

if ($count !== 1) {
    throw new coding_exception('Exactly one parameter should be specified!');
}

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/post.php', [
    'moodleoverflow' => $moodleoverflow,
    'reply' => $reply,
    'edit' => $edit,
    'delete' => $delete,
    'confirm' => $confirm,
]);

// These params will be passed as hidden variables later in the form.
$pageparams = ['moodleoverflow' => $moodleoverflow, 'reply' => $reply, 'edit' => $edit];

// Get the system context instance.
$systemcontext = context_system::instance();

// Catch guests.
if (!isloggedin() || isguestuser()) {

    // The user is starting a new discussion in a moodleoverflow instance.
    if (!empty($moodleoverflow)) {

        // Check the moodleoverflow instance is valid.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow])) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }

        // The user is replying to an existing moodleoverflow discussion.
    } else if (!empty($reply)) {

        // Check if the related post exists.
        if (!$parent = moodleoverflow_get_post_full($reply)) {
            throw new moodle_exception('invalidparentpostid', 'moodleoverflow');
        }

        // Check if the post is part of a valid discussion.
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $parent->discussion])) {
            throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
        }

        // Check if the post is related to a valid moodleoverflow instance.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow])) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }
    }

    // Get the related course.
    if (!$course = $DB->get_record('course', ['id' => $moodleoverflow->course])) {
        throw new moodle_exception('invalidcourseid');
    }

    // Get the related coursemodule and its context.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Get the context of the module.
    $modulecontext = context_module::instance($cm->id);

    // Set parameters for the page.
    $PAGE->set_cm($cm, $course, $moodleoverflow);
    $PAGE->set_context($modulecontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);

    // The page should not be large, only pages containing broad tables are usually.
    $PAGE->add_body_class('limitedwidth');

    // The guest needs to login.
    echo $OUTPUT->header();
    $strlogin = get_string('noguestpost', 'forum') . '<br /><br />' . get_string('liketologin');
    echo $OUTPUT->confirm($strlogin, get_login_url(), $CFG->wwwroot . '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id);
    echo $OUTPUT->footer();
    exit;
}

// First step: A general login is needed to post something.
require_login(0, false);

// First possibility: User is starting a new discussion in a moodleoverflow instance.
if (!empty($moodleoverflow)) {

    // Check the moodleoverflow instance is valid.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow])) {
        throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Get the related course.
    if (!$course = $DB->get_record('course', ['id' => $moodleoverflow->course])) {
        throw new moodle_exception('invalidcourseid');
    }

    // Get the related coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Retrieve the contexts.
    $modulecontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // Check if the user can start a new discussion.
    if (!moodleoverflow_user_can_post_discussion($moodleoverflow, $cm, $modulecontext)) {

        // Catch unenrolled user.
        if (!isguestuser() && !is_enrolled($coursecontext)) {
            if (enrol_selfenrol_available($course->id)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', [
                    'id' => $course->id,
                    'returnurl' => '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id,
                ]), get_string('youneedtoenrol'));
            }
        }

        // Notify the user, that he can not post a new discussion.
        throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
    }

    // Where is the user coming from?
    $SESSION->fromurl = get_local_referer(false);

    // Load all the $post variables.
    $post = new stdClass();
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->discussion = 0;
    $post->parent = 0;
    $post->subject = '';
    $post->userid = $USER->id;
    $post->message = '';

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);

} else if (!empty($reply)) {
    // Second possibility: The user is writing a new reply.

    // Check if the post exists.
    if (!$parent = moodleoverflow_get_post_full($reply)) {
        throw new moodle_exception('invalidparentpostid', 'moodleoverflow');
    }

    // Check if the post is part of a discussion.
    if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $parent->discussion])) {
        throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
    }

    // Check if the discussion is part of a moodleoverflow instance.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow])) {
        throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Check if the moodleoverflow instance is part of a course.
    if (!$course = $DB->get_record('course', ['id' => $discussion->course])) {
        throw new moodle_exception('invalidcourseid');
    }

    // Retrieve the related coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Ensure the coursemodule is set correctly.
    $PAGE->set_cm($cm, $course, $moodleoverflow);

    // Retrieve the other contexts.
    $modulecontext = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    // Check if Limitedanswertime is on. If so, replies are not possible.
    $limitedanswersetting = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id], 'la_starttime, la_endtime');
    $lastarttime = $limitedanswersetting->la_starttime;
    $laendtime = $limitedanswersetting->la_endtime;

    $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
    $iseditteacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

    $roleidteacher = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
    $isteacher = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleidteacher]);

    if (($lastarttime > time() || $laendtime != 0 && $laendtime < time()) &&
        (!has_capability('mod/moodleoverflow:addinstance', $modulecontext))) {
        // Redirect to the moodleoverflow.
        $link = new \moodle_url('/mod/moodleoverflow/view.php', ['id' => $cm->id]);
        redirect($link);
    }

    // Check whether the user is allowed to post.
    if (!moodleoverflow_user_can_post($modulecontext, $parent)) {

        // Give the user the chance to enroll himself to the course.
        if (!isguestuser() && !is_enrolled($coursecontext)) {
            $SESSION->wantsurl = qualified_me();
            $SESSION->enrolcancel = get_local_referer(false);
            redirect(new moodle_url('/enrol/index.php',
                ['id' => $course->id, 'returnurl' => '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id]),
                get_string('youneedtoenrol'));
        }

        // Print the error message.
        throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
    }

    // Make sure the user can post here.
    if (!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $modulecontext)) {
        throw new moodle_exception('activityiscurrentlyhidden');
    }

    // Load the $post variable.
    $post = new stdClass();
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;
    $post->discussion = $parent->discussion;
    $post->parent = $parent->id;
    $post->subject = $discussion->name;
    $post->userid = $USER->id;
    $post->message = '';

    // Append 'RE: ' to the discussions subject.
    $strre = get_string('re', 'moodleoverflow');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre . ' ' . $post->subject;
    }

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);


} else if (!empty($edit)) {
    // Third possibility: The user is editing his own post.

    // Check if the submitted post exists.
    if (!$post = moodleoverflow_get_post_full($edit)) {
        throw new moodle_exception('invalidpostid', 'moodleoverflow');
    }

    // Get the parent post of this post if it is not the starting post of the discussion.
    if ($post->parent) {
        if (!$parent = moodleoverflow_get_post_full($post->parent)) {
            throw new moodle_exception('invalidparentpostid', 'moodleoverflow');
        }
    }

    // Check if the post refers to a valid discussion.
    if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $post->discussion])) {
        throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
    }

    // Check if the post refers to a valid moodleoverflow instance.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow])) {
        throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
    }

    // Check if the post refers to a valid course.
    if (!$course = $DB->get_record('course', ['id' => $discussion->course])) {
        throw new moodle_exception('invalidcourseid');
    }

    // Retrieve the related coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
        throw new moodle_exception('invalidcoursemodule');
    } else {
        $modulecontext = context_module::instance($cm->id);
    }

    // Set the pages context.
    $PAGE->set_cm($cm, $course, $moodleoverflow);

    // Check if the post can be edited.
    $beyondtime = ((time() - $post->created) > get_config('moodleoverflow', 'maxeditingtime'));
    $alreadyreviewed = review::should_post_be_reviewed($post, $moodleoverflow) && $post->reviewed;
    if (($beyondtime || $alreadyreviewed) && !has_capability('mod/moodleoverflow:editanypost', $modulecontext)) {
        throw new moodle_exception('maxtimehaspassed', 'moodleoverflow', '',
            format_time(get_config('moodleoverflow', 'maxeditingtime')));
    }



    // If the current user is not the one who posted this post.
    if ($post->userid <> $USER->id) {

        // Check if the current user has not the capability to edit any post.
        if (!has_capability('mod/moodleoverflow:editanypost', $modulecontext)) {

            // Display the error. Capabilities are missing.
            throw new moodle_exception('cannoteditposts', 'moodleoverflow');
        }
    }

    // Load the $post variable.
    $post->edit = $edit;
    $post->course = $course->id;
    $post->moodleoverflow = $moodleoverflow->id;

    // Unset where the user is coming from.
    // Allows to calculate the correct return url later.
    unset($SESSION->fromdiscussion);

} else if (!empty($delete)) {
    // Fourth possibility: The user is deleting a post.
    // Check if the post is existing.
    if (!$post = moodleoverflow_get_post_full($delete)) {
        throw new moodle_exception('invalidpostid', 'moodleoverflow');
    }

    // Get the related discussion.
    if (!$discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $post->discussion])) {
        throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
    }

    // Get the related moodleoverflow instance.
    if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow])) {
        throw new moodle_exception('invalidmoodleoverflowid', 'moodleoveflow');
    }

    // Get the related coursemodule.
    if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course)) {
        throw new moodle_exception('invalidcoursemodule');
    }

    // Get the related course.
    if (!$course = $DB->get_record('course', ['id' => $moodleoverflow->course])) {
        throw new moodle_exception('invalidcourseid');
    }

    // Require a login and retrieve the modulecontext.
    require_login($course, false, $cm);
    $modulecontext = context_module::instance($cm->id);

    // Check some capabilities.
    $deleteownpost = has_capability('mod/moodleoverflow:deleteownpost', $modulecontext);
    $deleteanypost = has_capability('mod/moodleoverflow:deleteanypost', $modulecontext);
    if (!(($post->userid == $USER->id && $deleteownpost) || $deleteanypost)) {
        throw new moodle_exception('cannotdeletepost', 'moodleoverflow');
    }

    // Count all replies of this post.
    $replycount = moodleoverflow_count_replies($post, false);

    // Has the user confirmed the deletion?
    if (!empty($confirm) && confirm_sesskey()) {

        // Check if the user has the capability to delete the post.
        $timepassed = time() - $post->created;
        if (($timepassed > get_config('moodleoverflow', 'maxeditingtime')) && !$deleteanypost) {
            $url = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $post->discussion]);
            throw new moodle_exception('cannotdeletepost', 'moodleoverflow', moodleoverflow_go_back_to($url));
        }

        // A normal user cannot delete his post if there are direct replies.
        if ($replycount && !$deleteanypost) {
            $url = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $post->discussion]);
            throw new moodle_exception('couldnotdeletereplies', 'moodleoverflow', moodleoverflow_go_back_to($url));
        } else {
            // Delete the post.

            // The post is the starting post of a discussion. Delete the topic as well.
            if (!$post->parent) {
                moodleoverflow_delete_discussion($discussion, $course, $cm, $moodleoverflow);

                // Trigger the discussion deleted event.
                $params = [
                    'objectid' => $discussion->id,
                    'context' => $modulecontext,
                ];

                $event = \mod_moodleoverflow\event\discussion_deleted::create($params);
                $event->trigger();

                // Redirect the user back to start page of the moodleoverflow instance.
                redirect("view.php?m=$discussion->moodleoverflow");
                exit;

            } else if (moodleoverflow_delete_post($post, $deleteanypost, $cm, $moodleoverflow)) {
                // Delete a single post.
                // Redirect back to the discussion.
                $discussionurl = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $discussion->id]);
                redirect(moodleoverflow_go_back_to($discussionurl));
                exit;

            } else {
                // Something went wrong.
                throw new moodle_exception('errorwhiledelete', 'moodleoverflow');
            }
        }
    } else {
        // Deletion needs to be confirmed.

        moodleoverflow_set_return();
        $PAGE->navbar->add(get_string('delete', 'moodleoverflow'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        // The page should not be large, only pages containing broad tables are usually.
        $PAGE->add_body_class('limitedwidth');

        // Check if there are replies for the post.
        if ($replycount) {

            // Check if the user has capabilities to delete more than one post.
            if (!$deleteanypost) {
                throw new moodle_exception('couldnotdeletereplies', 'moodleoverflow',
                    moodleoverflow_go_back_to(new moodle_url('/mod/moodleoverflow/discussion.php',
                        ['d' => $post->discussion, 'p' . $post->id])));
            }

            // Request a confirmation to delete the post.
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(get_string("deletesureplural", "moodleoverflow", $replycount + 1),
                "post.php?delete=$delete&confirm=$delete", $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' .
                $post->discussion . '#p' . $post->id);

        } else {
            // Delete a single post.

            // Print a confirmation message.
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(get_string("deletesure", "moodleoverflow", $replycount),
                "post.php?delete=$delete&confirm=$delete",
                $CFG->wwwroot . '/mod/moodleoverflow/discussion.php?d=' . $post->discussion . '#p' . $post->id);
        }
    }
    echo $OUTPUT->footer();
    exit;

} else {
    // Last posibility: the action is not known.

    throw new moodle_exception('unknownaction');
}

// Second step: The user must be logged on properly. Must be enrolled to the course as well.
require_login($course, false, $cm);

// Get the contexts.
$modulecontext = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

// Get the subject.
if ($edit) {
    $subject = $discussion->name;
} else if ($reply) {
    $subject = $post->subject;
} else if ($moodleoverflow) {
    $subject = $post->subject;
}

// Get attachments.
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid,
    $modulecontext->id,
    'mod_moodleoverflow',
    'attachment',
    empty($post->id) ? null : $post->id,
    mod_moodleoverflow_post_form::attachment_options($moodleoverflow));

if ($draftitemid && $edit && anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)
    && $post->userid != $USER->id) {

    $usercontext = context_user::instance($USER->id);
    $anonymousstr = get_string('anonymous', 'moodleoverflow');
    foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid) as $file) {
        $file->set_author($anonymousstr);
    }
}

// Prepare the form.
$formarray = [
    'course' => $course,
    'cm' => $cm,
    'coursecontext' => $coursecontext,
    'modulecontext' => $modulecontext,
    'moodleoverflow' => $moodleoverflow,
    'post' => $post,
    'edit' => $edit,
];
$mformpost = new mod_moodleoverflow_post_form('post.php', $formarray, 'post', '', ['id' => 'mformmoodleoverflow']);

// The current user is not the original author.
// Append the message to the end of the message.
if ($USER->id != $post->userid) {

    // Create a temporary object.
    $data = new stdClass();
    $data->date = userdate($post->modified);
    $post->messageformat = editors_get_preferred_format();

    // Append the message depending on the messages format.
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id' . $USER->id .
            '&course=' . $post->course . '">' . fullname($USER) . '</a>';
        $post->message .= '<p><span class="edited">(' . get_string('editedby', 'moodleoverflow', $data) . ')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
    }

    // Delete the temporary object.
    unset($data);
}

// Define the heading for the form.
$formheading = '';
if (!empty($parent)) {
    $heading = get_string('yourreply', 'moodleoverflow');
    $formheading = get_string('reply', 'moodleoverflow');
} else {
    $heading = get_string('yournewtopic', 'moodleoverflow');
}

// Get the original post.
$postid = empty($post->id) ? null : $post->id;
$postmessage = empty($post->message) ? null : $post->message;

// Set data for the form.
// TODO Refactor.
$param1 = (isset($discussion->id) ? [$discussion->id] : []);
$param2 = (isset($post->format) ? ['format' => $post->format] : []);
$param3 = (isset($discussion->timestart) ? ['timestart' => $discussion->timestart] : []);
$param4 = (isset($discussion->timeend) ? ['timeend' => $discussion->timeend] : []);
$param5 = (isset($discussion->id) ? ['discussion' => $discussion->id] : []);
$mformpost->set_data([
        'attachments' => $draftitemid,
        'general' => $heading,
        'subject' => $subject,
        'message' => [
            'text' => $postmessage,
            'format' => editors_get_preferred_format(),
            'itemid' => $postid,
        ],
        'userid' => $post->userid,
        'parent' => $post->parent,
        'discussion' => $post->discussion,
        'course' => $course->id,
    ] + $pageparams + $param1 + $param2 + $param3 + $param4 + $param5);

// Is it canceled?
if ($mformpost->is_cancelled()) {

    // Redirect the user back.
    if (!isset($discussion->id)) {
        redirect(new moodle_url('/mod/moodleoverflow/view.php', ['m' => $moodleoverflow->id]));
    } else {
        redirect(new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $discussion->id]));
    }

    // Cancel.
    exit();
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
    $fromform->message = $fromform->message['text'];
    $fromform->messagetrust = trusttext_trusted($modulecontext);

    // If we are updating a post.
    if ($fromform->edit) {

        // Initiate some variables.
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        // The FORUM-Plugin had an bug: https://tracker.moodle.org/browse/MDL-4314
        // This is a fix for it.
        if (!$realpost = $DB->get_record('moodleoverflow_posts', ['id' => $fromform->id])) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }

        // Check the capabilities of the user.
        // He may proceed if he can edit any post or if he has the startnewdiscussion
        // capability or the capability to reply and is editing his own post.
        $editanypost = has_capability('mod/moodleoverflow:editanypost', $modulecontext);
        $replypost = has_capability('mod/moodleoverflow:replypost', $modulecontext);
        $startdiscussion = has_capability('mod/moodleoverflow:startdiscussion', $modulecontext);
        $ownpost = ($realpost->userid == $USER->id);
        if (!(($ownpost && ($replypost || $startdiscussion)) || $editanypost)) {
            throw new moodle_exception('cannotupdatepost', 'moodleoverflow');
        }

        // Update the post or print an error message.
        $updatepost = $fromform;
        $updatepost->moodleoverflow = $moodleoverflow->id;
        if (!moodleoverflow_update_post($updatepost, $mformpost)) {
            throw new moodle_exception('couldnotupdate', 'moodleoverflow', $errordestination);
        }

        // Create a success-message.
        if ($realpost->userid == $USER->id) {
            $message .= get_string('postupdated', 'moodleoverflow');
        } else {
            if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $realpost->userid)) {
                $name = get_string('anonymous', 'moodleoverflow');
            } else {
                $realuser = $DB->get_record('user', ['id' => $realpost->userid]);
                $name = fullname($realuser);
            }
            $message .= get_string('editedpostupdated', 'moodleoverflow', $name);
        }

        // Create a link to go back to the discussion.
        $discussionurl = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $discussion->id], 'p' . $fromform->id);

        // Set some parameters.
        $params = [
            'context' => $modulecontext,
            'objectid' => $fromform->id,
            'other' => [
                'discussionid' => $discussion->id,
                'moodleoverflowid' => $moodleoverflow->id,
            ], ];

        // If the editing user is not the original author, add the original author to the params.
        if ($realpost->userid != $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        // Trigger post updated event.
        $event = \mod_moodleoverflow\event\post_updated::create($params);
        $event->trigger();

        // Redirect back to the discussion.
        redirect(moodleoverflow_go_back_to($discussionurl), $message, null, \core\output\notification::NOTIFY_SUCCESS);

        // Cancel.
        exit;

    } else if ($fromform->discussion) {
        // Add a new post to an existing discussion.

        // Set some basic variables.
        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->moodleoverflow = $moodleoverflow->id;

        // Create the new post.
        if ($fromform->id = moodleoverflow_add_new_post($addpost)) {

            // Subscribe to this thread.
            $discussion = new \stdClass();
            $discussion->id = $fromform->discussion;
            $discussion->moodleoverflow = $moodleoverflow->id;
            \mod_moodleoverflow\subscriptions::moodleoverflow_post_subscription($fromform,
                $moodleoverflow, $discussion, $modulecontext);

            // Print a success-message.
            $message .= '<p>' . get_string("postaddedsuccess", "moodleoverflow") . '</p>';
            $message .= '<p>' . get_string("postaddedtimeleft", "moodleoverflow",
                    format_time(get_config('moodleoverflow', 'maxeditingtime'))) . '</p>';

            // Set the URL that links back to the discussion.
            $link = '/mod/moodleoverflow/discussion.php';
            $discussionurl = new moodle_url($link, ['d' => $discussion->id], 'p' . $fromform->id);

            // Trigger post created event.
            $params = [
                'context' => $modulecontext,
                'objectid' => $fromform->id,
                'other' => [
                    'discussionid' => $discussion->id,
                    'moodleoverflowid' => $moodleoverflow->id,
                ], ];
            $event = \mod_moodleoverflow\event\post_created::create($params);
            $event->trigger();
            redirect(
                moodleoverflow_go_back_to($discussionurl),
                $message,
                \core\output\notification::NOTIFY_SUCCESS
            );

            // Print an error if the answer could not be added.
        } else {
            throw new moodle_exception('couldnotadd', 'moodleoverflow', $errordestination);
        }

        // The post has been added.
        exit;

    } else {
        // Add a new discussion.

        // The location to redirect the user after successfully posting.
        $redirectto = new moodle_url('view.php', ['m' => $fromform->moodleoverflow]);

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        // Check if the user is allowed to post here.
        if (!moodleoverflow_user_can_post_discussion($moodleoverflow)) {
            throw new moodle_exception('cannotcreatediscussion', 'moodleoverflow');
        }

        // Check if the creation of the new discussion failed.
        if (!$discussion->id = moodleoverflow_add_discussion($discussion, $modulecontext)) {

            throw new moodle_exception('couldnotadd', 'moodleoverflow', $errordestination);

        } else {    // The creation of the new discussion was successful.

            $params = [
                'context' => $modulecontext,
                'objectid' => $discussion->id,
                'other' => [
                    'moodleoverflowid' => $moodleoverflow->id,
                ],
            ];

            $message = '<p>' . get_string("postaddedsuccess", "moodleoverflow") . '</p>';

            // Trigger the discussion created event.
            $params = [
                'context' => $modulecontext,
                'objectid' => $discussion->id,
            ];
            $event = \mod_moodleoverflow\event\discussion_created::create($params);
            $event->trigger();
            // Subscribe to this thread.
            $discussion->moodleoverflow = $moodleoverflow->id;
            \mod_moodleoverflow\subscriptions::moodleoverflow_post_subscription($fromform,
                $moodleoverflow, $discussion, $modulecontext);
        }

        // Redirect back to te discussion.
        redirect(moodleoverflow_go_back_to($redirectto->out()), $message, null, \core\output\notification::NOTIFY_SUCCESS);

        // Do not continue.
        exit;
    }
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

// The page should not be large, only pages containing broad tables are usually.
$PAGE->add_body_class('limitedwidth');

// Display the header.
echo $OUTPUT->header();

// Display the form.
$mformpost->display();

// Display the footer.
echo $OUTPUT->footer();
