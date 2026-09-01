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
 * File to display all posts from a user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use core\user;

require_once('../../config.php');
global $CFG, $PAGE, $OUTPUT, $USER;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

// Declare the parameters. The course is optional and only tells where the user came from.
$userid = required_param('user', PARAM_INT);
$courseid = optional_param('course', 0, PARAM_INT);

$pageurl = new moodle_url('/mod/moodleoverflow/user.php', ['user' => $userid]);
if ($courseid) {
    $pageurl->param('course', $courseid);
}
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('limitedwidth');

require_login();

// Resolve the outcome before touching anything that assumes a valid user.
$user = user::get_user($userid);
$error = null;
if (!$user || $user->deleted) {
    $error = 'invaliduser';
} else if (isguestuser()) {
    $error = 'usernotavailable';
} else {
    $usercontext = context_user::instance($userid);
    if ($CFG->branch >= 503) {
        $canviewprofile = user::can_view_profile($user, null, $usercontext);
    } else {
        require_once($CFG->dirroot . '/user/lib.php');
        $canviewprofile = user_can_view_profile($user, null, $usercontext);
    }

    // Whoever may not see the profile of a user may not see their posts either.
    $error = $canviewprofile ? null : 'usernotavailable';
}

if ($error) {
    // Fall back to the system context and a generic heading, so that no user name is leaked.
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('user'));
    $PAGE->set_heading(get_string('user'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string($error, 'error'));
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_context(context_user::instance($userid));
$PAGE->set_title(get_string('moodleoverflowposts', 'mod_moodleoverflow'));
$PAGE->set_heading(fullname($user));

// Initiate the page.
moodleoverflow_cache_strings();

echo $OUTPUT->header();

if ($CFG->branch >= 503) {
    $params = (object) ['userid' => $userid, 'courseid' => $courseid];
    echo $OUTPUT->render_react_component('mod_moodleoverflow/UserPage', $params);
} else {
    echo $OUTPUT->notification(get_string('featurenotavailable', 'mod_moodleoverflow'), notification::NOTIFY_WARNING);
}
echo $OUTPUT->footer();
