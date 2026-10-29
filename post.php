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
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\output\author;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
global $USER, $PAGE, $OUTPUT;

// Which operation on which object: on the first request from the URL, on the submission from the hidden form fields.
$create = optional_param('moodleoverflow', 0, PARAM_INT);
$reply = optional_param('reply', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
if (count(array_filter([$create, $reply, $edit])) !== 1) {
    throw new moodle_exception('wrongparametercount', 'moodleoverflow');
}
$post = $edit ? post::from_id($edit) : null;
$parent = $reply ? post::from_id($reply) : null;
$modflow = $create ? moodleoverflow::from_id($create) : ($post ?? $parent)->get_moodleoverflow();
$course = $modflow->get_course();
$cm = $modflow->get_cm();

$PAGE->set_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $create, 'reply' => $reply, 'edit' => $edit]);
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
if ($create) {
    permissions::ensure(permissions::can_start_discussion($modflow, $USER->id), 'nopostmoodleoverflow');
} else if ($reply) {
    permissions::ensure(permissions::can_reply($parent, $USER->id), 'cannotreply');
} else {
    permissions::ensure(permissions::can_edit_post($post, $USER->id), 'cannoteditposts');
}

$customdata = ['modflow' => $modflow, 'post' => $post, 'parent' => $parent];
$form = new post_form($PAGE->url, $customdata, 'post', '', ['id' => 'mformmoodleoverflow']);
$form->set_initial_data();

if ($form->is_cancelled()) {
    redirect($create ? $modflow->get_link() : ($post ?? $parent)->get_discussion()->get_link());
}

// If there is data from the form it means that the user entered information in the form. Execute the right action related to it.
if ($data = $form->get_data()) {
    $content = new post_content(
        message: $data->message['text'],
        messageformat: $data->message['format'],
        messagedraftid: $data->message['itemid'],
        attachmentdraftid: $data->attachments ?? null,
        subject: $data->subject ?? null
    );

    if ($create) {
        posting::create_discussion($modflow, $USER->id, $content);
        redirect($modflow->get_link(), get_string('postaddedsuccess', 'moodleoverflow'), null, notification::NOTIFY_SUCCESS);
    }

    if ($reply) {
        $newpost = posting::reply($parent, $USER->id, $content);
        $message = get_string('postaddedsuccess', 'moodleoverflow') . ' ' .
            get_string('postaddedtimeleft', 'moodleoverflow', format_time(get_config('moodleoverflow', 'maxeditingtime')));
        redirect($newpost->get_link(), $message, null, notification::NOTIFY_SUCCESS);
    }

    // The post gets edited.
    $post = posting::edit($post, $USER->id, $content);
    $message = get_string('postupdated', 'moodleoverflow');
    if ($post->get_userid() !== (int) $USER->id) {
        // Name the author whose post was edited, as the editor sees them.
        $name = (new author($post, $USER->id))->export_for_template($OUTPUT)['name'];
        $message = get_string('editedpostupdated', 'moodleoverflow', $name);
    }
    redirect($post->get_link(), $message, null, notification::NOTIFY_SUCCESS);
}

// Nothing was submitted yet (or the submission was invalid): show the form, for a reply show the parent post.
if ($parent) {
    $PAGE->requires->js_call_amd('mod_moodleoverflow/show_post', 'init');
}
echo $OUTPUT->header();
if ($parent) {
    echo $OUTPUT->render_from_template('mod_moodleoverflow/post_original', [
        'postid' => $parent->get_id(),
        'postcontent' => $parent->get_message_formatted(),
        'attachments' => $parent->get_attachments($OUTPUT),
        'byname' => $OUTPUT->render_from_template(
            'mod_moodleoverflow/author_link',
            (new author($parent, $USER->id))->export_for_template($OUTPUT)
        ),
    ]);
}
$form->display();
echo $OUTPUT->footer();
