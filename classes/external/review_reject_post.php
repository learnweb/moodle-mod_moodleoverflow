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

use core_user;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\output\moodleoverflow_email;
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
        global $PAGE, $OUTPUT, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid, 'reason' => $reason]);
        $post = post::from_id($params['postid']);
        $discussion = $post->get_discussion();
        $moodleoverflow = $post->get_moodleoverflow();
        $context = $moodleoverflow->get_context();
        self::validate_context($context);
        $PAGE->set_context($context);

        // The mail is rendered before the post is deleted, but only sent once the rejection succeeded.
        $mail = null;
        if ($post->get_userid() != 0) {
            $rendererhtml = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'htmlemail');
            $renderertext = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'textemail');

            $userto = core_user::get_user($post->get_userid());
            $userto->anonymous = $moodleoverflow->is_author_anonymous($discussion->get_userid() == $post->get_userid());

            $maildata = new moodleoverflow_email(
                $moodleoverflow->get_course(),
                $moodleoverflow->get_cm(),
                $moodleoverflow,
                $discussion->get_db_object(),
                $post->get_db_object(),
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

            $mail = [
                $userto,
                core_user::get_noreply_user(),
                get_string('email_rejected_subject', 'moodleoverflow', $textcontext),
                $OUTPUT->render_from_template('mod_moodleoverflow/email_rejected_text', $textcontext),
                $OUTPUT->render_from_template('mod_moodleoverflow/email_rejected_html', $htmlcontext),
            ];
        }

        service\review::reject($post, $USER->id);
        $nextpost = service\review::get_next_post_to_review($moodleoverflow, $USER->id);

        if ($mail) {
            email_to_user(...$mail);
        }

        return $nextpost?->get_link()->out(false) ?? '';
    }
}
