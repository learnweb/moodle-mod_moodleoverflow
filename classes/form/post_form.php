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

namespace mod_moodleoverflow\form;

use core\output\html_writer;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use moodle_url;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Form to start a discussion, reply to a post or edit a post.
 *
 * Custom data: 'modflow' (moodleoverflow), and either 'parent' (the post to reply to), 'post' (the post to edit) or
 * neither (a new discussion).
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_form extends moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        global $USER;
        $mform = $this->_form;
        $modflow = $this->get_moodleoverflow();
        $post = $this->get_edited_post();
        $parent = $this->get_parent();

        $heading = $parent ? get_string('yourreply', 'moodleoverflow') : get_string('yournewtopic', 'moodleoverflow');
        $mform->addElement('header', 'general', $heading);

        // Only the question has a subject: it is the name of the discussion.
        if ($this->has_subject()) {
            $mform->addElement('text', 'subject', get_string('subject', 'moodleoverflow'), 'size="48"');
            $mform->setType('subject', PARAM_TEXT);
            $mform->addRule('subject', get_string('required'), 'required', null, 'client');
            $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        }

        $mform->addElement(
            'editor',
            'message',
            get_string('message', 'moodleoverflow'),
            null,
            $modflow->get_editor_options($post?->get_id())
        );
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        if (permissions::can_add_attachments($modflow, $USER->id)) {
            $mform->addElement(
                'filemanager',
                'attachments',
                get_string('attachment', 'moodleoverflow'),
                null,
                $modflow->get_attachment_options()
            );
            $mform->addHelpButton('attachments', 'attachment', 'moodleoverflow');
        }

        $strsubmit = $post ? get_string('savechanges') : get_string('posttomoodleoverflow', 'moodleoverflow');
        $this->add_action_buttons(true, $strsubmit);

        // Which operation on which object. post.php reads them again on the submission; the constants make sure the
        // submitted values are the ones the form was built for.
        $operation = [
            'moodleoverflow' => ($post || $parent) ? 0 : $modflow->id,
            'reply' => $parent?->get_id() ?? 0,
            'edit' => $post?->get_id() ?? 0,
        ];
        foreach ($operation as $name => $value) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, PARAM_INT);
            $mform->setConstant($name, $value);
        }
    }

    /**
     * Fills the form when it is shown: the message with its embedded files and the attachments in draft areas, the
     * format of the message and, for the question, the subject.
     *
     * @return void
     */
    public function set_initial_data(): void {
        global $USER;
        $modflow = $this->get_moodleoverflow();
        $post = $this->get_edited_post();
        $postid = $post?->get_id();
        $context = $modflow->get_context();

        // Copy the attachments into a draft area. A new draft is only created on the first request, so a form that is
        // shown again after a validation error keeps the user's uploads.
        $attachmentdraftid = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area(
            $attachmentdraftid,
            $context->id,
            'mod_moodleoverflow',
            'attachment',
            $postid,
            $modflow->get_attachment_options()
        );

        // The files of an anonymous author must not name the author.
        if ($post && permissions::is_author_hidden($post)) {
            $usercontext = \context_user::instance($USER->id);
            $anonymous = get_string('anonymous', 'moodleoverflow');
            foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $attachmentdraftid) as $file) {
                $file->set_author($anonymous);
            }
        }

        // A new post uses the preferred editor format; an edited post keeps its own format.
        $format = $post ? $post->messageformat : editors_get_preferred_format();
        $message = $post ? $post->message . $this->get_edited_by_note($post, $format) : '';

        // Copy the embedded files of the message into a draft area, so the editor shows and keeps them.
        $messagedraftid = file_get_submitted_draft_itemid('message');
        $message = file_prepare_draft_area(
            $messagedraftid,
            $context->id,
            'mod_moodleoverflow',
            'post',
            $postid,
            $modflow->get_editor_options($postid),
            $message
        );

        $data = [
            'attachments' => $attachmentdraftid,
            'message' => ['text' => $message, 'format' => $format, 'itemid' => $messagedraftid],
        ];
        if ($this->has_subject()) {
            $data['subject'] = $post ? $post->get_discussion()->name : '';
        }
        $this->set_data($data);
    }

    /**
     * Form validation.
     *
     * @param array $data  data from the form.
     * @param array $files files uploaded.
     *
     * @return array of errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'moodleoverflow');
        }
        if ($this->has_subject() && trim($data['subject'] ?? '') === '') {
            $errors['subject'] = get_string('erroremptysubject', 'moodleoverflow');
        }
        return $errors;
    }

    /**
     * Whether the form has a subject: for a new discussion and when the question is edited.
     *
     * @return bool
     */
    private function has_subject(): bool {
        $post = $this->get_edited_post();
        return $post ? $post->is_question() : !$this->get_parent();
    }

    /**
     * The note appended to the message when someone else than the author edits a post.
     *
     * @param post $post The edited post.
     * @param int $format The format of the message.
     * @return string Empty if the author edits their own post.
     */
    private function get_edited_by_note(post $post, int $format): string {
        global $USER;
        if ($post->get_userid() === (int) $USER->id) {
            return '';
        }
        $data = (object) ['date' => userdate(time())];
        if ($format == FORMAT_HTML) {
            $profileurl = new moodle_url('/user/view.php', ['id' => $USER->id, 'course' => $this->get_moodleoverflow()->course]);
            $data->name = html_writer::link($profileurl, fullname($USER));
            $note = html_writer::tag('span', get_string('editedby', 'moodleoverflow', $data), ['class' => 'edited']);
            return html_writer::tag('p', $note);
        }
        $data->name = fullname($USER);
        return "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
    }

    /**
     * The moodleoverflow of the post.
     *
     * @return moodleoverflow
     */
    private function get_moodleoverflow(): moodleoverflow {
        return $this->_customdata['modflow'];
    }

    /**
     * The post that is edited, null for a new discussion or a reply.
     *
     * @return post|null
     */
    private function get_edited_post(): ?post {
        return $this->_customdata['post'] ?? null;
    }

    /**
     * The post that is replied to, null for a new discussion or an edit.
     *
     * @return post|null
     */
    private function get_parent(): ?post {
        return $this->_customdata['parent'] ?? null;
    }
}
