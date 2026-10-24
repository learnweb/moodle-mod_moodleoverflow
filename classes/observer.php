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

namespace mod_moodleoverflow;

use mod_moodleoverflow\local\service\subscription;

/**
 * Event observer for mod_moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // Get user enrolment info from event.
        $cp = (object) $event->other['userenrolment'];

        // Check if the user was enrolled.
        if ($cp->lastenrol) {
            // Get the moodleoverflow instances from which the user was unenrolled from.
            $moodleoverflows = $DB->get_records('moodleoverflow', ['course' => $cp->courseid], '', 'id');

            // Do not continue if there are no connected moodleoverflow instances.
            if (!$moodleoverflows) {
                return;
            }

            // Get the sql parameters for the moodleoverflow instances and add the user ID.
            [$select, $params] = $DB->get_in_or_equal(array_keys($moodleoverflows), SQL_PARAMS_NAMED);
            $params['userid'] = $cp->userid;

            // Delete all records that are connected to those moodleoverflow instances.
            foreach (array_keys($moodleoverflows) as $modflowid) {
                subscription::delete_subscriptions(userid: $cp->userid, modflowid: $modflowid);
            }
            $DB->delete_records_select('moodleoverflow_read', 'userid = :userid AND moodleoverflowid ' . $select, $params);
        }
    }
}
