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
use mod_moodleoverflow\local\enum\posting_action;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\permissions;
use moodle_url;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Form to start a discussion, reply to a post or edit a post (post.php).
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_form extends moodleform {
    /**
     * Constructor.
     *
     * @param moodle_url $url The page of the form
     * @param posting_action $action What the form does
     * @param moodleoverflow $modflow The moodleoverflow of the post
     * @param post|null $post The parent for REPLY, the edited post for EDIT, null for CREATE
     * @param string $prefill Plain text to start a new post with (the small reply form of the discussion page)
     */
    public function __construct(
        moodle_url $url,
        /** @var posting_action What the form does */
        private readonly posting_action $action,
        /** @var moodleoverflow The moodleoverflow of the post */
        private readonly moodleoverflow $modflow,
        /** @var post|null The parent for REPLY, the edited post for EDIT, null for CREATE */
        private readonly ?post $post,
        /** @var string Plain text to start a new post with */
        private readonly string $prefill = '',
    ) {
        parent::__construct($url, null, 'post', '', ['id' => 'mformmoodleoverflow']);
    }

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {
        global $USER;
        $mform = $this->_form;

        $heading = $this->action === posting_action::REPLY ? 'yourreply' : 'yournewtopic';
        $mform->addElement('header', 'general', get_string($heading, 'moodleoverflow'));

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
            $this->modflow->get_editor_options($this->get_edited_post()?->get_id())
        );
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        if (permissions::can_add_attachments($this->modflow, $USER->id)) {
            $mform->addElement(
                'filemanager',
                'attachments',
                get_string('attachment', 'moodleoverflow'),
                null,
                $this->modflow->get_attachment_options()
            );
            $mform->addHelpButton('attachments', 'attachment', 'moodleoverflow');
        }

        $submit = $this->action === posting_action::EDIT
            ? get_string('savechanges')
            : get_string('posttomoodleoverflow', 'moodleoverflow');
        $this->add_action_buttons(true, $submit);

        // The action goes with the submission: post.php reads it again from this field.
        $name = $this->action->value;
        $mform->addElement('hidden', $name);
        $mform->setType($name, PARAM_INT);
        $mform->setConstant($name, $this->post?->get_id() ?? $this->modflow->id);
    }

    /**
     * Fills the form when it is shown: the message with its embedded files and the attachments in draft areas, the
     * format of the message and, for the question, the subject.
     *
     * @return void
     */
    public function set_initial_data(): void {
        global $USER;
        $post = $this->get_edited_post();
        $postid = $post?->get_id();
        $context = $this->modflow->get_context();

        // Copy the attachments into a draft area. A new draft is only created on the first request, so a form that is
        // shown again after a validation error keeps the user's uploads.
        $attachmentdraftid = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area(
            $attachmentdraftid,
            $context->id,
            'mod_moodleoverflow',
            'attachment',
            $postid,
            $this->modflow->get_attachment_options()
        );

        // The files of an anonymous author must not name the author.
        if ($post && permissions::is_author_hidden($post)) {
            $usercontext = \context_user::instance($USER->id);
            $anonymous = get_string('anonymous', 'moodleoverflow');
            foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $attachmentdraftid) as $file) {
                $file->set_author($anonymous);
            }
        }

        // An edited post keeps its own format, a new post uses the preferred editor format or starts with the text of
        // the small reply form.
        if ($post) {
            $format = $post->messageformat;
            $message = $post->message . $this->get_edited_by_note($post, $format);
        } else if (trim($this->prefill) !== '') {
            $format = FORMAT_HTML;
            $message = post_content::plain_text_to_html($this->prefill);
        } else {
            $format = editors_get_preferred_format();
            $message = '';
        }

        // Copy the embedded files of the message into a draft area, so the editor shows and keeps them.
        $messagedraftid = file_get_submitted_draft_itemid('message');
        $message = file_prepare_draft_area(
            $messagedraftid,
            $context->id,
            'mod_moodleoverflow',
            'post',
            $postid,
            $this->modflow->get_editor_options($postid),
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
     * The content of the submitted form, null if the form was not submitted, was cancelled or is invalid.
     *
     * @return post_content|null
     */
    public function get_post_content(): ?post_content {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        return new post_content(
            message: $data->message['text'],
            messageformat: $data->message['format'],
            messagedraftid: $data->message['itemid'],
            attachmentdraftid: $data->attachments ?? null,
            subject: $data->subject ?? null
        );
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
     * The post that is edited, null for a new discussion or a reply.
     *
     * @return post|null
     */
    private function get_edited_post(): ?post {
        return $this->action === posting_action::EDIT ? $this->post : null;
    }

    /**
     * Whether the form has a subject: for a new discussion and when the question is edited.
     *
     * @return bool
     */
    private function has_subject(): bool {
        return match ($this->action) {
            posting_action::CREATE => true,
            posting_action::REPLY => false,
            posting_action::EDIT => $this->post->is_question(),
        };
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
            $profileurl = new moodle_url('/user/view.php', ['id' => $USER->id, 'course' => $this->modflow->course]);
            $data->name = html_writer::link($profileurl, fullname($USER));
            $note = html_writer::tag('span', get_string('editedby', 'moodleoverflow', $data), ['class' => 'edited']);
            return html_writer::tag('p', $note);
        }
        $data->name = fullname($USER);
        return "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
    }
}
