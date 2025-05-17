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
 * Moodleoverflow mail manager.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Nina Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\manager;

use context_course;
use context_module;
use core\context\course;
use core_php_time_limit;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\output\moodleoverflow_email;
use mod_moodleoverflow\subscriptions;
use stdClass;

/**
 * Moodleoverflow mail manager.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Nina Herrmann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mail_manager {
    // Mailing state constants.
    /**
     * Mail is pending.
     */
    const MOODLEOVERFLOW_MAILED_PENDING = 0;
    /**
     * Sucessfully send.
     */
    const MOODLEOVERFLOW_MAILED_SUCCESS = 1;
    /**
     * Error occurred.
     */
    const MOODLEOVERFLOW_MAILED_ERROR = 2;
    /**
     * Mail successfully reviewed.
     */
    const MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS = 3;

    /**
     * Sends mail notifications about new posts.
     *
     * @return bool
     */
    public static function moodleoverflow_send_mails(): bool {
        global $CFG, $DB, $PAGE;

        // Get the course object of the top level site.
        $site = get_site();

        // Get the main renderers.
        $htmlout = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'htmlemail');
        $textout = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'textemail');

        // Initiate the arrays that are saving the users that are subscribed to posts that needs sending.
        $users = [];

        // Status arrays.
        $mailcount = [];
        $errorcount = [];

        // Cache arrays.
        $discussions = [];
        $moodleoverflows = [];
        $courses = [];
        $coursemodules = [];

        // Posts older than x days will not be mailed. This will avoid problems with the cron not ran for a long time.
        $timenow = time();
        $endtime = $timenow - get_config('moodleoverflow', 'maxeditingtime');
        $starttime = $endtime - (get_config('moodleoverflow', 'maxmailingtime') * 60 * 60);

        // Retrieve all unmailed posts.
        $posts = self::moodleoverflow_get_unmailed_posts($starttime, $endtime);
        if ($posts) {
            // Mark those posts as mailed.
            if (!self::moodleoverflow_mark_old_posts_as_mailed($endtime)) {
                mtrace('Errors occurred while trying to mark some posts as being mailed.');
                return false;
            }
            // Loop through all posts to be mailed.
            foreach ($posts as $postid => $post) {
                self::check_post($post, $mailcount, $users, $discussions, $errorcount, $posts, $postid,
                   $moodleoverflows, $courses, $coursemodules);
            }
        }

        // Send mails to the users with information about the posts.
        if ($users && $posts) {
            // Send one mail to every user.
            foreach ($users as $userto) {
                // Terminate if the process takes more time then two minutes.
                core_php_time_limit::raise(120);

                // Tracing information.
                mtrace('Processing user ' . $userto->id);
                // Initiate the user caches to save memory.
                $userto = clone($userto);
                $userto->ciewfullnames = [];
                $userto->canpost = [];
                $userto->markposts = [];

                // Cache the capabilities of the user.
                $CFG->branch >= 402 ? \core\cron::setup_user($userto) : cron_setup_user($userto);

                // Reset the caches.
                foreach ($coursemodules as $moodleoverflowid) {
                    $moodleoverflowid->cache = new stdClass();
                    $moodleoverflowid->cache->caps = [];
                    unset($moodleoverflowid->uservisible);
                }

                // Loop through all posts of this users.
                foreach ($posts as $post) {
                    self::send_post($userto, $post, $coursemodules, $errorcount,
                        $discussions, $moodleoverflows, $courses, $mailcount, $users, $site, $textout, $htmlout);
                }

                // Release the memory.
                unset($userto);
            }
        }

        // Check for all posts whether errors occurred.
        foreach ($posts as $post) {
            // Tracing information.
            mtrace($mailcount[$post->id] . " users were sent post $post->id");

            // Mark the posts with errors in the database.
            if ($errorcount[$post->id]) {
                $DB->set_field('moodleoverflow_posts', 'mailed', self::MOODLEOVERFLOW_MAILED_ERROR, ['id' => $post->id]);
            }
        }

        // The task was completed.
        return true;
    }

    /**
     * Returns a list of all posts that have not been mailed yet.
     *
     * @param int $starttime posts created after this time
     * @param int $endtime   posts created before this time
     *
     * @return array
     */
    public static function moodleoverflow_get_unmailed_posts($starttime, $endtime) {
        global $DB;

        // Set params for the sql query.
        $params = [];
        $params['ptimestart'] = $starttime;
        $params['ptimeend'] = $endtime;

        $pendingmail = self::MOODLEOVERFLOW_MAILED_PENDING;
        $reviewsent = self::MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS;

        // Retrieve the records.
        $sql = "SELECT p.*, d.course, d.moodleoverflow
            FROM {moodleoverflow_posts} p
            JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
            WHERE p.mailed IN ($pendingmail, $reviewsent) AND p.reviewed = 1
            AND COALESCE(p.timereviewed, p.created) >= :ptimestart AND p.created < :ptimeend
            ORDER BY p.modified ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Marks posts before a certain time as being mailed already.
     *
     * @param int $endtime
     *
     * @return bool
     */
    public static function moodleoverflow_mark_old_posts_as_mailed($endtime) {
        global $DB;

        // Get the current timestamp.
        $now = time();

        // Define variables for the sql query.
        $params = [];
        $params['mailedsuccess'] = self::MOODLEOVERFLOW_MAILED_SUCCESS;
        $params['mailedreviewsent'] = self::MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS;
        $params['now'] = $now;
        $params['endtime'] = $endtime;
        $params['mailedpending'] = self::MOODLEOVERFLOW_MAILED_PENDING;

        // Define the sql query.
        $sql = "UPDATE {moodleoverflow_posts}
            SET mailed = :mailedsuccess
            WHERE (created < :endtime) AND mailed IN (:mailedpending, :mailedreviewsent) AND reviewed = 1";

        return $DB->execute($sql, $params);

    }
    /**
     * Removes unnecessary information from the user records for the mail generation.
     *
     * @param stdClass $user
     */
    public static function moodleoverflow_minimise_user_record(stdClass $user) {
        // Remove all information for the mail generation that are not needed.
        unset($user->institution);
        unset($user->department);
        unset($user->address);
        unset($user->city);
        unset($user->url);
        unset($user->currentlogin);
        unset($user->description);
        unset($user->descriptionformat);
    }

    /**
     * Check for a single post if the mail should be send. This includes:
     * 1) Does a) the moodleoverflow
     *         b) moodleoverflow discussion
     *         c) course module
     *          still exists?
     * 2) Is the user subscriped?
     * @param stdClass $post
     * @param array $mailcount
     * @param array $users
     * @param array $discussions
     * @param array $errorcount
     * @param array $posts
     * @param int $postid
     * @param array $moodleoverflows
     * @param array $courses
     * @param array $coursemodules
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private static function check_post($post, array &$mailcount, array &$users, array &$discussions, array &$errorcount,
                                       array &$posts, int $postid, array &$moodleoverflows, array &$courses,
                                       array &$coursemodules) {
        // Check the cache if the discussion exists.
        $discussionid = $post->discussion;
        if (!self::cache_record('moodleoverflow_discussions', $discussionid, $discussions,
                                'Could not find discussion ', $posts, $postid, true)) {
            return;
        }

        // Retrieve the connected moodleoverflow instance from the database.
        $moodleoverflowid = $discussions[$discussionid]->moodleoverflow;
        if (!self::cache_record('moodleoverflow', $moodleoverflowid, $moodleoverflows,
                          'Could not find moodleoverflow ', $posts, $postid, false)) {
            return;
        }

        // Retrieve the connected courses from the database.
        $courseid = $moodleoverflows[$moodleoverflowid]->course;
        if (!self::cache_record('course', $courseid, $courses,
                                'Could not find course ', $posts, $postid, false)) {
            return;
        }

        // Retrieve the connected course modules from the database.
        if (!isset($coursemodules[$moodleoverflowid])) {
            // Retrieve the coursemodule and update the cache.
            if ($cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflowid, $courseid)) {
                $coursemodules[$moodleoverflowid] = $cm;
            } else {
                mtrace('Could not find course module for moodleoverflow ' . $moodleoverflowid);
                unset($posts[$postid]);
                return;
            }
        }

        // Cache subscribed users of each moodleoverflow.
        if (!isset($subscribedusers[$moodleoverflowid])) {
            // Retrieve the context module.
            $modulecontext = context_module::instance($coursemodules[$moodleoverflowid]->id);

            // Retrieve all subscribed users.
            $mid = $moodleoverflows[$moodleoverflowid];
            if ($subusers = subscriptions::get_subscribed_users($mid, $modulecontext, 'u.*', true)) {
                // Loop through all subscribed users.
                foreach ($subusers as $postuser) {
                    // Save the user into the cache.
                    $subscribedusers[$moodleoverflowid][$postuser->id] = $postuser->id;
                    self::moodleoverflow_minimise_user_record($postuser);
                    self::moodleoverflow_minimise_user_record($postuser);
                    $users[$postuser->id] = $postuser;
                }

                // Release the memory.
                unset($subusers);
                unset($postuser);
            }
        }

        // Initiate the count of the mails send and errors.
        $mailcount[$postid] = 0;
        $errorcount[$postid] = 0;
    }

    /**
     * Helper function for check_post(). Caches the a record exists in the database and caches the record if needed.
     * @param string $table
     * @param int $id
     * @param array $cache
     * @param string $errormessage
     * @param array $posts
     * @param int $postid
     * @param bool $fillsubscache    If the subscription cache is being filled (only when checking discussion cache)
     * @return bool
     * @throws \dml_exception
     */
    private static function cache_record($table, $id, &$cache, $errormessage, &$posts, $postid, $fillsubscache) {
        global $DB;
        // Check if cache if an record exists already in the cache.
        if (!isset($cache[$id])) {
            // If there is a record in the database, update the cache. Else ignore the post.
            if ($record = $DB->get_record($table, ['id' => $id])) {
                $cache[$id] = $record;
                if ($fillsubscache) {
                    subscriptions::fill_subscription_cache($record->moodleoverflow);
                    subscriptions::fill_discussion_subscription_cache($record->moodleoverflow);
                }
            } else {
                mtrace($errormessage . $id);
                unset($posts[$postid]);
                return false;
            }
        }
        return true;
    }


    /**
     * Send the Mail with information of the post depending on theinformation available.
     * E.g. anonymous post do not include names, users who want resumes do not get single mails.
     * @param stdClass $userto
     * @param stdClass $post
     * @param array $coursemodules
     * @param array $errorcount
     * @param array $discussions
     * @param array $moodleoverflows
     * @param array $courses
     * @param array $mailcount
     * @param array $users
     * @param stdClass $site
     * @param stdClass $textout
     * @param stdClass $htmlout
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private static function send_post($userto, $post, array &$coursemodules, array &$errorcount,
                                      array &$discussions, array &$moodleoverflows, array &$courses, array &$mailcount,
                                      array &$users, $site, $textout, $htmlout) {
        global $DB, $CFG;

        // Initiate variables for the post.
        $discussion = $discussions[$post->discussion];
        $moodleoverflow = $moodleoverflows[$discussion->moodleoverflow];
        $course = $courses[$moodleoverflow->course];
        $cm             =& $coursemodules[$moodleoverflow->id];
        $modulecontext = context_module::instance($cm->id);

        // Check if user wants a resume.
        // in this case: make a new dataset in "moodleoverflow_mail_info" to save the posts data.
        // Dataset from moodleoverflow_mail_info will be send later in a mail.
        $usermailsetting = $userto->maildigest;
        if ($usermailsetting != 0) {
            $dataobject = new stdClass();
            $dataobject->userid = $userto->id;
            $dataobject->courseid = $course->id;
            $dataobject->forumid = $moodleoverflow->id;
            $dataobject->forumdiscussionid = $discussion->id;
            $record = $DB->get_record('moodleoverflow_mail_info',
                ['userid' => $dataobject->userid,
                    'courseid' => $dataobject->courseid,
                    'forumid' => $dataobject->forumid,
                    'forumdiscussionid' => $dataobject->forumdiscussionid, ],
                'numberofposts, id');
            if (is_object($record)) {
                $dataset = $record;
                $dataobject->numberofposts = $dataset->numberofposts + 1;
                $dataobject->id = $dataset->id;
                $DB->update_record('moodleoverflow_mail_info', $dataobject);
            } else {
                $dataobject->numberofposts = 1;
                $DB->insert_record('moodleoverflow_mail_info', $dataobject);
            }
            return;
        }

        // Check whether the user is subscribed.
        if (!isset($subscribedusers[$moodleoverflow->id][$userto->id])) {
            return;
        }

        // Check whether the user is subscribed to the discussion.
        $uid = $userto->id;
        if (!subscriptions::is_subscribed($uid, $moodleoverflow, $modulecontext, $post->discussion)) {
            return;
        }

        // Check whether the user unsubscribed to the discussion after it was created.
        $subnow = subscriptions::fetch_discussion_subscription($moodleoverflow->id, $userto->id);
        if ($subnow && isset($subnow[$post->discussion]) && ($subnow[$post->discussion] > $post->created)) {
            return;
        }

        if (anonymous::is_post_anonymous($discussion, $moodleoverflow, $post->userid)) {
            $userfrom = \core_user::get_noreply_user();
        } else {
            // Check whether the sending user is cached already.
            if (array_key_exists($post->userid, $users)) {
                $userfrom = $users[$post->userid];
            } else {
                // We dont know the the user yet.

                // Retrieve the user from the database.
                $userfrom = $DB->get_record('user', ['id' => $post->userid]);
                if ($userfrom) {
                    self::moodleoverflow_minimise_user_record($userfrom);
                } else {
                    $uid = $post->userid;
                    $pid = $post->id;
                    mtrace('Could not find user ' . $uid . ', author of post ' . $pid . '. Unable to send message.');
                    return;
                }
            }
        }

        // Setup roles and languages.
        $CFG->branch >= 402 ? \core\cron::setup_user($userto, $course) : cron_setup_user($userto, $course);

        // Cache the users capability to view full names.
        if (!isset($userto->viewfullnames[$moodleoverflow->id])) {

            // Find the context module.
            $modulecontext = context_module::instance($cm->id);

            // Check the users capabilities.
            $userto->viewfullnames[$moodleoverflow->id] = has_capability('moodle/site:viewfullnames', $modulecontext);
        }

        // Cache the users capability to post in the discussion.
        if (!isset($userto->canpost[$discussion->id])) {

            // Find the context module.
            $modulecontext = context_module::instance($cm->id);

            // Check the users capabilities.
            $canpost = moodleoverflow_user_can_post($modulecontext, $post, $userto->id);
            $userto->canpost[$discussion->id] = $canpost;
        }

        // Make sure the current user is allowed to see the post.
        if (!moodleoverflow_user_can_see_post($moodleoverflow, $discussion, $post, $cm)) {
            mtrace('User ' . $userto->id . ' can not see ' . $post->id . '. Not sending message.');
            return;
        }

        // Sent the email.

        // Preapare to actually send the post now. Build up the content.
        $cleanname = str_replace('"', "'", strip_tags(format_string($moodleoverflow->name)));
        $shortname = format_string($course->shortname, true, ['context' => context_course::instance($course->id)]);

        // Define a header to make mails easier to track.
        $emailmessageid = generate_email_messageid('moodlemoodleoverflow' . $moodleoverflow->id);
        $userfrom->customheaders = [
            'List-Id: "' . $cleanname . '" ' . $emailmessageid,
            'List-Help: ' . $CFG->wwwroot . '/mod/moodleoverflow/view.php?m=' . $moodleoverflow->id,
            'Message-ID: ' . generate_email_messageid(hash('sha256', $post->id . 'to' . $userto->id)),
            'X-Course-Id: ' . $course->id,
            'X-Course-Name: ' . format_string($course->fullname, true),

            // Headers to help prevent auto-responders.
            'Precedence: Bulk',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
        ];

        // Cache the users capabilities.
        if (!isset($userto->canpost[$discussion->id])) {
            $canreply = moodleoverflow_user_can_post($modulecontext, $post, $userto->id);
        } else {
            $canreply = $userto->canpost[$discussion->id];
        }

        // Format the data.
        $data = new moodleoverflow_email($course, $cm, $moodleoverflow, $discussion, $post, $userfrom, $userto, $canreply);

        // Retrieve the unsubscribe-link.
        $userfrom->customheaders[] = sprintf('List-Unsubscribe: <%s>', $data->get_unsubscribediscussionlink());

        // Check the capabilities to view full names.
        if (!isset($userto->viewfullnames[$moodleoverflow->id])) {
            $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modulecontext, $userto->id);
        } else {
            $data->viewfullnames = $userto->viewfullnames[$moodleoverflow->id];
        }

        // Retrieve needed variables for the mail.
        $var = new stdClass();
        $var->subject = $data->get_subject();
        $var->moodleoverflowname = $cleanname;
        $var->sitefullname = format_string($site->fullname);
        $var->siteshortname = format_string($site->shortname);
        $var->courseidnumber = $data->get_courseidnumber();
        $var->coursefullname = $data->get_coursefullname();
        $var->courseshortname = $data->get_coursename();
        $postsubject = html_to_text(get_string('postmailsubject', 'moodleoverflow', $var), 0);
        $rootid = generate_email_messageid(hash('sha256', $discussion->firstpost . 'to' . $userto->id));

        // Check whether the post is a reply.
        if ($post->parent) {
            // Add a reply header.
            $parentid = generate_email_messageid(hash('sha256', $post->parent . 'to' . $userto->id));
            $userfrom->customheaders[] = "In-Reply-To: $parentid";

            // Comments need a reference to the starting post as well.
            if ($post->parent != $discussion->firstpost) {
                $userfrom->customheaders[] = "References: $rootid $parentid";
            } else {
                $userfrom->customheaders[] = "References: $parentid";
            }
        }

        // Send the post now.
        mtrace('Sending ', '');

        // Create the message event.
        $eventdata = new \core\message\message();
        $eventdata->courseid = $course->id;
        $eventdata->component = 'mod_moodleoverflow';
        $eventdata->name = 'posts';
        $eventdata->userfrom = $userfrom;
        $eventdata->userto = $userto;
        $eventdata->subject = $postsubject;
        $eventdata->fullmessage = $textout->render($data);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $htmlout->render($data);
        $eventdata->notification = 1;

        // Initiate another message array.
        $small = new stdClass();
        $small->user = fullname($userfrom);
        $formatedstring = format_string($moodleoverflow->name, true);
        $small->moodleoverflowname = "$shortname: " . $formatedstring . ": " . $discussion->name;
        $small->message = $post->message;

        // Make sure the language is correct.
        $usertol = $userto->lang;
        $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'moodleoverflow', $small, $usertol);

        // Generate the url to view the post.
        $url = '/mod/moodleoverflow/discussion.php';
        $params = ['d' => $discussion->id];
        $contexturl = new moodle_url($url, $params, 'p' . $post->id);
        $eventdata->contexturl = $contexturl->out();
        $eventdata->contexturlname = $discussion->name;

        // Actually send the message.
        $mailsent = message_send($eventdata);

        // Check whether the sending failed.
        if (!$mailsent) {
            mtrace('Error: mod/moodleoverflow/classes/task/send_mail.php execute(): ' .
                "Could not send out mail for id $post->id to user $userto->id ($userto->email) .. not trying again.");
            $errorcount[$post->id]++;
        } else {
            $mailcount[$post->id]++;
        }

        // Tracing message.
        mtrace('post ' . $post->id . ': ' . $discussion->name);
    }

}
