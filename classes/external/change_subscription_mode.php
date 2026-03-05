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

namespace mod_moodleoverflow\external;

use context_module;
use mod_moodleoverflow\subscriptions;
use external_function_parameters;
use external_api;
use external_value;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class implementing the external API, esp. for AJAX functions.
 * Changes the subscription mode of a user.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class change_subscription_mode extends external_api {
    /**
     * Returns description of method parameters for execute
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'the user id'),
                'subscribed' => new external_value(PARAM_BOOL, 'current subscription status'),
                'cmid' => new external_value(PARAM_INT, 'course module id that is targeted'),
            ]
        );
    }

    /**
     * Return the result of the execute function
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'true if successful');
    }

    /**
     * Changes the subscription mode on a moodleoverflow
     * @param int $userid The user the setting will be changed for.
     * @param bool $subscribed current subscription status. True if user is subscribed, false it user is not subscribed.
     * @param int $cmid The course module id of the moodleoverflow that is being targeted.
     * @return bool
     */
    public static function execute(int $userid, bool $subscribed, int $cmid): bool {
        global $DB;
        // Get the moodleoverflow from the cmid.
        $cm = get_coursemodule_from_id('moodleoverflow', $cmid, 0, false, MUST_EXIST);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $cm->instance], '*', MUST_EXIST);
        $modulecontext = context_module::instance($cmid);

        if ($subscribed) {
            return subscriptions::unsubscribe_user($userid, $moodleoverflow, $modulecontext, true);
        } else {
            return subscriptions::subscribe_user($userid, $moodleoverflow, $modulecontext, true);
        }
    }
}
