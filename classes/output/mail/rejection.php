<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

namespace mod_moodleoverflow\output\mail;

use core\output\renderer_base;
use mod_moodleoverflow\local\models\post;
use moodle_url;

/**
 * The mail to the author of a rejected post, with the reason of the reviewer and the post itself.
 *
 * It is built before the post is deleted, but the post is deleted when the author reads it: the mail shows the post
 * without a link, and links to the moodleoverflow instead.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rejection implements message {
    /**
     * Constructor.
     * @param post $post The rejected post
     * @param object $recipient The user record of the author
     * @param string|null $reason The reason the reviewer gave, if any
     */
    public function __construct(
        /** @var post The rejected post */
        private post $post,
        /** @var object The user record of the author */
        private object $recipient,
        /** @var string|null The reason the reviewer gave, if any */
        private ?string $reason
    ) {
    }

    #[\Override]
    public function get_subject(): string {
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $coursename = format_string($this->post->get_moodleoverflow()->get_course()->fullname, true, $options);
        return get_string('mailrejectionsubject', 'moodleoverflow', $coursename);
    }

    #[\Override]
    public function get_url(): moodle_url {
        return $this->post->get_moodleoverflow()->get_link();
    }

    #[\Override]
    public function get_courseid(): int {
        return $this->post->get_moodleoverflow()->course;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_moodleoverflow/mail/rejection';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // The text template prints the names as they are, so they are not escaped; the HTML template escapes them.
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $modflow = $this->post->get_moodleoverflow();
        $modflowname = format_string($modflow->name, true, $options);
        $discussionname = format_string($this->post->get_discussion()->name, true, $options);
        $post = (new post_content($this->post, $this->recipient))->export_for_template($output);
        $post['url'] = null;
        // The recipient is the author, the HTML mail shows only the date.
        $post['author'] = null;

        return [
            'intro' => get_string('mailrejectionintro', 'moodleoverflow', [
                'moodleoverflow' => $modflowname,
                'discussion' => $discussionname,
            ]),
            'heading' => get_string(
                'mailrejectionheading',
                'moodleoverflow',
                format_string($modflow->get_course()->fullname, true, $options)
            ),
            'subheading' => get_string('mailforum', 'moodleoverflow', $modflowname),
            'discussion' => $discussionname,
            'reason' => trim($this->reason ?? '') !== ''
                ? get_string('mailrejectionreason', 'moodleoverflow', format_text_email($this->reason, FORMAT_PLAIN))
                : null,
            'post' => $post,
            'actions' => [
                ['url' => $modflow->get_link()->out(false), 'label' => get_string('mailviewforum', 'moodleoverflow'),
                    'primary' => true],
            ],
        ];
    }
}
