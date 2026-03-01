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

namespace mod_moodleoverflow\task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../locallib.php');

/**
 * A scheduled task for moodleoverflow cron to clean up read records.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class clean_readrecords extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('taskcleanreadrecords', 'mod_moodleoverflow');
    }

    #[\Override]
    public function execute() {

        // Delete the old read records.
        \mod_moodleoverflow\readtracking::moodleoverflow_clean_read_records();

        // The cron is finished.
        return true;
    }
}
