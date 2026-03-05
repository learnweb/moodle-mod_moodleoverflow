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
use external_single_structure;
use mod_moodleoverflow\anonymous;
use external_function_parameters;
use external_api;
use external_value;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/externallib.php');
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
     */
    public static function execute($postid, $ratingid) {
        global $DB, $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'postid' => $postid,
            'ratingid' => $ratingid,
        ]);

        $transaction = $DB->start_delegated_transaction();

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $params['postid']], '*', MUST_EXIST);

        // Check if the discussion is valid.
        $discussion = moodleoverflow_get_record_or_exception(
            'moodleoverflow_discussions',
            ['id' => $post->discussion],
            'invaliddiscussionid'
        );

        // Check if the related moodleoverflow instance is valid.
        $moodleoverflow = moodleoverflow_get_record_or_exception(
            'moodleoverflow',
            ['id' => $discussion->moodleoverflow],
            'invalidmoodleoverflowid'
        );

        // Check if the related moodleoverflow instance is valid.
        $course = moodleoverflow_get_record_or_exception('course', ['id' => $discussion->course], 'invalidcourseid', '*', true);

        // Get the related coursemodule and its context.
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        // Security checks.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/moodleoverflow:ratepost', $context);

        // Rate the post.
        if (
            !\mod_moodleoverflow\ratings::moodleoverflow_add_rating(
                $moodleoverflow,
                $params['postid'],
                $params['ratingid'],
                $cm,
                $USER->id
            )
        ) {
            throw new moodle_exception('ratingfailed', 'moodleoverflow');
        }

        $post = moodleoverflow_get_post_full($params['postid']);
        $postownerid = $post->userid;
        $rating = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion(
            $discussion->id,
            $params['postid']
        );
        $ownerrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $postownerid);
        $raterrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $USER->id);

        $cannotseeowner = anonymous::is_post_anonymous($discussion, $moodleoverflow, $USER->id) &&
            $USER->id != $postownerid;

        $params['postrating'] = $rating->upvotes - $rating->downvotes;
        $params['ownerreputation'] = $cannotseeowner ? null : $ownerrating;
        $params['raterreputation'] = $raterrating;
        $params['ownerid'] = $cannotseeowner ? null : $postownerid;

        $transaction->allow_commit();

        moodleoverflow_update_user_grade($moodleoverflow, $ownerrating, $postownerid);
        moodleoverflow_update_user_grade($moodleoverflow, $raterrating, $USER->id);

        return $params;
    }
}
