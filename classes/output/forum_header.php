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

namespace mod_moodleoverflow\output;

use core\output\renderer_base;
use core\output\templatable;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use moodle_url;

/**
 * The top of the page of a moodleoverflow: the description and the rules for its participants (template
 * forum_header), and the data of its actions: "Add a new discussion topic", "Review needed" and the menu "Forum
 * actions". The list of discussions shows the actions in its action bar, the discussion of a single discussion
 * moodleoverflow, which has no list, as buttons.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_header implements templatable {
    /** @var moodleoverflow The moodleoverflow of the page */
    private moodleoverflow $modflow;

    /** @var int The number of posts of the moodleoverflow the viewer has not read */
    private int $unreadcount;

    /**
     * Constructor.
     * @param moodleoverflow $modflow
     * @param int $unreadcount The number of posts of the moodleoverflow the viewer has not read
     */
    public function __construct(moodleoverflow $modflow, int $unreadcount) {
        $this->modflow = $modflow;
        $this->unreadcount = $unreadcount;
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        global $USER;
        $startdiscussion = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $this->modflow->id]);
        $userstatslink = new moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->modflow->get_cm()->id]);
        $nextpost = service\review::get_next_post_to_review($this->modflow, $USER->id);

        // The menu "Forum actions" in three groups, separated by dividers: reading, notifications, statistics.
        $markallread = $this->unreadcount > 0 ? [
            'itemid' => 'moodleoverflow-markallread-' . $this->modflow->id,
            'instanceid' => $this->modflow->id,
        ] : [];
        $subscription = permissions::can_change_subscription($this->modflow, $USER->id) ? [
            'type' => 'moodleoverflow',
            'id' => $this->modflow->id,
            'subscribed' => service\subscription::is_subscribed($this->modflow, $USER->id),
            'forum' => true,
        ] : [];
        $tracking = permissions::can_change_tracking($this->modflow, $USER->id) ? [
            'itemid' => 'moodleoverflow-tracking-' . $this->modflow->id,
            'instanceid' => $this->modflow->id,
            'tracked' => service\readtracking::is_tracked($this->modflow, $USER->id),
        ] : [];
        $stats = permissions::can_view_userstats($this->modflow, $USER->id) ? ['link' => $userstatslink->out()] : [];
        $menu = $markallread || $subscription || $tracking || $stats ? [
            'markallread' => $markallread,
            'subscription' => $subscription,
            'tracking' => $tracking,
            'stats' => $stats,
            'dividernotifications' => $subscription || $tracking,
            'dividerstats' => $markallread || $subscription || $tracking,
        ] : [];

        return [
            'intro' => $this->modflow->intro !== ''
                ? format_module_intro('moodleoverflow', $this->modflow->get_db_object(), $this->modflow->get_cm()->id)
                : '',
            'hints' => $this->get_hints($USER->id),
            'startdiscussion' => permissions::can_start_discussion($this->modflow, $USER->id)
                ? ['link' => $startdiscussion->out()] : [],
            'review_link' => $nextpost ? $nextpost->get_link()->out(false) : false,
            'menu' => $menu,
            'hasactions' => $nextpost || $menu,
        ];
    }

    /**
     * Returns the rules of the moodleoverflow for its participants: single discussion, anonymity, review and the answer
     * window, each with an icon, a short label and a sentence.
     *
     * @param int $userid The viewer
     * @return array{icon: string, label: string, text: string}[]
     */
    private function get_hints(int $userid): array {
        $hint = fn(string $icon, string $key, string $text) => [
            'icon' => $icon,
            'label' => get_string('desc:' . $key . '_label', 'moodleoverflow'),
            'text' => $text,
        ];
        $hints = [];
        if ($this->modflow->is_single_discussion()) {
            $hints[] = $hint('t/messages', 'singlediscussion', get_string('desc:singlediscussion', 'moodleoverflow'));
        }
        $anonymity = match ($this->modflow->get_anonymity()) {
            anonymity::QUESTIONS => 'only_questions',
            anonymity::EVERYTHING => 'anonymous',
            default => null,
        };
        if ($anonymity) {
            $text = get_string('desc:' . $anonymity, 'moodleoverflow');
            // A viewer who always posts with the name must not think to be anonymous.
            if (!permissions::is_hidden_as_author($this->modflow, $userid, true)) {
                $text .= ' ' . get_string('desc:postwithname', 'moodleoverflow');
            }
            $hints[] = $hint('i/show', $anonymity, $text);
        }
        $review = match ($this->modflow->get_review_level()) {
            review_level::QUESTIONS => 'review_questions',
            review_level::EVERYTHING => 'review_everything',
            default => null,
        };
        if ($review) {
            $hints[] = $hint('i/duration', $review, get_string('desc:' . $review, 'moodleoverflow'));
        }

        // The answer window, as long as it says something: before it opens, while it closes later, after it closed.
        [$start, $end] = $this->modflow->get_answer_window();
        $now = time();
        $date = fn(int $time) => userdate($time, get_string('strftimedatetime', 'langconfig'));
        $answers = match (true) {
            $start && $now < $start && $end => ['fromuntil', ['start' => $date($start), 'end' => $date($end)]],
            $start && $now < $start => ['from', $date($start)],
            $end && $now < $end => ['until', $date($end)],
            (bool) $end => ['closed', $date($end)],
            default => null,
        };
        if ($answers) {
            [$state, $a] = $answers;
            $hints[] = $hint('i/calendar', 'limitedanswer', get_string('desc:limitedanswer_' . $state, 'moodleoverflow', $a));
        }
        return $hints;
    }
}
