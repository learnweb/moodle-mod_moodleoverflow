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

/**
 * External moodleoverflow API
 *
 * @package    mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_moodleoverflow_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function record_vote_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'id of discussion'),
                'postid' => new external_value(PARAM_INT, 'id of post'),
                'ratingid' => new external_value(PARAM_INT, 'rating'),
                'userid' => new external_value(PARAM_INT, 'user id')
            )
        );
    }

    /**
     * Returns the result of the vote (new rating and reputations).
     * @return external_multiple_structure
     */
    public static function record_vote_returns() {
        return new external_single_structure(

            array(
                'postrating' => new external_value(PARAM_INT, 'new post rating'),
                'ownerreputation' => new external_value(PARAM_INT, 'new reputation of post owner'),
                'raterreputation' => new external_value(PARAM_INT, 'new reputation of rater'),
            )

        );
    }

    /**
     * Records upvotes and downvotes.
     * @param array $vote
     * @return array with updated information about rating /reputation
     */
    public static function record_vote($discussionid, $postid, $ratingid, $userid) {
        global $DB;

        // Parameter validation.
        $params = self::validate_parameters(self::record_vote_parameters(), array(
            'discussionid' => $discussionid,
            'postid' => $postid,
            'ratingid' => $ratingid,
            'userid' => $userid
        ));

        $transaction = $DB->start_delegated_transaction();

        // Check if the discussion is valid.
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $params['discussionid']))) {
            print_error('invaliddiscussionid', 'moodleoverflow');
        }

        // Check if the related moodleoverflow instance is valid.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
            print_error('invalidmoodleoverflowid', 'moodleoverflow');
        }

        // Check if the related moodleoverflow instance is valid.
        if (!$course = $DB->get_record('course', array('id' => $discussion->course))) {
            print_error('invalidcourseid');
        }

        // Get the related coursemodule and its context.
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
            print_error('invalidcoursemodule');
        }

        // Security checks
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/moodleoverflow:ratepost', $context);

        // Rate the post.
        if (!\mod_moodleoverflow\ratings::moodleoverflow_add_rating($moodleoverflow, $params['postid'], $params['ratingid'], $cm)) {
            print_error('ratingfailed', 'moodleoverflow');
        }
        $rating = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($discussion->id, $params['postid']);
        $postownerid = moodleoverflow_get_post_full($params['postid'])->userid;
        $ownerrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $postownerid);
        $raterrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $params['userid']);

        $params['postrating'] = $rating->upvotes - $rating->downvotes;
        $params['ownerreputation'] = $ownerrating;
        $params['raterreputation'] = $raterrating;

        $transaction->allow_commit();
        return $params;
    }
}
