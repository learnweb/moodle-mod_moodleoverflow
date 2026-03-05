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

use mod_moodleoverflow\readtracking;
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
 * Changes the readtracking mode of a user.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class change_readtracking_mode extends external_api {
    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'the user id'),
                'tracked' => new external_value(PARAM_BOOL, 'current tracking status'),
                'moodleoverflowid' => new external_value(PARAM_INT, 'moodleoverflow that is targeted'),
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
     * @param bool $tracked current readtracking status.
     * @param int $moodleoverflowid The moodleoverflow that is being targeted.
     * @return bool
     */
    public static function execute(int $userid, bool $tracked, int $moodleoverflowid): bool {
        if ($tracked) {
            return readtracking::moodleoverflow_stop_tracking($moodleoverflowid, $userid);
        } else {
            return readtracking::moodleoverflow_start_tracking($moodleoverflowid, $userid);
        }
    }
}
