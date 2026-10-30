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
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\subscription;
use moodle_url;

/**
 * The mail to a subscriber about a new post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification implements message {
    /**
     * Constructor.
     * @param post $post The new post
     * @param object $recipient The user record of the recipient
     */
    public function __construct(
        /** @var post The new post */
        private post $post,
        /** @var object The user record of the recipient */
        private object $recipient
    ) {
    }

    #[\Override]
    public function get_subject(): string {
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $coursename = format_string($this->post->get_moodleoverflow()->get_course()->fullname, true, $options);
        return get_string('mailnotificationsubject', 'moodleoverflow', $coursename);
    }

    #[\Override]
    public function get_url(): moodle_url {
        return $this->post->get_link();
    }

    #[\Override]
    public function get_courseid(): int {
        return $this->post->get_moodleoverflow()->course;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_moodleoverflow/mail/notification';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $modflow = $this->post->get_moodleoverflow();
        $discussion = $this->post->get_discussion();
        // The text template prints the names as they are, so they are not escaped; the HTML template escapes them.
        $options = ['context' => $this->post->get_context(), 'escape' => false];
        $modflowname = format_string($modflow->name, true, $options);
        $discussionname = format_string($discussion->name, true, $options);
        $heading = format_string($modflow->get_course()->fullname, true, $options);

        // A reply to a comment goes to its answer, like on the discussion page.
        $replytarget = $this->post->is_comment() ? $this->post->get_parentpost() : $this->post;
        $replyurl = permissions::can_reply($replytarget, $this->recipient->id)
            ? (new moodle_url('/mod/moodleoverflow/post.php', ['reply' => $replytarget->get_id()]))->out(false)
            : null;

        // The unsubscribe links open a confirmation on the page, see view.php and discussion.php.
        $unsubscribeforumurl = permissions::can_change_subscription($modflow, $this->recipient->id)
            && subscription::is_subscribed($modflow, $this->recipient->id)
            ? (new moodle_url($modflow->get_link(), ['unsubscribe' => 1]))->out(false)
            : null;
        $unsubscribediscussionurl = permissions::can_change_discussion_subscription($discussion, $this->recipient->id)
            && subscription::is_subscribed_to_discussion($discussion, $this->recipient->id)
            ? (new moodle_url($discussion->get_link(), ['unsubscribe' => 1]))->out(false)
            : null;

        $actions = [['url' => $this->post->get_link()->out(false), 'label' => get_string('mailviewpost', 'moodleoverflow'),
            'primary' => true]];
        if ($replyurl) {
            // The same labels as the reply command on the discussion page.
            $label = get_string($replytarget->is_question() ? 'replyfirst' : 'reply', 'moodleoverflow');
            $actions[] = ['url' => $replyurl, 'label' => $label, 'primary' => false];
        }

        $footerlinks = [];
        if ($unsubscribediscussionurl) {
            $footerlinks[] = ['url' => $unsubscribediscussionurl, 'label' => get_string('unsubscribediscussion', 'moodleoverflow')];
        }
        if ($unsubscribeforumurl) {
            $footerlinks[] = ['url' => $unsubscribeforumurl, 'label' => get_string('unsubscribe', 'moodleoverflow')];
        }
        // Without a user id the page opens the preferences of whoever is logged in.
        $footerlinks[] = ['url' => (new moodle_url('/user/forum.php'))->out(false), 'label' => get_string('forumpreferences')];

        return [
            'intro' => get_string('mailnotificationintro', 'moodleoverflow', [
                'moodleoverflow' => $modflowname,
                'discussion' => $discussionname,
            ]),
            'heading' => get_string('mailnotificationheading', 'moodleoverflow', $heading),
            'subheading' => get_string('mailforum', 'moodleoverflow', $modflowname),
            'post' => (new post_content($this->post, $this->recipient, true))->export_for_template($output),
            'actions' => $actions,
            'reply' => $replyurl
                ? get_string('postmailinfolink', 'moodleoverflow', [
                    'coursename' => format_string($modflow->get_course()->shortname, true, $options),
                    'replylink' => $replyurl,
                ])
                : null,
            'unsubscribeforum' => $unsubscribeforumurl ? get_string('unsubscribelink', 'moodleoverflow', $unsubscribeforumurl)
                : null,
            'unsubscribediscussion' => $unsubscribediscussionurl
                ? get_string('unsubscribediscussionlink', 'moodleoverflow', $unsubscribediscussionurl)
                : null,
            'footerlinks' => $footerlinks,
        ];
    }
}
