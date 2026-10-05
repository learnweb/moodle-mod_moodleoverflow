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

use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\review;
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
 * Approves a post that is currently reviewed.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_approve_post extends external_api {
    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'id of post'),
        ]);
    }

    /**
     * Returns description of return value.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_TEXT, 'the url of the next post to review');
    }

    /**
     * Approve a post.
     *
     * @param int $postid ID of post to approve.
     * @return string|null Url of next post to review.
     */
    public static function execute($postid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid]);
        $post = post::from_id($params['postid']);
        $discussion = $post->get_discussion();
        $moodleoverflow = $discussion->get_moodleoverflow();
        self::validate_context($moodleoverflow->get_context());
        permissions::ensure(permissions::can_review_post($post, $USER->id), 'cannotreviewpost');

        $post->reviewed = 1;
        $post->timereviewed = time();

        $DB->update_record('moodleoverflow_posts', $post->get_db_object());

        if ($post->modified > $discussion->timemodified) {
            $discussion->timemodified = $post->modified;
            $discussion->usermodified = $post->get_userid();
            $DB->update_record('moodleoverflow_discussions', $discussion->get_db_object());
        }

        return review::get_first_review_post($moodleoverflow->id, $post->get_id());
    }
}
