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
use mod_moodleoverflow\review;
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
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid]);
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
}
