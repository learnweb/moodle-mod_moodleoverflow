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

/**
 * A scheduled task for moodleoverflow cron.
 *
 * @package   mod_moodleoverflow
 */

namespace mod_moodleoverflow\task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../locallib.php');

/**
 * Class for updating grades.
 *
 * @package   mod_moodleoverflow
 */
class update_grades extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskupdategrades', 'mod_moodleoverflow');
    }

    /**
     * Run moodleoverflow cron.
     */
    public function execute() {
		global $DB;

        // get all moodleoverflow instances
        $sql = 'SELECT DISTINCT id FROM mdl_moodleoverflow';
        $moodleoverflowids = $DB->get_fieldset_sql($sql);

		//iterate all moodleoverflow instances
		foreach($moodleoverflowids as $moodleoverflowid){

			// Update grades.
			moodleoverflow_update_all_grades($moodleoverflowid);
		}
     
        // The cron is finished.
        return true;
    }

}

