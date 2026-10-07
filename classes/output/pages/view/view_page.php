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

use context_module;
use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\readtracking;
use mod_moodleoverflow\review;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * This class represents the output of the view page (view.php). It shows a collection of discussion cards.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_page implements named_templatable, renderable {
    /** @var moodleoverflow The moodleoverflow that gets shown */
    public moodleoverflow $modflow;

    /** @var int Which page gets shown */
    public int $page;

    /** @var object The coursemodule */
    public object $cm;

    /**
     * Constructor.
     * @param moodleoverflow $moodleoverflow The moodleoverflow DB object
     * @param int $page Which "page" should get
     */
    public function __construct(moodleoverflow $moodleoverflow, int $page = -1) {
        $this->modflow = $moodleoverflow;
        $this->page = $page;
        $module = 'moodleoverflow';
        $this->cm = get_coursemodule_from_instance($module, $this->modflow->id, $this->modflow->course, false, MUST_EXIST);
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/view/view_page';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $OUTPUT, $DB, $USER;
        $perpage = get_config('moodleoverflow', 'manydiscussions');

        // Set the context.
        $context = context_module::instance($this->cm->id);

        // If the perpage value is invalid, deactivate paging.
        if ($perpage <= 0) {
            $perpage = 0;
            $this->page = -1;
        }
        $usepaging = ($perpage > 0 && $this->page !== -1);
        $limitfrom = $usepaging ? $this->page * $perpage : 0;
        $limitamount = $usepaging ? $perpage : 0;

        // Check some capabilities and create other check variables.
        $canstartdiscussion = permissions::can_start_discussion($this->modflow, $USER->id);

        // Create links.
        $startdiscussion = new moodle_url('/mod/moodleoverflow/post.php', ['moodleoverflow' => $this->modflow->id]);
        $userstatslink = new moodle_url('/mod/moodleoverflow/userstats.php', ['id' => $this->cm->id]);

        // Get information about the moodleoverflow. This includes: discussioncount, unread posts, discussions its replies.
        $discussioncount = moodleoverflow_get_discussions_count($this->modflow, $USER->id);
        $unreads = readtracking::count_unread_posts_moodleoverflow($this->modflow, $USER->id) > 0;
        $pagingbar = $OUTPUT->paging_bar($discussioncount, $this->page, $perpage, "view.php?id={$this->cm->id}");

        // Get moodleoverflow where discussions can be moved.
        $destinations = [];
        foreach ($DB->get_records('moodleoverflow', ['course' => $this->modflow->course]) as $record) {
            $destination = moodleoverflow::from_record($record);
            if (permissions::can_move_discussions($this->modflow, $destination, $USER->id)) {
                $destinations[] = ['name' => $destination->name, 'modflowid' => $destination->id];
            }
        }

        // Iterate through every visible discussion and build the discussion card.
        $canreview = permissions::can_review_posts($this->modflow, $USER->id);
        $items = [];
        $sql = "SELECT d.*
            FROM {moodleoverflow_discussions} d
            JOIN {moodleoverflow_posts} p ON p.discussion = d.id
            WHERE d.moodleoverflow = ?
                AND p.parent = 0
                AND (? = 1 OR (p.reviewed = 1 OR p.userid = ?))
            ORDER BY d.timestart DESC, d.id DESC";
        $discussions = $DB->get_records_sql($sql, [$this->modflow->id, $canreview ? 1 : 0, $USER->id], $limitfrom, $limitamount);
        foreach ($discussions as $discussion) {
            $items[] = $OUTPUT->render(new discussion_card(discussion::from_record($discussion), $context, !empty($destinations)));
        }

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

        // Collect the needed data being submitted to the template.
        return (object) [
            'discussions' => $items,
            'hasdiscussions' => count($discussions) > 0,
            'startdiscussion' => $canstartdiscussion ? ['link' => $startdiscussion->out()] : [],
            'markallread' => $unreads ? [
                'itemid' => 'moodleoverflow-markallread-' . $this->modflow->id,
                'instanceid' => $this->modflow->id,
            ] : [],
            'stats' => permissions::can_view_userstats($this->modflow, $USER->id) ? ['link' => $userstatslink->out()] : [],
            'paging_bar' => ($this->page != -1) ? $pagingbar : false,
            'destinations' => $destinations,
            'anonymous_desc' => $anonymousdesc,
            'review_desc' => $reviewdesc,
            'review_link' => $canreview ? review::get_first_review_post($this->modflow->id) : false,
        ];
    }
}
