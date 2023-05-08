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
 * A scheduled task for moodleoverflow cron.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\task;

use core\session\exception;
use mod_moodleoverflow\output\moodleoverflow_email;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../locallib.php');

/**
 * Class for sending mails to users who have subscribed a moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_mails extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasksendmails', 'mod_moodleoverflow');
    }

    /**
     * Runs moodleoverflow cron.
     */
    public function execute() {

        // Send mail notifications.
        moodleoverflow_send_mails();

        $this->send_review_notifications();

        // The cron is finished.
        return true;

    }

    /**
     * Sends initial notifications for needed reviews to all users with review capability.
     */
    public function send_review_notifications() {
        global $DB, $OUTPUT, $PAGE;

        $rendererhtml = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'htmlemail');
        $renderertext = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'textemail');

        $postinfos = $DB->get_records_sql(
            'SELECT p.*, d.course as cid, d.moodleoverflow as mid, d.id as did FROM {moodleoverflow_posts} p ' .
            'JOIN {moodleoverflow_discussions} d ON p.discussion = d.id ' .
            "WHERE p.mailed = :mailpending AND p.reviewed = 0 AND p.created < :timecutoff " .
            "ORDER BY d.course, d.moodleoverflow, d.id",
            [
                'mailpending' => MOODLEOVERFLOW_MAILED_PENDING,
                'timecutoff' => time() - get_config('moodleoverflow', 'reviewpossibleaftertime')
            ]
        );

        if (empty($postinfos)) {
            mtrace('No review notifications to send.');
            return;
        }

        $course = null;

        $moodleoverflow = null;
        $usersto = null;
        $cm = null;

        $discussion = null;

        $success = [];

        foreach ($postinfos as $postinfo) {
            if ($course == null || $course->id != $postinfo->cid) {
                $course = get_course($postinfo->cid);
            }

            if ($moodleoverflow == null || $moodleoverflow->id != $postinfo->mid) {
                $cm = get_coursemodule_from_instance('moodleoverflow', $postinfo->mid, 0, false, MUST_EXIST);
                $modulecontext = \context_module::instance($cm->id);
                $userswithcapability = get_users_by_capability($modulecontext, 'mod/moodleoverflow:reviewpost');
                $coursecontext = \context_course::instance($course->id);
                $usersenrolled = get_enrolled_users($coursecontext);
                $usersto = array();
                foreach ($userswithcapability as $user) {
                    if (in_array($user, $usersenrolled)) {
                        array_push($usersto, $user);
                    }
                }

                $moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $postinfo->mid], '*', MUST_EXIST);
            }

            if ($discussion == null || $discussion->id != $postinfo->did) {
                $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $postinfo->did], '*', MUST_EXIST);
            }

            $post = $postinfo;
            $userfrom = \core_user::get_user($postinfo->userid, '*', MUST_EXIST);

            foreach ($usersto as $userto) {
                try {
                    \core\cron::setup_user($userto, $course);

                    $maildata = new moodleoverflow_email(
                        $course,
                        $cm,
                        $moodleoverflow,
                        $discussion,
                        $post,
                        $userfrom,
                        $userto,
                        false
                    );

                    $textcontext = $maildata->export_for_template($renderertext, true);
                    $htmlcontext = $maildata->export_for_template($rendererhtml, false);

                    email_to_user(
                        $userto,
                        \core_user::get_noreply_user(),
                        get_string('email_review_needed_subject', 'moodleoverflow', $textcontext),
                        $OUTPUT->render_from_template('mod_moodleoverflow/email_review_needed_text', $textcontext),
                        $OUTPUT->render_from_template('mod_moodleoverflow/email_review_needed_html', $htmlcontext)
                    );
                } catch (exception $e) {
                    mtrace("Error sending review notification for post $post->id to user $userto->id!");
                }
            }
            $success[] = $post->id;
        }

        if (!empty($success)) {
            list($insql, $inparams) = $DB->get_in_or_equal($success);
            $DB->set_field_select(
                'moodleoverflow_posts', 'mailed', MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS,
                    'id ' . $insql, $inparams
                );
            mtrace('Sent review notifications for ' . count($success) . ' posts successfully!');
        }
    }

}

