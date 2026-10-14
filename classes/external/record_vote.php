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

use core_external\external_single_structure;
use core_external\external_function_parameters;
use core_external\external_api;
use core_external\external_value;
use core_external\restricted_context_exception;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;
use core\exception\moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class implementing the external API, esp. for AJAX functions.
 * Saves a vote in the database.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class record_vote extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'postid' => new external_value(PARAM_INT, 'id of post'),
                'ratingid' => new external_value(PARAM_INT, 'rating'),
            ]
        );
    }

    /**
     * Returns the result of the vote (new rating and reputations).
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'postrating' => new external_value(PARAM_INT, 'new post rating'),
                'ownerreputation' => new external_value(PARAM_INT, 'new reputation of post owner'),
                'raterreputation' => new external_value(PARAM_INT, 'new reputation of rater'),
                'ownerid' => new external_value(PARAM_INT, 'user id of post owner'),
            ]
        );
    }

    /**
     * Records upvotes and downvotes.
     *
     * @param int $postid ID of post
     * @param int $ratingid Rating value
     * @return array with updated information about rating /reputation
     * @throws \invalid_parameter_exception|\dml_exception|moodle_exception|restricted_context_exception
     */
    public static function execute(int $postid, int $ratingid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid, 'ratingid' => $ratingid]);
        $post = post::from_id($params['postid']);
        $modflow = $post->get_moodleoverflow();
        self::validate_context($modflow->get_context());

        // The JS sends removals as the rating value × 10.
        $remove = $params['ratingid'] >= 10 && $params['ratingid'] % 10 === 0;
        $type = rating_type::tryFrom($remove ? intdiv($params['ratingid'], 10) : $params['ratingid'])
            ?? throw new moodle_exception('invalidratingid', 'moodleoverflow');
        match (true) {
            $type->is_vote() && !$remove => service\rating::vote($post, $USER->id, $type),
            $type->is_vote() => service\rating::remove_vote($post, $USER->id),
            !$remove => service\rating::mark($post, $USER->id, $type),
            default => service\rating::unmark($post, $USER->id, $type),
        };

        $ratings = service\rating::get_post_ratings($post->get_discussion())[$post->get_id()];
        return [
            'postrating' => $ratings->upvotes - $ratings->downvotes,
            'ownerreputation' => permissions::can_view_reputation($post, $USER->id)
                ? service\rating::get_reputation($modflow, $post->get_userid()) : null,
            'raterreputation' => service\rating::get_reputation($modflow, $USER->id),
            'ownerid' => permissions::can_view_author($post, $USER->id) ? $post->get_userid() : null,
        ];
    }
}
