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
    public function render_text(): string {
        global $PAGE;
        // Not $OUTPUT: in the cron it is only a placeholder until its first use.
        $output = $PAGE->get_renderer('core');
        return $output->render_from_template('mod_moodleoverflow/mail/review_request_text', $this->export_for_template($output));
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // The text template prints the names as they are, so they are not escaped.
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        return [
            'intro' => get_string('mailreviewrequestintro', 'moodleoverflow', [
                'moodleoverflow' => format_string($this->post->get_moodleoverflow()->name, true, $options),
                'discussion' => format_string($this->post->get_discussion()->name, true, $options),
            ]),
            'post' => (new post_content($this->post, $this->recipient))->export_for_template($output),
        ];
    }
}
