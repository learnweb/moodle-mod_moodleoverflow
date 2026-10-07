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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use dml_exception;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service\readtracking;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class implementing the external API, esp. for AJAX functions.
 * Mark a discussion or whole moodleoverflow as read.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mark_post_read extends external_api {
    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'instanceid' => new external_value(PARAM_INT, 'Id of the discussion or moodleoverflow'),
                'domain' => new external_value(PARAM_TEXT, 'If a discussion or moodleoverflow is targeted'),
            ]
        );
    }

    /**
     * Return the result of the execute function
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_INT, 'Amount of unread posts after calling the function');
    }

    /**
     * Marks all posts of a discussion/moodleoverflow as read
     * @param int $instanceid id of the discussion/moodleoverflow.
     * @param string $domain Can be "moodleoverflow" or "discussion"
     * @return int Return how many unread posts the user has in the discussion/moodleoverflow. JS uses it to update the unread info.
     *             (It should always be 0, otherwise an error ocurred. This is important for behat testing).
     * @throws coding_exception|dml_exception|\moodle_exception
     */
    public static function execute(int $instanceid, string $domain): int {
        global $USER;

        // Validation.
        $params = self::validate_parameters(self::execute_parameters(), ['instanceid' => $instanceid, 'domain' => $domain]);
        if (!in_array($params['domain'], ['moodleoverflow', 'discussion'])) {
            throw new \invalid_parameter_exception('Use a valid parameter for the domain.');
        }

        // Get data.
        $discussion = null;
        if ($params['domain'] === 'discussion') {
            $discussion = discussion::from_id($params['instanceid']);
            $moodleoverflow = $discussion->get_moodleoverflow();
        } else {
            $moodleoverflow = moodleoverflow::from_id($params['instanceid']);
        }

        self::validate_context($moodleoverflow->get_context());

        // Execute the readtracking action.
        if ($discussion === null) {
            readtracking::mark_moodleoverflow_read($moodleoverflow, $USER->id);
            return readtracking::count_unread_posts_moodleoverflow($moodleoverflow, $USER->id);
        } else {
            readtracking::mark_discussion_read($discussion, $USER->id);
            return readtracking::count_unread_posts_discussion($discussion, $USER->id);
        }
    }
}
