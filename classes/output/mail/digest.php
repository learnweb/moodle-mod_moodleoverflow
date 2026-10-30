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
 * The daily mail to a user with the new posts of all their courses.
 *
 * The posts are grouped by course, moodleoverflow and discussion. Every discussion gets one line with the number of
 * its new posts. The digest type of the user's profile decides about the rest: "complete" (1) shows every post below
 * that line, "subjects" (2) only the line.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class digest implements message {
    /**
     * Constructor.
     * @param post[] $posts The new posts the recipient may see, sorted by their date
     * @param object $recipient The user record of the recipient
     */
    public function __construct(
        /** @var post[] The new posts the recipient may see, sorted by their date */
        private array $posts,
        /** @var object The user record of the recipient */
        private object $recipient
    ) {
    }

    #[\Override]
    public function get_subject(): string {
        return get_string('maildigestsubject', 'moodleoverflow');
    }

    #[\Override]
    public function get_url(): moodle_url {
        return new moodle_url('/my/');
    }

    #[\Override]
    public function get_courseid(): int {
        // The digest covers all courses.
        return SITEID;
    }

    #[\Override]
    public function get_template(): string {
        return 'mod_moodleoverflow/mail/digest';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $courses = [];
        foreach (self::group($this->posts, fn(post $post) => $post->get_moodleoverflow()->course) as $courseposts) {
            $moodleoverflows = [];
            foreach (self::group($courseposts, fn(post $post) => $post->get_moodleoverflow()->id) as $modflowposts) {
                $discussions = [];
                foreach (self::group($modflowposts, fn(post $post) => $post->get_discussionid()) as $discussionposts) {
                    $discussions[] = $this->export_discussion($discussionposts, $output);
                }
                $modflow = $modflowposts[0]->get_moodleoverflow();
                $modflowname = format_string($modflow->name, true, self::format_options($modflowposts[0]));
                $moodleoverflows[] = [
                    'name' => get_string('maildigestforum', 'moodleoverflow', [
                        'name' => $modflowname,
                        'url' => $modflow->get_link()->out(false),
                    ]),
                    'forum' => get_string('mailforum', 'moodleoverflow', $modflowname),
                    'discussions' => $discussions,
                ];
            }
            $course = $courseposts[0]->get_moodleoverflow()->get_course();
            $courses[] = [
                'name' => get_string(
                    'maildigestcourse',
                    'moodleoverflow',
                    format_string($course->fullname, true, self::format_options($courseposts[0]))
                ),
                'moodleoverflows' => $moodleoverflows,
                // The HTML template draws a line between the courses.
                'separator' => $courses !== [],
            ];
        }

        // Without a user id the page opens the preferences of whoever is logged in.
        $preferences = (new moodle_url('/user/forum.php'))->out(false);
        return [
            'intro' => get_string('maildigestintro', 'moodleoverflow'),
            'heading' => get_string('maildigestsubject', 'moodleoverflow'),
            'subheading' => get_string('maildigestsubheading', 'moodleoverflow'),
            'complete' => $this->is_complete(),
            'courses' => $courses,
            'preferences' => get_string('mailindexlink', 'moodleoverflow', $preferences),
            'footerlinks' => [['url' => $preferences, 'label' => get_string('forumpreferences')]],
        ];
    }

    // Private helper functions.

    /**
     * Exports the new posts of one discussion: the line with their number and link, and in a complete digest the posts.
     *
     * @param post[] $posts The new posts of the discussion, the first one is linked
     * @param renderer_base $output
     * @return array
     */
    private function export_discussion(array $posts, renderer_base $output): array {
        $count = count($posts);
        $discussionname = format_string($posts[0]->get_discussion()->name, true, self::format_options($posts[0]));
        $url = $posts[0]->get_link()->out(false);
        $summary = get_string($count === 1 ? 'maildigestnewpost' : 'maildigestnewposts', 'moodleoverflow', [
            'count' => $count,
            'discussion' => $discussionname,
            'url' => $url,
        ]);

        return [
            'summary' => $summary,
            'discussion' => $discussionname,
            'url' => $url,
            'count' => $count === 1
                ? get_string('maildigestcountone', 'moodleoverflow')
                : get_string('maildigestcount', 'moodleoverflow', $count),
            'posts' => $this->is_complete()
                ? array_map(fn(post $post) => (new post_content($post, $this->recipient))->export_for_template($output), $posts)
                : [],
        ];
    }

    /**
     * Returns whether the recipient chose the complete digest (1) instead of the subjects only (2) in their profile.
     *
     * @return bool
     */
    private function is_complete(): bool {
        return (int) $this->recipient->maildigest === 1;
    }

    /**
     * Groups posts by a key and keeps their order, also within each group.
     *
     * @param post[] $posts
     * @param callable $key Returns the key of a post, e.g. its discussion id
     * @return post[][] The groups in the order of their first post
     */
    private static function group(array $posts, callable $key): array {
        $groups = [];
        foreach ($posts as $post) {
            $groups[$key($post)][] = $post;
        }
        return array_values($groups);
    }

    /**
     * Returns the options to format a name in the context of a post. The text template prints the names as they are,
     * so they are not escaped.
     *
     * @param post $post
     * @return array
     */
    private static function format_options(post $post): array {
        return ['context' => $post->get_context(), 'escape' => false];
    }
}
