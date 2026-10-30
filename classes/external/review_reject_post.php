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
use mod_moodleoverflow\local\service;
use core_external\external_function_parameters;
use core_external\external_api;
use core_external\external_value;

/**
 * Class implementing the external API, esp. for AJAX functions.
 * Rejects a post that is currently reviewed.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_reject_post extends external_api {
    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'id of post'),
            'reason' => new external_value(PARAM_RAW, 'reason of rejection'),
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
     * Rejects a post.
     *
     * @param int $postid ID of post to reject.
     * @param string|null $reason The reason for rejection.
     * @return string Url of next post to review, empty if there is none.
     */
    public static function execute($postid, $reason = null) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid, 'reason' => $reason]);
        $post = post::from_id($params['postid']);
        $moodleoverflow = $post->get_moodleoverflow();
        self::validate_context($moodleoverflow->get_context());

        service\review::reject($post, $USER->id, $params['reason']);
        $nextpost = service\review::get_next_post_to_review($moodleoverflow, $USER->id);
        return $nextpost?->get_link()->out(false) ?? '';
    }
}
