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

    /**
     * Constructor.
     * @param models\moodleoverflow $moodleoverflow The moodleoverflow DB object
     * @param int $page Which "page" should get
     */
    public function __construct(models\moodleoverflow $moodleoverflow, int $page = -1) {
        $this->modflow = $moodleoverflow;
        $this->page = $page;
        $this->cm = $moodleoverflow->get_cm();
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
        $destinations = array_map(
            fn(models\moodleoverflow $destination) => ['name' => $destination->name, 'modflowid' => $destination->id],
            service\discussion::get_move_destinations($this->modflow, $USER->id)
        );

        // Load the visible discussions and cut out the current page.
        $discussions = service\discussion::get_discussions($this->modflow, $USER->id);
        $discussioncount = count($discussions);

        // Count the unread posts of all visible discussions at once; the cards show theirs, the page their sum.
        $unread = service\readtracking::count_unread_posts_per_discussion($this->modflow, $discussions, $USER->id);
        $items = array_map(
            fn(models\discussion $discussion) => $OUTPUT->render(
                new discussion_card($discussion, !empty($destinations), $unread[$discussion->get_id()])
            ),
            array_slice($discussions, $limitfrom, $limitamount)
        );

        // Anonymous mode and Review mod description string identifier.
        $anonymousdesc = match ($this->modflow->get_anonymity()) {
            anonymity::QUESTIONS => get_string('desc:only_questions', 'moodleoverflow'),
            anonymity::EVERYTHING => get_string('desc:anonymous', 'moodleoverflow'),
            default => ''
        };
        $reviewdesc = match ($this->modflow->get_review_level()) {
            review_level::QUESTIONS => get_string('desc:review_questions', 'moodleoverflow'),
            review_level::EVERYTHING => get_string('desc:review_everything', 'moodleoverflow'),
            default => ''
        };

        // Get the link to the next posts that needs to be reviewed.
        $nextpost = service\review::get_next_post_to_review($this->modflow, $USER->id);

        // Collect the needed data being submitted to the template.
        $pagingbar = $OUTPUT->paging_bar($discussioncount, $this->page, $perpage, "view.php?id={$this->cm->id}");
        $canstartdiscussion = permissions::can_start_discussion($this->modflow, $USER->id);
        return (object) [
            'discussions' => $items,
            'hasdiscussions' => $discussioncount > 0,
            'startdiscussion' => $canstartdiscussion ? ['link' => $startdiscussion->out()] : [],
            'subscription' => permissions::can_change_subscription($this->modflow, $USER->id) ? [
                'type' => 'moodleoverflow',
                'id' => $this->modflow->id,
                'subscribed' => service\subscription::is_subscribed($this->modflow, $USER->id),
            ] : [],
            'markallread' => array_sum($unread) > 0 ? [
                'itemid' => 'moodleoverflow-markallread-' . $this->modflow->id,
                'instanceid' => $this->modflow->id,
            ] : [],
            'stats' => permissions::can_view_userstats($this->modflow, $USER->id) ? ['link' => $userstatslink->out()] : [],
            'paging_bar' => ($this->page != -1) ? $pagingbar : false,
            'destinations' => $destinations,
            'anonymous_desc' => $anonymousdesc,
            'review_desc' => $reviewdesc,
            'review_link' => $nextpost ? $nextpost->get_link()->out(false) : false,
        ];
    }
}
