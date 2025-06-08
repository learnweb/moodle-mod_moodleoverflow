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
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\task;

use coding_exception;
use lang_string;
use mod_moodleoverflow\manager\mail_manager;

/**
 * Class for sending mails to users that need to review a moodleoverflow post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_mails extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shwon to admins).
     *
     * @return lang_string|string
     * @throws coding_exception
     */
    public function get_name() {
        return get_string('tasksendmails', 'mod_moodleoverflow');
    }

    /**
     * Runs moodleoverflow cron.
     *
     * @return bool
     */
    public function execute() {
        mail_manager::moodleoverflow_send_mails();
        return true;
    }
}
