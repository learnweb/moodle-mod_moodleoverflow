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
use context_module;
use mod_moodleoverflow\readtracking;
use dml_exception;
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
 * Mark an discussion or whole moodleoverflow as read.
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
                'userid' => new external_value(PARAM_INT, 'the user id'),
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
     * @param int $userid
     * @return int Return how many unread posts the user has in the discussion/moodleoverflow. JS uses it to update the unread info.
     *             (It should always be 0, otherwise an error ocurred. This is important for behat testing).
     * @throws coding_exception|dml_exception
     */
    public static function execute(int $instanceid, string $domain, int $userid): int {
        global $DB;
        if ($domain == 'moodleoverflow') {
            $cm = get_coursemodule_from_instance('moodleoverflow', $instanceid);
            readtracking::moodleoverflow_mark_moodleoverflow_read($cm, $userid);
            return readtracking::moodleoverflow_count_unread_posts_moodleoverflow($cm);
        } else {
            $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $instanceid]);
            $cm = get_coursemodule_from_instance('moodleoverflow', $discussion->moodleoverflow, $discussion->course);
            readtracking::moodleoverflow_mark_discussion_read($instanceid, context_module::instance($cm->id), $userid);
            return readtracking::moodleoverflow_count_unread_posts_discussion($instanceid);
        }
    }
}
