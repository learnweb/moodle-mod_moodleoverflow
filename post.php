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
 * The page with the post form: start a discussion, reply to a post or edit a post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use mod_moodleoverflow\form\post_form;
use mod_moodleoverflow\local\enum\posting_action;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\output\author;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
global $USER, $PAGE, $OUTPUT;

// Which action on which object: on the first request from the URL, on the submission from the hidden form field.
[$action, $id] = posting_action::from_params(fn(string $name) => optional_param($name, 0, PARAM_INT));
[$modflow, $post] = $action->load_objects($id);
$course = $modflow->get_course();
$cm = $modflow->get_cm();

$PAGE->set_url('/mod/moodleoverflow/post.php', [$action->value => $id]);
$PAGE->set_cm($cm, $course);
$PAGE->set_title($course->shortname . ': ' . format_string($modflow->name));
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('limitedwidth');

// Guests are asked to log in.
if (!isloggedin() || isguestuser()) {
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('noguestpost', 'forum') . '<br /><br />' . get_string('liketologin'),
        get_login_url(),
        $modflow->get_link()
    );
    echo $OUTPUT->footer();
    exit;
}

// Users who are not enrolled are sent to the enrolment page.
require_login($course, false, $cm);

// May the user open the form? The service checks again when the form is submitted.
permissions::ensure(permissions::can_do_posting_action($action, $modflow, $post, $USER->id), $action->get_error_code());

// A reply started in the small form of the discussion page ("Advanced") brings its text along.
$prefill = optional_param('prefill', '', PARAM_TEXT);
$form = new post_form($PAGE->url, $action, $modflow, $post, $prefill);
$form->set_initial_data();

if ($form->is_cancelled()) {
    redirect($action === posting_action::CREATE ? $modflow->get_link() : $post->get_discussion()->get_link());
}

if ($content = $form->get_post_content()) {
    $saved = match ($action) {
        posting_action::CREATE => posting::create_discussion($modflow, $USER->id, $content)->get_first_post(),
        posting_action::REPLY => posting::reply($post, $USER->id, $content),
        posting_action::EDIT => posting::edit($post, $USER->id, $content),
    };
    $edittime = format_time(get_config('moodleoverflow', 'maxeditingtime'));
    // The author whose post was edited by someone else, as the editor sees them.
    $authorname = fn() => (new author($saved, $USER->id))->export_for_template($OUTPUT)['name'];
    $message = match ($action) {
        posting_action::CREATE => get_string('postaddedsuccess', 'moodleoverflow'),
        posting_action::REPLY => get_string('postaddedsuccess', 'moodleoverflow') . ' ' .
            get_string('postaddedtimeleft', 'moodleoverflow', $edittime),
        posting_action::EDIT => $saved->get_userid() === (int) $USER->id
            ? get_string('postupdated', 'moodleoverflow')
            : get_string('editedpostupdated', 'moodleoverflow', $authorname()),
    };
    // A new discussion opens the discussion list, a reply or an edit the post.
    $url = $action === posting_action::CREATE ? $modflow->get_link() : $saved->get_link();
    redirect($url, $message, null, notification::NOTIFY_SUCCESS);
}

// Nothing was submitted yet (or the submission was invalid): show the form, for a reply show the parent post.
if ($action === posting_action::REPLY) {
    $PAGE->requires->js_call_amd('mod_moodleoverflow/show_post', 'init');
}
echo $OUTPUT->header();
if ($action === posting_action::REPLY) {
    echo $OUTPUT->render_from_template('mod_moodleoverflow/post_original', [
        'postid' => $post->get_id(),
        'postcontent' => $post->get_message_formatted(),
        'attachments' => $post->get_attachments($OUTPUT),
        'byname' => $OUTPUT->render_from_template(
            'mod_moodleoverflow/author_link',
            (new author($post, $USER->id))->export_for_template($OUTPUT)
        ),
    ]);
}
$form->display();
echo $OUTPUT->footer();
