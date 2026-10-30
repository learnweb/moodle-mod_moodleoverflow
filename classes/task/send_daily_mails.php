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

use core\task\scheduled_task;
use mod_moodleoverflow\local\service;

/**
 * Sends the daily digest of new posts to every user who chose a digest in their profile.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_daily_mails extends scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('tasksenddailymails', 'mod_moodleoverflow');
    }

    #[\Override]
    public function execute() {
        foreach (service\mail::send_digests() as $userid => $reason) {
            mtrace("The digest could not be sent to user $userid: $reason");
        }
    }
}
