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
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/moodleoverflow/locallib.php');

// Declare optional parameters.
$d = required_param('d', PARAM_INT); // The ID of the discussion.
$ratingid = optional_param('r', 0, PARAM_INT);
$ratedpost = optional_param('rp', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/discussion.php', array('d' => $d));

// Check if the discussion is valid.
if (! $discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $d))) {
    print_error('invaliddiscussionid', 'moodleoverflow');
}

// Check if the related moodleoverflow instance is valid.
if (! $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
    print_error('invalidmoodleoverflowid', 'moodleoverflow');
}

// Check if the related moodleoverflow instance is valid.
if (! $course = $DB->get_record('course', array('id' => $discussion->course))) {
    print_error('invalidcourseid');
}

// Get the related coursemodule and its context.
if (! $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
    print_error('invalidcoursemodule');
}

// Set the modulecontext.
$modulecontext = context_module::instance($cm->id);

// A user must be logged in and enrolled to the course.
require_course_login($course, true, $cm);

// Check if the user has the capability to view discussions.
$canviewdiscussion = has_capability('mod/moodleoverflow:viewdiscussion', $modulecontext);
if (!$canviewdiscussion) {
    notice(get_string('noviewdiscussionspermission', 'moodleoverflow'));
}

// Has a request to rate a post been submitted?
if ($ratingid) {
    if (!\mod_moodleoverflow\ratings::moodleoverflow_add_rating($moodleoverflow, $ratedpost, $ratingid, $cm)) {
        print_error('ratingfailed', 'moodleoverflow');
    }
    redirect(new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $discussion->id)));
}

// TODO: Trigger the DISCUSSION-VIEW-Event.

// Unset where the user is coming from.
// Allows to calculate the correct return url later.
unset($SESSION->fromdiscussion);

// Get the parent post.
$parent = $discussion->firstpost;
if (! $post = moodleoverflow_get_post_full($parent)) {
    print_error("notexists", 'moodleoverflow', "$CFG->wwwroot/mod/moodleoverflow/view.php?m=$moodleoverflow->id");
}

// Has the user the capability to view the post?
if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'moodleoverflow', "$CFG->wwwroot/mod/moodleoverflow/view.php?m=$moodleoverflow->id");
}

// TODO: User mark posts as (un-)read?!
// TODO: Search-Form?!

// Append the discussion name to the navigation.
$forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($forumnode)) {
    $forumnode = $PAGE->navbar;
} else {
    $forumnode->make_active();
}

$node = $forumnode->add(format_string($discussion->name),
    new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $discussion->id)));
$node->display = false;
if ($node AND ($post->id != $discussion->firstpost)) {
    $node->add(format_string($post->subject), $PAGE->url);
}

// Initiate the page.
$PAGE->set_title($course->shortname . ': ' . format_string($discussion->name));
$PAGE->set_heading($course->fullname);

// Include the renderer.
$renderer = $PAGE->get_renderer('mod_moodleoverflow');

// Start the side-output.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($moodleoverflow->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// Guests and users can not subscribe to a discussion.
if ((!is_guest($modulecontext, $USER) AND isloggedin() AND $canviewdiscussion)) {

    // TODO: Subscription Handling.;
    echo '';
}

// Check if the user can reply in this discussion.
$canreply = moodleoverflow_user_can_post($moodleoverflow, $discussion, $USER, $cm, $course, $modulecontext);

// Link to the selfenrollment if not allowed.
if (!$canreply) {
    if (!is_enrolled($modulecontext) AND !is_viewing($modulecontext)) {
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// TODO: Neighbour Discussions?

// TODO: Discussion ontrols?
// TODO: -> Move discussion?
// TODO: -> Pin discussion?

// TODO: Capability: view qanda without posting?

echo "<br><br>";

moodleoverflow_print_discussion($course, $cm, $moodleoverflow, $discussion, $post, $canreply);


echo $OUTPUT->footer();