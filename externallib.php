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
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\output\moodleoverflow_email;
use mod_moodleoverflow\review;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Class implementing the external API, esp. for AJAX functions.
 *
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function record_vote_parameters() {
        return new external_function_parameters(
            array(
                'postid'       => new external_value(PARAM_INT, 'id of post'),
                'ratingid'     => new external_value(PARAM_INT, 'rating')
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
                'postrating'      => new external_value(PARAM_INT, 'new post rating'),
                'ownerreputation' => new external_value(PARAM_INT, 'new reputation of post owner'),
                'raterreputation' => new external_value(PARAM_INT, 'new reputation of rater'),
                'ownerid'         => new external_value(PARAM_INT, 'user id of post owner'),
            )
        );
    }

    /**
     * Records upvotes and downvotes.
     *
     * @param int $postid ID of post
     * @param int $ratingid Rating value
     * @return array with updated information about rating /reputation
     */
    public static function record_vote($postid, $ratingid) {
        global $DB, $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::record_vote_parameters(), array(
            'postid'       => $postid,
            'ratingid'     => $ratingid,
        ));

        $transaction = $DB->start_delegated_transaction();

        $post = $DB->get_record('moodleoverflow_posts', array('id' => $params['postid']), '*', MUST_EXIST);

        // Check if the discussion is valid.
        if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $post->discussion))) {
            throw new moodle_exception('invaliddiscussionid', 'moodleoverflow');
        }

        // Check if the related moodleoverflow instance is valid.
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $discussion->moodleoverflow))) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }

        // Check if the related moodleoverflow instance is valid.
        if (!$course = $DB->get_record('course', array('id' => $discussion->course))) {
            throw new moodle_exception('invalidcourseid');
        }

        // Get the related coursemodule and its context.
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        // Security checks.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/moodleoverflow:ratepost', $context);

        // Rate the post.
        if (!\mod_moodleoverflow\ratings::moodleoverflow_add_rating($moodleoverflow,
            $params['postid'], $params['ratingid'], $cm)) {
            throw new moodle_exception('ratingfailed', 'moodleoverflow');
        }

        $post = moodleoverflow_get_post_full($params['postid']);
        $postownerid = $post->userid;
        $rating      = \mod_moodleoverflow\ratings::moodleoverflow_get_ratings_by_discussion($discussion->id,
            $params['postid']);
        $ownerrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $postownerid);
        $raterrating = \mod_moodleoverflow\ratings::moodleoverflow_get_reputation($moodleoverflow->id, $USER->id);

        $cannotseeowner = \mod_moodleoverflow\anonymous::is_post_anonymous($discussion, $moodleoverflow, $USER->id) &&
            $USER->id != $postownerid;

        $params['postrating']      = $rating->upvotes - $rating->downvotes;
        $params['ownerreputation'] = $cannotseeowner ? null : $ownerrating;
        $params['raterreputation'] = $raterrating;
        $params['ownerid']         = $cannotseeowner ? null : $postownerid;

        $transaction->allow_commit();

        moodleoverflow_update_user_grade($moodleoverflow, $ownerrating, $postownerid);
        moodleoverflow_update_user_grade($moodleoverflow, $raterrating, $USER->id);

        return $params;
    }

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function review_approve_post_parameters() {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'id of post')
        ]);
    }

    /**
     * Returns description of return value.
     * @return external_value
     */
    public static function review_approve_post_returns() {
        return new external_value(PARAM_TEXT, 'the url of the next post to review');
    }

    /**
     * Approve a post.
     *
     * @param int $postid ID of post to approve.
     * @return string|null Url of next post to review.
     */
    public static function review_approve_post($postid) {
        global $DB;

        $params = self::validate_parameters(self::review_approve_post_parameters(), ['postid' => $postid]);
        $postid = $params['postid'];

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $postid], '*', MUST_EXIST);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow], '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $context = context_module::instance($cm->id);

        require_capability('mod/moodleoverflow:reviewpost', $context);

        if ($post->reviewed) {
            throw new coding_exception('post was already approved!');
        }

        if (!review::is_post_in_review_period($post)) {
            throw new coding_exception('post is not yet in review period!');
        }

        $post->reviewed = 1;
        $post->timereviewed = time();

        $DB->update_record('moodleoverflow_posts', $post);

        if ($post->modified > $discussion->timemodified) {
            $discussion->timemodified = $post->modified;
            $discussion->usermodified = $post->userid;
            $DB->update_record('moodleoverflow_discussions', $discussion);
        }

        return review::get_first_review_post($moodleoverflow->id, $post->id);
    }

    /**
     * Returns description of method parameters.
     * @return external_function_parameters
     */
    public static function review_reject_post_parameters() {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'id of post'),
            'reason' => new external_value(PARAM_RAW, 'reason of rejection')
        ]);
    }

    /**
     * Returns description of return value.
     * @return external_value
     */
    public static function review_reject_post_returns() {
        return new external_value(PARAM_TEXT, 'the url of the next post to review');
    }

    /**
     * Rejects a post.
     *
     * @param int $postid ID of post to reject.
     * @param string|null $reason The reason for rejection.
     * @return string|null Url of next post to review.
     */
    public static function review_reject_post($postid, $reason = null) {
        global $DB, $PAGE, $OUTPUT;

        $params = self::validate_parameters(self::review_reject_post_parameters(), ['postid' => $postid, 'reason' => $reason]);
        $postid = $params['postid'];

        $post = $DB->get_record('moodleoverflow_posts', ['id' => $postid], '*', MUST_EXIST);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $discussion->moodleoverflow], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        $course = get_course($cm->course);
        $context = context_module::instance($cm->id);

        $PAGE->set_context($context);

        require_capability('mod/moodleoverflow:reviewpost', $context);

        if ($post->reviewed) {
            throw new coding_exception('post was already approved!');
        }

        if (!review::is_post_in_review_period($post)) {
            throw new coding_exception('post is not yet in review period!');
        }

        // Has to be done before deleting the post.
        $rendererhtml = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'htmlemail');
        $renderertext = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'textemail');

        $userto = core_user::get_user($post->userid);

        $maildata = new moodleoverflow_email(
                $course,
                $cm,
                $moodleoverflow,
                $discussion,
                $post,
                $userto,
                $userto,
                false
        );

        $textcontext = $maildata->export_for_template($renderertext, true);
        $htmlcontext = $maildata->export_for_template($rendererhtml, false);

        if ($params['reason'] ?? null) {
            $htmlcontext['reason'] = format_text_email($params['reason'], FORMAT_PLAIN);
            $textcontext['reason'] = $htmlcontext['reason'];
        }

        email_to_user(
                $userto,
                \core_user::get_noreply_user(),
                get_string('email_rejected_subject', 'moodleoverflow', $textcontext),
                $OUTPUT->render_from_template('mod_moodleoverflow/email_rejected_text', $textcontext),
                $OUTPUT->render_from_template('mod_moodleoverflow/email_rejected_html', $htmlcontext)
        );

        $url = review::get_first_review_post($moodleoverflow->id, $post->id);

        if (!$post->parent) {
            // Delete discussion, if this is the question.
            moodleoverflow_delete_discussion($discussion, $course, $cm, $moodleoverflow);
        } else {
            moodleoverflow_delete_post($post, true, $cm, $moodleoverflow);
        }

        return $url;
    }
}
