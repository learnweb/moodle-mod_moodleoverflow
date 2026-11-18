<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\output\pages\view;

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use moodle_url;

/**
 * This class represents the output of the view page (view.php). It shows a collection of discussion cards.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements named_templatable, renderable {
    /** @var models\moodleoverflow The moodleoverflow that gets shown */
    public models\moodleoverflow $modflow;

    /** @var int Which page gets shown */
    public int $page;

    /** @var object The coursemodule */
    public object $cm;

    /** @var string The search query, empty: all discussions */
    public string $query;

    /**
     * Constructor.
     * @param models\moodleoverflow $moodleoverflow The moodleoverflow DB object
     * @param int $page Which "page" should get
     * @param string $query Only the discussions that contain these words, empty: all discussions
     */
    public function __construct(models\moodleoverflow $moodleoverflow, int $page = -1, string $query = '') {
        $this->modflow = $moodleoverflow;
        $this->page = $page;
        $this->cm = $moodleoverflow->get_cm();
        $this->query = $query;
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/view/view_page';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $OUTPUT, $USER;
        $perpage = get_config('moodleoverflow', 'manydiscussions');

        // If the perpage value is invalid, deactivate paging.
        if ($perpage <= 0) {
            $perpage = 0;
            $this->page = -1;
        }
        $usepaging = ($perpage > 0 && $this->page !== -1);
        $limitfrom = $usepaging ? $this->page * $perpage : 0;
        $limitamount = $usepaging ? $perpage : null;

        // Create links.
        $startdiscussion = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $this->modflow->id]);
        $userstatslink = new moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->cm->id]);

        // Get moodleoverflows where discussions can be moved.
        // The names as Moodle formats them (filters, HTML escaped without escaping existing entities again); the template
        // prints them as they are. The JS gets the plain text in an attribute, which the template escapes itself.
        $destinations = array_map(
            function (models\moodleoverflow $destination): array {
                $name = format_string($destination->name, true, ['context' => $destination->get_context()]);
                return [
                    'name' => $name,
                    'plainname' => html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'label' => get_string('movetopictoforum', 'moodleoverflow', ['forumname' => $name]),
                    'modflowid' => $destination->id,
                ];
            },
            service\discussion::get_move_destinations($this->modflow, $USER->id)
        );

        // Load the visible discussions.
        $discussions = service\discussion::get_discussions($this->modflow, $USER->id);
        $searchable = !empty($discussions);

        // Count the unread posts of all visible discussions at once; the cards show theirs, the page their sum. Counted
        // before the search, because "mark all read" marks the whole moodleoverflow, not only the matches.
        $unread = service\readtracking::count_unread_posts_per_discussion($this->modflow, $discussions, $USER->id);

        // Keep the discussions that match the search and cut out the current page.
        if ($this->query !== '') {
            $discussions = service\discussion::search($discussions, $this->query, $USER->id);
        }
        $discussioncount = count($discussions);
        $items = array_map(
            fn(models\discussion $discussion) => $OUTPUT->render(
                new discussion_card($discussion, !empty($destinations), $unread[$discussion->get_id()], $this->query)
            ),
            array_slice($discussions, $limitfrom, $limitamount)
        );

        // Get the link to the next posts that needs to be reviewed.
        $nextpost = service\review::get_next_post_to_review($this->modflow, $USER->id);

        // Collect the needed data being submitted to the template.
        $listurl = new moodle_url($this->modflow->get_link(), $this->query !== '' ? ['q' => $this->query] : []);
        $pagingbar = $OUTPUT->paging_bar($discussioncount, $this->page, $perpage, $listurl);
        $canstartdiscussion = permissions::can_start_discussion($this->modflow, $USER->id);

        // The menu "Forum actions" in three groups, separated by dividers: reading, notifications, statistics.
        $markallread = array_sum($unread) > 0 ? [
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

        return (object) [
            'discussions' => $items,
            'hasdiscussions' => $discussioncount > 0,
            'startdiscussion' => $canstartdiscussion ? ['link' => $startdiscussion->out()] : [],
            'menu' => $menu,
            'search' => $searchable ? [
                'action' => (new moodle_url('/mod/moodleoverflow/view.php'))->out(false),
                'inputname' => 'q',
                'searchstring' => get_string('searchforum', 'moodleoverflow'),
                'query' => $this->query,
                'hiddenfields' => [['name' => 'id', 'value' => $this->cm->id]],
                'result' => match (true) {
                    $this->query === '' => '',
                    $discussioncount > 0 => get_string('searchresults', 'moodleoverflow', $this->query),
                    default => get_string('searchnoresults', 'moodleoverflow', $this->query),
                },
                'clearurl' => $this->modflow->get_link()->out(false),
            ] : [],
            'searching' => $this->query !== '',
            'paging_bar' => ($this->page != -1) ? $pagingbar : false,
            'destinations' => $destinations,
            'intro' => $this->modflow->intro !== ''
                ? format_module_intro('moodleoverflow', $this->modflow->get_db_object(), $this->modflow->get_cm()->id)
                : '',
            'hints' => $this->get_hints($USER->id),
            'review_link' => $nextpost ? $nextpost->get_link()->out(false) : false,
        ];
    }

    /**
     * Returns the rules of the moodleoverflow for its participants: anonymity, review and the answer window, each with an
     * icon, a short label and a sentence.
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
