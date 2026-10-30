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
use core\output\templatable;
use core_date;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\output\author;

/**
 * One post as a mail shows it: the link to the post, the author with the date, and the text.
 *
 * This is a part of a mail, not a mail itself: the notification, the review request and the rejection show one post
 * this way, the complete digest every new post. The mails hold it and include its templates mail/post_content_text and
 * mail/post_content_html, so the author is hidden in anonymous moodleoverflows the same way in every mail. Everything
 * around the post (intro, reply and unsubscribe links) stays in the mail that shows it.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_content implements templatable {
    /**
     * Constructor.
     * @param post $post The post to show
     * @param object $recipient The user record of the recipient of the mail
     * @param bool $withheadline Whether the HTML shows a headline with the author, the kind of post and the discussion
     *        (e.g. "Jacob Brown answered in "…"") instead of the author next to the date
     */
    public function __construct(
        /** @var post The post to show */
        private readonly post $post,
        /** @var object The user record of the recipient of the mail */
        private readonly object $recipient,
        /** @var bool Whether the HTML shows a headline instead of the author next to the date */
        private readonly bool $withheadline = false
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        // The author as the recipient may see them, the date in the recipient's timezone.
        $author = (new author($this->post, $this->recipient->id))->export_for_template($output);
        $date = userdate($this->post->created, '', core_date::get_user_timezone($this->recipient));
        $context = $this->post->get_context();
        $message = file_rewrite_pluginfile_urls(
            $this->post->message,
            'pluginfile.php',
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->post->get_id()
        );
        // Mail clients are not logged in, so embedded images need a token of the recipient to load.
        $messagehtml = file_rewrite_pluginfile_urls(
            $this->post->message,
            'pluginfile.php',
            $context->id,
            'mod_moodleoverflow',
            'post',
            $this->post->get_id(),
            ['includetoken' => $this->recipient->id]
        );

        $headline = null;
        if ($this->withheadline) {
            $kind = match (true) {
                $this->post->is_question() => 'mailpostquestion',
                $this->post->is_comment() => 'mailpostcomment',
                default => 'mailpostanswer',
            };
            // The text template prints the names as they are, so they are not escaped; the HTML template escapes them.
            $discussion = format_string($this->post->get_discussion()->name, true, ['context' => $context, 'escape' => false]);
            $headline = get_string($kind, 'moodleoverflow', ['name' => $author['name'], 'discussion' => $discussion]);
        }

        return [
            'url' => $this->post->get_link()->out(false),
            'by' => get_string('bynameondatenorating', 'moodleoverflow', ['name' => $author['name'], 'date' => $date]),
            'headline' => $headline,
            // The headline names the author already.
            'author' => $headline ? null : $author['name'],
            'date' => $date,
            'message' => trim(format_text_email($message, $this->post->messageformat)),
            'messagehtml' => format_text($messagehtml, $this->post->messageformat, ['context' => $context]),
        ];
    }
}
