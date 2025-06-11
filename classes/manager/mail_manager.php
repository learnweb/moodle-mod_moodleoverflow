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
use core\cron;
use core\message\message;
use core_php_time_limit;
use core_user;
use dml_exception;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\output\moodleoverflow_email;
use mod_moodleoverflow\subscriptions;
use moodle_exception;
use moodle_url;
use renderer_base;
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
     * This functions executes the task of sending a notification mail to users that are subscribed to a moodleoverflow.
     * This function does the following:
     * - Retrieve all posts that are unmailed and need to be send.
     *
     *
     * @return bool
     * @throws moodle_exception
     * @throws dml_exception
     */
    public static function moodleoverflow_send_mails(): bool {
        global $DB, $CFG, $PAGE;

        // Get the course object of the top level site.
        $site = get_site();

        // Get the main renderers.
        $htmlout = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'htmlemail');
        $textout = $PAGE->get_renderer('mod_moodleoverflow', 'email', 'textemail');

        // Posts older than x days will not be mailed.
        // This will avoid problems with the cron not being run for a long time.
        $timenow = time();
        $endtime = $timenow - get_config('moodleoverflow', 'maxeditingtime');
        $starttime = $endtime - (get_config('moodleoverflow', 'maxmailingtime') * 60 * 60);

        // Retrieve posts that need to be send to users.
        mtrace("Fetching records");
        if (!$records = self::moodleoverflow_get_unmailed_posts($starttime, $endtime)) {
            var_dump("No records found for mailing.");
            mtrace('No posts to be mailed.');
            return true;
        }

        // Mark those posts as mailed.
        // TODO: Set 0 to $endtime as soon this function is ready for work.
        if (!self::moodleoverflow_mark_old_posts_as_mailed( $endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;
        }

        // Start processing the records.

        // Build cache arrays for most important objects. All caches are structured with id => object.
        $posts = [];
        $authors = [];
        $recipients = [];
        $courses = [];
        $moodleoverflows = [];
        $discussions = [];
        $coursemodules = [];

        mtrace("Records found, start processing");

        // Loop through each records.
        foreach ($records as $record) {
            // Terminate if the process takes more time then two minutes.
            core_php_time_limit::raise(120);

            // Check if the user that is subscribed to the post wants a resume instead of a notification mail.
            if ($record->usertomaildigest != 0) {
                // Process the record for the mail digest.
                self::moodleoverflow_process_maildigest_record($record);
                continue;
            }

            // Fill the caches with objects if needed.
            // Add additional information that were not retrievable from the database to the objects if needed.
            self::moodleoverflow_update_mail_caches($record, $coursemodules, $courses, $moodleoverflows,
                $discussions, $posts, $authors, $recipients);

            // Set up the user that receives the mail.
            $CFG->branch >= 402 ? \core\cron::setup_user($recipients[$record->usertoid]) :
                                  cron_setup_user($recipients[$record->usertoid]);

            // Check if the user can see the post.
            if (!moodleoverflow_user_can_see_post($moodleoverflows[$record->moodleoverflowid], $discussions[$record->discussionid],
                $posts[$record->postid], $coursemodules[$record->cmid])) {
                mtrace('User ' . $record->usertoid . ' can not see ' . $record->postid . '. Not sending message.');
                continue;
            }

            // Determine if the author should be anonymous.
            $authoranonymous = match ($record->moodleoverflowanonymous) {
                anonymous::NOT_ANONYMOUS => false,
                anonymous::EVERYTHING_ANONYMOUS => true,
                anonymous::QUESTION_ANONYMOUS => ($record->discussionuserid == $record->authorid)
            };

            // Set the userfrom variable, that is anonymous or the post author.
            if ($authoranonymous) {
                $userfrom = core_user::get_noreply_user();
                $userfrom->anonymous = true;
            } else {
                $userfrom = clone($authors[$record->authorid]);
                $userfrom->anonymous = false;
            }

            // Cache the recipients capabilities to view full names for the moodleoverflow instance.
            if (!isset($recipients[$record->usertoid]->viewfullnames[$record->moodleoverflowid])) {
                // Find the context module.
                $modulecontext = context_module::instance($record->cmid);

                // Check the users capabilities.
                $recipients[$record->usertoid]->viewfullnames[$record->moodleoverflowid] =
                    has_capability('moodle/site:viewfullnames', $modulecontext, $record->usertoid);
            }

            // Cache the recipients capability to post in the discussion.
            if (!isset($recipients[$record->usertoid]->canpost[$record->discussionid])) {
                // Find the context module.
                $modulecontext = context_module::instance($record->cmid);

                // Check the users capabilities.
                $canreply = moodleoverflow_user_can_post($modulecontext, $posts[$record->postid], $record->usertoid);
                $recipients[$record->usertoid]->canpost[$record->discussionid] = $canreply;
            }

            // Preparation complete. Ready to send message.
            mtrace('Preparation complete. Build mail event');

            // Build up content of the mail.
            $cleanname = str_replace('"', "'", strip_tags(format_string($record->moodleoverflowname)));
            $shortname = format_string($record->courseshortname, true, ['context' => context_course::instance($record->courseid)]);

            // Define a header to make mails easier to track.
            $emailmessageid = generate_email_messageid('moodlemoodleoverflow' . $record->moodleoverflowid);
            $userfrom->customheaders = [
                'List-Id: "' . $cleanname . '" ' . $emailmessageid,
                'List-Help: ' . $CFG->wwwroot . '/mod/moodleoverflow/view.php?m=' . $record->moodleoverflowid,
                'Message-ID: ' . generate_email_messageid(hash('sha256', $record->postid . 'to' . $record->usertoid)),
                'X-Course-Id: ' . $record->courseid,
                'X-Course-Name: ' . format_string($record->coursefullname),
                'Precedence: Bulk',
                'X-Auto-Response-Suppress: All',
                'Auto-Submitted: auto-generated',
            ];

            // Build the mail object.
            $email = new moodleoverflow_email(
                $courses[$record->courseid],
                $coursemodules[$record->moodleoverflowid],
                $moodleoverflows[$record->moodleoverflowid],
                $discussions[$record->discussionid],
                $posts[$record->postid],
                $userfrom,
                $recipients[$record->usertoid],
                $recipients[$record->usertoid]->canpost[$record->discussionid]
            );

            // TODO: check if this is needed.
            $email->viewfullnames = $recipients[$record->usertoid]->viewfullnames[$record->moodleoverflowid];

            // The email object is build. Now build all data that is needed for the event that really send the mail.
            $userfrom->customheader[] = sprintf('List-Unsubscribe: <%s>', $email->get_unsubscribediscussionlink());
            if ($record->postparent != 0) {
                $parentid = generate_email_messageid(hash('sha256', $record->postparent . 'to' . $record->usertoid));
                $rootid = generate_email_messageid(hash('sha256', $record->discussionfirstpost . 'to' . $record->usertoid));
                $userfrom->customheaders[] = "In-Reply-To: $parentid";

                if ($record->postparent != $record->discussionfirstpost) {
                    $userfrom->customheaders[] = "References: $rootid $parentid";
                } else {
                    $userfrom->customheaders[] = "References: $parentid";
                }
            }

            // Build post subject for mail event.
            $postsubject = (object) ['subject' => $email->get_subject(),  'moodleoverflowname' => $cleanname,
                'sitefullname' => format_string($site->fullname), 'siteshortname' => format_string($site->shortname),
                'courseidnumber' => $email->get_courseidnumber(), 'coursefullname' => $email->get_coursefullname(),
                'courseshortname' => $email->get_coursename(),
            ];

            // Build small message of the mail.
            $smallmessage = (object) [
                'user' => fullname($userfrom),
                'moodleoverflowname' => "$shortname: " . format_string($record->moodleoverflowname) . ": "
                                        . $record->discussionname,
                'message' => $record->postmessage,
            ];

            mtrace('Preparation complete. Sending mail to user ' . $record->usertoid . ' for post ' . $record->postid);

            // Create the message event.
            $emailmessage = new message();
            $emailmessage->courseid = $record->courseid;
            $emailmessage->component = 'mod_moodleoverflow';
            $emailmessage->name = 'posts';
            $emailmessage->userfrom = $userfrom;
            $emailmessage->userto = $recipients[$record->usertoid];
            $emailmessage->subject = html_to_text(get_string('postmailsubject', 'moodleoverflow', $postsubject), 0);
            $emailmessage->fullmessage = $textout->render($email);
            $emailmessage->fullmessageformat = FORMAT_PLAIN;
            $emailmessage->fullmessagehtml = $htmlout->render($email);
            $emailmessage->notification = 1;
            $emailmessage->contexturl = new moodle_url('/mod/moodleoverflow/discussion.php',
                                                        ['d' => $record->discussionid], 'p' . $record->postid);
            $emailmessage->contexturlname = $record->discussionname;
            $emailmessage->smallmessage = get_string_manager()->get_string('smallmessage', 'moodleoverflow',
                                                                                    $smallmessage, $record->usertolang);

            // Finally: send the notification mail.
            var_dump("Finally");
            $mailsent = message_send($emailmessage);

            // Check if an error occurred and mark the post as mailed_error.
            if (!$mailsent) {
                mtrace('Error: mod/moodleoverflow/classes/manager/mail_manager.php moodleoverflow_send_mails(): ' .
                    'Could not send out mail for id $record->postid to user $record->usertoid ($record->usertoemail).' .
                    ' ... not trying again.');
                $DB->set_field('moodleoverflow_posts', 'mailed', MOODLEOVERFLOW_MAILED_ERROR, ['id' => $record->postid]);
            } else {
                mtrace('Mail sent successfully for post ' . $record->postid . ' to user ' . $record->usertoid);
            }
        }

        // The task is completed.
        return true;
    }


    /**
     * Return a list of records that will be mailed. One record has all the information that is needed. This includes:
     * - The post, discussion, moodleoverflow data of a post that is unmailed
     * - The data of the post author
     * - The data of the user, that is subscribed to the moodleoverflow discussion, that has the unmailed post
     *
     * The same post and user can be found redundantly, because one posts is  mailed to many user and one user gets notified about
     * many posts. Because all data is in one table, every record represents one mail.
     *
     * @param int $starttime posts created after this time
     * @param int $endtime posts created before this time
     *
     * @return array
     * @throws dml_exception
     */
    public static function moodleoverflow_get_unmailed_posts($starttime, $endtime): array {
        global $DB;

        // Define fields that will be get from the database.
        $postfields = "p.id AS postid, p.message AS postmessage, p.messageformat as postmessageformat, p.modified as postmodified,
                       p.parent AS postparent, p.userid AS postuserid, p.reviewed AS postreviewed";
        $discussionfields = "d.id AS discussionid, d.name AS discussionname, d.userid AS discussionuserid,
                             d.firstpost AS discussionfirstpost";
        $moodleoverflowfields = "mo.id AS moodleoverflowid, mo.name AS moodleoverflowname, mo.anonymous AS moodleoverflowanonymous,
                                 mo.forcesubscribe AS moodleoverflowforcesubscribe";
        $coursefields = "c.id AS courseid, c.idnumber AS courseidnumber, c.fullname AS coursefullname,
                         c.shortname AS courseshortname";
        $cmfields = "cm.id AS cmid, cm.groupingid AS cmgroupingid";
        $authorfields = "author.id AS authorid, author.firstname AS authorfirstname, author.lastname AS authorlastname,
                         author.firstnamephonetic AS authorfirstnamephonetic, author.lastnamephonetic AS authorlastnamephonetic,
                         author.middlename AS authormiddlename, author.alternatename AS authoralternatename,
                         author.picture AS authorpicture, author.imagealt AS authorimagealt, author.email AS authoremail";
        $usertofields = "userto.id AS usertoid, userto.maildigest AS usertomaildigest, userto.description AS usertodescription,
                         userto.password AS usertopassword, userto.lang AS usertolang, userto.auth AS usertoauth,
                         userto.suspended AS usertosuspended, userto.deleted AS usertodeleted, userto.emailstop AS usertoemailstop";

        $fields = "(ROW_NUMBER() OVER (ORDER BY p.modified)) AS row_num, " . $postfields . ", " . $discussionfields . ", "
                    . $moodleoverflowfields . ", " . $coursefields . ", " . $cmfields . ", " . $authorfields . ", " . $usertofields;

        // Set params for the sql query.
        $params = [];
        $params['unsubscribed'] = subscriptions::MOODLEOVERFLOW_DISCUSSION_UNSUBSCRIBED;
        $params['pendingmail'] = self::MOODLEOVERFLOW_MAILED_PENDING;
        $params['reviewsent'] = self::MOODLEOVERFLOW_MAILED_REVIEW_SUCCESS;
        $params['ptimestart'] = $starttime;
        $params['ptimeend'] = $endtime;

        // Retrieve the records.
        $sql = "SELECT $fields
                FROM {moodleoverflow_posts} p
                JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                JOIN {moodleoverflow} mo ON mo.id = d.moodleoverflow
                JOIN {course} c ON c.id = mo.course
                JOIN (
                    SELECT cm.id, cm.groupingid, cm.instance
                    FROM {course_modules} cm
                    JOIN {modules} md ON md.id = cm.module
                    WHERE md.name = 'moodleoverflow'
                ) cm ON cm.instance = mo.id
                JOIN {user} author ON author.id = p.userid
                JOIN (
                    SELECT *
                    FROM (
                        SELECT userid, moodleoverflow, -1 as discussion
                        FROM {moodleoverflow_subscriptions} s
                        UNION
                        SELECT userid, moodleoverflow, discussion
                        FROM {moodleoverflow_discuss_subs} ds
                        WHERE ds.preference <> :unsubscribed
                    ) as subscriptions
                    LEFT JOIN {user} u ON u.id = subscriptions.userid
                    ORDER BY u.email ASC
                ) userto ON ((d.moodleoverflow = userto.moodleoverflow) AND
                                      ((p.discussion = userto.discussion) OR
                                      (userto.discussion = -1))
                                     )
                WHERE p.mailed IN (:pendingmail, :reviewsent) AND p.reviewed = 1
                AND COALESCE(p.timereviewed, p.created) >= :ptimestart AND p.created < :ptimeend
                AND author.id <> userto.id";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fills and updates cache arrays with data from a record object.
     * This function checks if specific data (course modules, courses, moodleoverflows, discussions, posts, authors, recipients)
     * is already cached. If not, it creates an object with the relevant data from the provided record and stores it in the cache.
     *
     * @param object $record            The record containing data to be cached.
     * @param array  $coursemodules    Cache for course module data, indexed by course module ID.
     * @param array  $courses          Cache for course data, indexed by course ID.
     * @param array  $moodleoverflows  Cache for moodleoverflow data, indexed by moodleoverflow ID.
     * @param array  $discussions      Cache for discussion data, indexed by discussion ID.
     * @param array  $posts            Cache for post data, indexed by post ID.
     * @param array  $authors          Cache for author data, indexed by author ID.
     * @param array  $recipients       Cache for recipient data, indexed by recipient ID.
     *
     * @return void
     */
    public static function moodleoverflow_update_mail_caches(object $record, array &$coursemodules, array &$courses,
                                                             array &$moodleoverflows, array  &$discussions, array &$posts,
                                                             array &$authors, array &$recipients ): void {
        // TODO: Check this simplified version.
        // Define cache types and their corresponding record properties.
        $cachetypes = [
            'coursemodules' => ['id' => 'cmid', 'groupingid' => 'cmgroupingid'],
            'courses' => ['id' => 'courseid', 'idnumber' => 'courseidnumber', 'fullname' => 'coursefullname',
                          'shortname' => 'courseshortname'],
            'moodleoverflows' => ['id' => 'moodleoverflowid', 'name' => 'moodleoverflowname',
                                  'anonymous' => 'moodleoverflowanonymous', 'forcesubscribe' => 'moodleoverflowforcesubscribe'],
            'discussions' => ['id' => 'discussionid', 'name' => 'discussionname', 'userid' => 'discussionuserid',
                              'firstpost' => 'discussionfirstpost'],
            'posts' => ['id' => 'postid', 'message' => 'postmessage', 'messageformat' => 'postmessageformat',
                        'modified' => 'postmodified', 'parent' => 'postparent', 'userid' => 'postuserid',
                        'reviewed' => 'postreviewed'],
            'authors' => ['id' => 'authorid', 'firstname' => 'authorfirstname', 'lastname' => 'authorlastname',
                          'firstnamephonetic' => 'authorfirstnamephonetic', 'lastnamephonetic' => 'authorlastnamephonetic',
                          'middlename' => 'authormiddlename', 'alternatename' => 'authoralternatename',
                          'picture' => 'authorpicture', 'imagealt' => 'authorimagealt', 'email' => 'authoremail'],
            'recipients' => ['id' => 'usertoid', 'description' => 'usertodescription', 'password' => 'usertopassword',
                             'lang' => 'usertolang', 'auth' => 'usertoauth', 'suspended' => 'usertosuspended',
                             'deleted' => 'usertodeleted', 'emailstop' => 'usertoemailstop', 'viewfullnames' => [],
                             'canpost' => []],
        ];

        // Iterate over cache types and update caches if not already set.
        foreach ($cachetypes as $cachename => $properties) {
            $cachekey = $record->{$properties['id']};
            if (!isset(${$cachename}[$cachekey])) {
                ${$cachename}[$cachekey] = (object) array_map(fn($prop) => $record->{$prop}, $properties);
            }
        }
    }

    /**
     * Function that processes a record from self::moodleoverflow_get_unmailed_posts() if the user that gets the mail wants a
     * resume instead of a mail for every post.
     *
     * @param object $record Record from self::moodleoverflow_get_unmailed_posts()
     * @return void
     */
    public static function moodleoverflow_process_maildigest_record($record) {

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
}
