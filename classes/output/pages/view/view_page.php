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
use mod_moodleoverflow\local\models;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\output\forum_header;
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

        // Collect the needed data being submitted to the template.
        $listurl = new moodle_url($this->modflow->get_link(), $this->query !== '' ? ['q' => $this->query] : []);
        $pagingbar = $OUTPUT->paging_bar($discussioncount, $this->page, $perpage, $listurl);
        return (object) [
            'discussions' => $items,
            'hasdiscussions' => $discussioncount > 0,
            'header' => (new forum_header($this->modflow, array_sum($unread)))->export_for_template($output),
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
        ];
    }
}
