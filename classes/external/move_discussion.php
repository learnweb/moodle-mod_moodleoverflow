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
use external_function_parameters;
use external_api;
use external_value;
use mod_moodleoverflow\models\discussion;

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
     * Returns the result of the vote (new rating and reputations).
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'If move was successfull');
    }

    /**
     * Records upvotes and downvotes.
     *
     * @param int $discussionid
     * @param int $moodleoverflowid
     * @return bool
     * @throws dml_exception|coding_exception
     */
    public static function execute(int $discussionid, int $moodleoverflowid): bool {
        global $DB;
        $discussion = discussion::from_record($DB->get_record('moodleoverflow_discussions', ['id' => $discussionid]));
        return $discussion->move_dicussion($moodleoverflowid);
    }
}
