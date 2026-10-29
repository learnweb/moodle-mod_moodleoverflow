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

namespace mod_moodleoverflow\task;

use core\task\adhoc_task;
use dml_missing_record_exception;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use Throwable;

/**
 * Sends the notification mail about a new post to the subscribers of its discussion.
 *
 * Queued by service\mail::queue_notification() with the post id only. The post is loaded when the task runs, so the
 * mail shows the post as it is then. The task never fails: errors are written to the task log, because running it
 * again would send the mail twice to the recipients who already got it.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_post_notification extends adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('tasksendpostnotification', 'mod_moodleoverflow');
    }

    #[\Override]
    public function execute(): void {
        $postid = $this->get_custom_data()->postid;
        try {
            $failures = service\mail::send_notification(post::from_id($postid));
        } catch (dml_missing_record_exception) {
            // The post was deleted before its mail was due.
            return;
        } catch (Throwable $e) {
            mtrace("The notification about post $postid could not be sent: {$e->getMessage()}");
            return;
        }
        foreach ($failures as $userid => $reason) {
            mtrace("The notification about post $postid could not be sent to user $userid: $reason");
        }
    }
}
