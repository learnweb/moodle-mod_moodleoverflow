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
 * The mail to a reviewer about a post that waits for its review.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_request implements message {
    /**
     * Constructor.
     * @param post $post The post that waits for its review
     * @param object $recipient The user record of the reviewer
     */
    public function __construct(
        /** @var post The post that waits for its review */
        private post $post,
        /** @var object The user record of the reviewer */
        private object $recipient
    ) {
    }

    #[\Override]
    public function get_subject(): string {
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $coursename = format_string($this->post->get_moodleoverflow()->get_course()->fullname, true, $options);
        return get_string('mailreviewrequestsubject', 'moodleoverflow', $coursename);
    }

    #[\Override]
    public function get_url(): moodle_url {
        // The discussion page shows the review buttons at the post.
        return $this->post->get_link();
    }

    #[\Override]
    public function get_courseid(): int {
        return $this->post->get_moodleoverflow()->course;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_moodleoverflow/mail/review_request';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // The text template prints the names as they are, so they are not escaped; the HTML template escapes them.
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $modflow = $this->post->get_moodleoverflow();
        $modflowname = format_string($modflow->name, true, $options);
        return [
            'intro' => get_string('mailreviewrequestintro', 'moodleoverflow', [
                'moodleoverflow' => $modflowname,
                'discussion' => format_string($this->post->get_discussion()->name, true, $options),
            ]),
            'heading' => get_string(
                'mailreviewrequestheading',
                'moodleoverflow',
                format_string($modflow->get_course()->fullname, true, $options)
            ),
            'subheading' => get_string('mailforum', 'moodleoverflow', $modflowname),
            'post' => (new post_content($this->post, $this->recipient, true))->export_for_template($output),
            // The discussion page shows the review buttons at the post.
            'actions' => [
                ['url' => $this->post->get_link()->out(false), 'label' => get_string('mailreviewpost', 'moodleoverflow'),
                    'primary' => true],
            ],
        ];
    }
}
