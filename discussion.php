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
 * File to display a moodleoverflow discussion.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once('../../config.php');
global $CFG, $DB, $PAGE, $USER, $SESSION, $OUTPUT;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

// Declare optional parameters.
$d = required_param('d', PARAM_INT); // The ID of the discussion.
$sesskey = optional_param('sesskey', null, PARAM_TEXT);
$ratingid = optional_param('r', 0, PARAM_INT);
$ratedpost = optional_param('rp', 0, PARAM_INT);

// Set the URL that should be used to return to this page.
$PAGE->set_url('/mod/moodleoverflow/discussion.php', ['d' => $d]);

// The page should not be large, only pages containing broad tables are usually.
$PAGE->add_body_class('limitedwidth');

// Check if the discussion is valid.
$discussion = moodleoverflow_get_record_or_exception('moodleoverflow_discussions', ['id' => $d], 'invaliddiscussionid');

// Check if the related moodleoverflow instance is valid.
$moodleoverflow = moodleoverflow_get_record_or_exception('moodleoverflow', ['id' => $discussion->moodleoverflow],
                                                         'invalidmoodleoverflowid');

// Check if the related moodleoverflow instance is valid.
$course = moodleoverflow_get_record_or_exception('course', ['id' => $discussion->course], 'invalidcourseid', '*', true);

// Save the allowmultiplemarks setting.
$marksetting = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id], 'allowmultiplemarks');
$multiplemarks = false;
if ($marksetting->allowmultiplemarks == 1) {
    $multiplemarks = true;
}
// Setting of limitedanswer. Limitedanswertime saves the timestamp, until the limitedanswer is on (0 if off).
$limitedanswersetting = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id], 'la_starttime, la_endtime');

// Get the related coursemodule and its context.
if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
    throw new moodle_exception('invalidcoursemodule');
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

// Has a request to rate a post (as solved or helpful) or to remove rating been submitted?
if ($ratingid) {
    require_sesskey();

    if (in_array($ratingid, [RATING_SOLVED, RATING_REMOVE_SOLVED, RATING_HELPFUL, RATING_REMOVE_HELPFUL])) {
        // Rate the post.
        if (!\mod_moodleoverflow\ratings::moodleoverflow_add_rating($moodleoverflow, $ratedpost, $ratingid, $cm, $USER->id)) {
            throw new moodle_exception('ratingfailed', 'moodleoverflow');
        }

        // Return to the discussion.
        $returnto = new moodle_url('/mod/moodleoverflow/discussion.php?d=' . $discussion->id);
        redirect($returnto);
    }
}

// Trigger the discussion viewed event.
$params = [
    'context' => $modulecontext,
    'objectid' => $discussion->id,
];
$event = \mod_moodleoverflow\event\discussion_viewed::create($params);
$event->trigger();

// Unset where the user is coming from.
// Allows to calculate the correct return url later.
unset($SESSION->fromdiscussion);

// Get the parent post.
$parent = $discussion->firstpost;
if (!$post = moodleoverflow_get_post_full($parent)) {
    throw new moodle_exception("notexists", 'moodleoverflow', "$CFG->wwwroot/mod/moodleoverflow/view.php?m=$moodleoverflow->id");
}

// Has the user the capability to view the post?
if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)) {
    throw new moodle_exception('noviewdiscussionspermission', 'moodleoverflow',
        "$CFG->wwwroot/mod/moodleoverflow/view.php?m=$moodleoverflow->id");
}

// Append the discussion name to the navigation.
$forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($forumnode)) {
    $forumnode = $PAGE->navbar;
} else {
    $forumnode->make_active();
}

if ($discussion->userid === '0') {
    $discussion->name = get_string('privacy:anonym_discussion_name', 'mod_moodleoverflow');
}

$node = $forumnode->add(format_string($discussion->name),
    new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $discussion->id]));
$node->display = false;
if ($node && ($post->id != $discussion->firstpost)) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->requires->js_call_amd('mod_moodleoverflow/reviewing', 'init');

$PAGE->requires->js_call_amd('mod_moodleoverflow/rating', 'init', [$USER->id, $multiplemarks]);

// Initiate the page.
$PAGE->set_title($course->shortname . ': ' . format_string($discussion->name));
$PAGE->set_heading($course->fullname);

// Include the renderer.
$renderer = $PAGE->get_renderer('mod_moodleoverflow');

// Start the side-output.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($discussion->name), 1, 'discussionname');

// Guests and users can not subscribe to a discussion.
if ((!is_guest($modulecontext, $USER) && isloggedin() && $canviewdiscussion)) {
    echo '';
}

echo "<br>";

echo '<div id="moodleoverflow-posts"><div id="moodleoverflow-root">';

moodleoverflow_print_discussion($course, $cm, $moodleoverflow, $discussion, $post, $multiplemarks, $limitedanswersetting);
echo '</div></div>';

echo $OUTPUT->footer();
