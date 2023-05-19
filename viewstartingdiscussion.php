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
 * Prints a particular instance of moodleoverflow
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config and locallib.
require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');
use mod_moodleoverflow\readtracking;

global $PAGE, $OUTPUT, $USER, $DB;
// If invoked from a course show the discussions from the course.
$courseid = optional_param('courseid', 0, PARAM_INT);
$params = array();
if ($courseid) {
    $params['courseid'] = $courseid;
}

$PAGE->set_url('/mod/moodleoverflow/viewstartingdiscussion.php', $params);
require_login();

// Systemwide context as all started discussion in moodleoverflows are displayed.
$PAGE->set_context(context_system::instance());

$PAGE->set_title(get_string('overviewdiscussions', 'mod_moodleoverflow'));
$PAGE->set_heading(get_string('overviewdiscussions', 'mod_moodleoverflow'));

// Get all started discussions (in a course).
$discussions = $DB->get_records('moodleoverflow_discussions', array('userid' => $USER->id));
// Collect the needed data being submitted to the template.
$discussionswithdetails = array();
$tracking = new readtracking();
echo $OUTPUT->header();

foreach ($discussions as $discussion) {
    $moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow));
    $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $discussion->course,
        false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $canreviewposts = has_capability('mod/moodleoverflow:reviewpost', $context);

    if (has_capability('mod/moodleoverflow:viewdiscussion', $context)) {
        if ($cantrack = \mod_moodleoverflow\readtracking::moodleoverflow_can_track_moodleoverflows($moodleoverflow)) {
            $istracked = \mod_moodleoverflow\readtracking::moodleoverflow_is_tracked($moodleoverflow);
        } else {
            $istracked = false;
        }
        $discussion->discussion = $discussion->id;

        $firstpost = $DB->get_record('moodleoverflow_posts', array('id' => $discussion->firstpost));
        $discussion->reviewed = $firstpost->reviewed;
        $newdiscussion = prepare_data_for_discussions($discussion, $istracked, $canreviewposts, $moodleoverflow, $context, $cm, true);
        $newdiscussion->istracked = $istracked;
        $newdiscussion->cantrack = $cantrack;
        if ($unreadpost = get_discussion_unread($discussion->id, $cm)) {
            $newdiscussion->unread = true;
            $newdiscussion->unreadamount = $unreadpost->unread;
        }
        $newdiscussion->cansubtodiscussion = false;
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/moodleoverflow:viewdiscussion', $context)
            && \mod_moodleoverflow\subscriptions::is_subscribable($moodleoverflow, $context)) {
            $newdiscussion->cansubtodiscussion = true;
        }
        array_push($discussionswithdetails, $newdiscussion);
    }
}
$mustachedata = new stdClass();
$mustachedata->discussions = $discussionswithdetails;
$mustachedata->hasdiscussions = count($discussionswithdetails) >= 0;

// Include the renderer.
$renderer = $PAGE->get_renderer('mod_moodleoverflow');
echo $renderer->render_discussion_started_list($mustachedata);
echo $OUTPUT->footer();
