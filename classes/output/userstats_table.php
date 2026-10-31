<?php
// This file is part of Moodle - http://moodle.org/
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

defined('MOODLE_INTERNAL') || die();

use core\output\html_writer;
use mod_moodleoverflow\local\dto\userstats_dto;
use moodle_url;

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * The user statistics as a table, for Moodle versions before 5.3 that cannot show the React page (UserStatsPage).
 *
 * This page is not supported anymore: new statistics and features only go into the React page. The table only shows
 * the numbers of service\userstats::get_statistics().
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userstats_table extends \flexible_table {
    /**
     * Constructor.
     *
     * @param string $uniqueid Unique id of this table.
     * @param userstats_dto[] $statistics The statistics of the participants.
     * @param moodle_url $url The url of the table.
     */
    public function __construct(
        string $uniqueid,
        /** @var userstats_dto[] $statistics The statistics of the participants */
        private array $statistics,
        moodle_url $url
    ) {
        global $PAGE, $OUTPUT;
        parent::__construct($uniqueid);
        $PAGE->requires->js_call_amd('mod_moodleoverflow/activityhelp', 'init');
        $helpactivity = $OUTPUT->help_icon('helpamountofactivity', 'moodleoverflow');

        $this->set_attribute('class', 'moodleoverflow-statistics-table');
        $this->set_attribute('id', $uniqueid);
        $this->define_columns(['name', 'courseupvotes', 'coursedownvotes', 'forumactivity', 'courseactivity',
                               'forumreputation', 'coursereputation', ]);
        $this->define_baseurl($url);
        $this->define_headers([get_string('fullnameuser'),
                               get_string('userstatsupvotes', 'moodleoverflow'),
                               get_string('userstatsdownvotes', 'moodleoverflow'),
                               get_string('userstatsforumactivity', 'moodleoverflow') . $helpactivity,
                               get_string('userstatscourseactivity', 'moodleoverflow') . $helpactivity,
                               get_string('userstatsforumreputation', 'moodleoverflow'),
                               get_string('userstatscoursereputation', 'moodleoverflow'), ]);
        $this->sortable(true, 'coursereputation', SORT_DESC);
        $this->no_sorting('name');
        $this->setup();
    }

    /**
     * Prints the table.
     * @return void
     */
    public function out(): void {
        ['sortby' => $sortby, 'sortorder' => $sortorder] = $this->get_sort_order();
        $direction = $sortorder == SORT_ASC ? 1 : -1;
        $rows = array_map(fn(userstats_dto $dto) => (object) $dto->to_array(), $this->statistics);
        usort($rows, fn($a, $b) => $direction * ($a->$sortby <=> $b->$sortby));

        $this->start_output();
        $this->format_and_add_array_of_rows($rows, true);
        $this->finish_output();
    }

    /**
     * Name column: links to the course profile of the user.
     * @param object $row
     * @return string
     */
    public function col_name($row): string {
        return html_writer::link($row->profileurl, s($row->name));
    }

    /**
     * Every other column is a number, shown as a badge.
     * @param string $column
     * @param object $row
     * @return string
     */
    public function other_cols($column, $row): string {
        $number = $row->$column;
        $class = $number > 0 ? 'badge bg-success' : 'badge bg-warning';
        return html_writer::tag('h5', html_writer::span($number, $class));
    }
}
