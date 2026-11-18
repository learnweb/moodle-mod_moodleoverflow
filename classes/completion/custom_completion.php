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

namespace mod_moodleoverflow\completion;

use core_completion\activity_custom_completion;

/**
 * The completion rule of a moodleoverflow: a user has written at least a number of posts (questions, answers or
 * comments). Only reviewed posts count, so a post that waits for its review counts once it is approved.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    #[\Override]
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);

        $required = $this->cm->customdata['customcompletionrules']['completionposts'] ?? 0;
        $sql = "SELECT COUNT(p.id)
                  FROM {moodleoverflow_posts} p
                  JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                 WHERE d.moodleoverflow = :modflowid AND p.userid = :userid AND p.reviewed = 1";
        $written = $DB->count_records_sql($sql, ['modflowid' => $this->cm->instance, 'userid' => $this->userid]);
        return $written >= $required ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    #[\Override]
    public static function get_defined_custom_rules(): array {
        return ['completionposts'];
    }

    #[\Override]
    public function get_custom_rule_descriptions(): array {
        $required = $this->cm->customdata['customcompletionrules']['completionposts'] ?? 0;
        return ['completionposts' => get_string('completiondetail:posts', 'moodleoverflow', $required)];
    }

    #[\Override]
    public function get_sort_order(): array {
        return ['completionview', 'completionposts', 'completionusegrade', 'completionpassgrade'];
    }
}
