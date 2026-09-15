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
use core\exception\moodle_exception;
use mod_moodleoverflow\readtracking;
use core_external\external_function_parameters;
use core_external\external_api;
use core_external\external_value;


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
     * @param bool $tracked current readtracking status.
     * @param int $moodleoverflowid The moodleoverflow that is being targeted.
     * @return bool
     */
    public static function execute(bool $tracked, int $moodleoverflowid): bool {
        global $USER, $DB;
        self::validate_parameters(self::execute_parameters(), ['tracked' => $tracked, 'moodleoverflowid' => $moodleoverflowid]);

        // Get data.
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflowid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $moodleoverflow->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Security checks.
        self::validate_context($context);
        require_capability('mod/moodleoverflow:viewdiscussion', $context);
        if (isguestuser() || $moodleoverflow->trackingtype != MOODLEOVERFLOW_TRACKING_OPTIONAL) {
            throw new moodle_exception('cannotchangetracking', 'moodleoverflow');
        }

        // Execute action.
        if ($tracked) {
            return readtracking::stop_tracking($moodleoverflowid, $USER->id);
        } else {
            return readtracking::start_tracking($moodleoverflowid, $USER->id);
        }
    }
}
