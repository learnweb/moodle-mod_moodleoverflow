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

use coding_exception;
use dml_exception;
use core_external\external_function_parameters;
use core_external\external_api;
use core_external\external_value;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class implementing the external API, esp. for AJAX functions.
 * Moves a discussion to another moodleoverflow
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class move_discussion extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussion that will be moved'),
                'moodleoverflowid' => new external_value(PARAM_INT, 'destination moodleoverflow'),
            ]
        );
    }

    /**
     * External result.
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'If move was successfull');
    }

    /**
     * Moves discussion from one moodleoverflow to another.
     *
     * @param int $discussionid
     * @param int $moodleoverflowid
     * @return bool
     * @throws dml_exception|coding_exception|moodle_exception
     */
    public static function execute(int $discussionid, int $moodleoverflowid): bool {
        global $USER;
        // Validation.
        $params = ['discussionid' => $discussionid, 'moodleoverflowid' => $moodleoverflowid];
        self::validate_parameters(self::execute_parameters(), $params);

        $discussion = discussion::from_id($params['discussionid']);
        $destination = moodleoverflow::from_id($params['moodleoverflowid']);

        self::validate_context($discussion->get_moodleoverflow()->get_context());
        service\discussion::move($discussion, $destination, $USER->id);
        return true;
    }
}
