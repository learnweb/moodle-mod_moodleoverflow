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
 * Class that is important to interact with posts.
 *
 * @package     mod_moodleoverflow
 * @copyright   2023 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_moodleoverflow\post;

// Import namespace from the locallib, needs a check later which namespaces are really needed.
use coding_exception;
use core\notification;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\event\discussion_created;
use mod_moodleoverflow\event\post_created;
use mod_moodleoverflow\event\post_updated;
use mod_moodleoverflow\review;

use mod_moodleoverflow\post\post;
use mod_moodleoverflow\discussion\discussion;
use mod_moodleoverflow\subscriptions;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * This Class controls the manipulation of posts and acts as controller of interactions with the post.php
 *
 * This Class has 2 main Tasks:
 * 1. Before entering the post.php
 * - Detect the wanted interaction (new discussion, new answer in a discussion, editing or deleting a post)
 * - make capability and other security/integrity checks (are all given data correct?)
 * - gather important information that need to be used later.
 * Note: if a post is being deleted, the post_control deletes it in the first step and the post.php does not call the post_form.php
 *
 * Now the post.php calls the post_form, so that the user can enter a message and attachments.
 *
 * 2. After calling the post_form:
 * - collect the information from the post_form
 * - based on the interaction, call the right function
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_control {

    /** @var string the Interaction type, the interactions are:
     * - create (creates a new discussion with a first post)
     * - reply (replies to a existing post, can be an answer or a comment)
     * - edit (change the content of an existing post)
     * - delete (delete a post from a discussion)
     */
    private $interaction;

    /** @var object information about the post like the related moodleoverflow, post etc.
     * Difference between info and prepost: Info has objects, prepost mostly ID's and string like the message of the post.
     */
    private $info;

    /** @var object prepost for the classes/post/post_form.php,
     * this object is more like a prototype of a post and it's not in the database*
     * Difference between info and prepost. Info has objects, prepost mostly ID's and strings like the message of the post.
     */
    private $prepost;

    /**
     * Constructor
     */
    public function __construct() {
        $this->info = new stdClass();
    }

    /**
     * Detects the interaction and builds the prepost.
     * @param stdClass $urlparameter parameter from the post.php
     * @throws coding_exception
     * @throws moodle_exception if the interaction is not correct.
     */
    public function detect_interaction($urlparameter) {
        $count = 0;
        $count += $urlparameter->create ? 1 : 0;
        $count += $urlparameter->reply ? 1 : 0;
        $count += $urlparameter->edit ? 1 : 0;
        $count += $urlparameter->delete ? 1 : 0;
        if ($count !== 1) {
            throw new moodle_exception('wrongparametercount', 'moodleoverflow');
        }

        if ($urlparameter->create) {
            $this->interaction = 'create';
            $this->info->moodleoverflowid = $urlparameter->create;
            $this->build_prepost_create($this->info->moodleoverflowid);

        } else if ($urlparameter->edit) {
            $this->interaction = 'edit';
            $this->info->editpostid = $urlparameter->edit;
            $this->build_prepost_edit($this->info->editpostid);

        } else if ($urlparameter->reply) {
            $this->interaction = 'reply';
            $this->info->replypostid = $urlparameter->reply;
            $this->build_prepost_reply($this->info->replypostid);

        } else if ($urlparameter->delete) {
            $this->interaction = 'delete';
            $this->info->deletepostid = $urlparameter->delete;
            $this->build_prepost_delete($this->info->deletepostid);
        }
    }

    /**
     * Controls the execution of an interaction.
     * @param object $form The results from the post_form.
     */
    public function execute_interaction($form) {
        global $CFG, $SESSION;
        // Redirect url in case of occurring errors.
        $SESSION->errorreturnurl = new moodle_url('/mod/moodleoverflow/view.php?', ['m' => $this->prepost->moodleoverflowid]);

        // Format the submitted data.
        $this->prepost->messageformat = $form->message['format'];
        $this->prepost->formattachments = $form->attachments;
        $this->prepost->message = $form->message['text'];
        $this->prepost->messagetrust = trusttext_trusted($this->prepost->modulecontext);

        // Get the current time.
        $this->prepost->timenow = time();

        // Execute the right function.
        if ($this->interaction == 'create' && $form->moodleoverflow == $this->prepost->moodleoverflowid) {
            $this->execute_create($form);
        } else if ($this->interaction == 'reply' && $form->reply == $this->prepost->parentid) {
            $this->execute_reply($form);
        } else if ($this->interaction == 'edit' && $form->edit == $this->prepost->postid) {
            $this->execute_edit($form);
        } else {
            throw new coding_exception(get_string('errorunexpectedinteraction', 'moodleoverflow'));
        }
    }

    /**
     * This function is used when a guest enters the post.php.
     * Parameters will be checked so that the post.php can redirect the user to the right site.
     * @param int $postid
     * @param int $moodleoverflowid
     * @return object $this->information // The gathered information.
     */
    public function catch_guest($postid = false, $moodleoverflowid = false) {
        global $PAGE;
        if ((!$postid && !$moodleoverflowid) || ($postid && $moodleoverflowid)) {
            throw new coding_exception('inaccurateparameter', 'moodleoverflow');
        }
        if ($postid) {
            $this->collect_information($postid, false);
        } else if ($moodleoverflowid) {
            $this->collect_information(false, $moodleoverflowid);
        }
        $this->info->modulecontext = \context_module::instance($this->info->cm->id);

        // Set the parameters for the page.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);
        $PAGE->set_context($this->info->modulecontext);
        $PAGE->set_title($this->info->course->shortname);
        $PAGE->set_heading($this->info->course->fullname);
        $PAGE->add_body_class('limitedwidth');
        return $this->info;
    }

    // Build functions, that build the prepost object for further use.

    /**
     * Function to prepare a new discussion in moodleoverflow.
     *
     * @param int $moodleoverflowid     The ID of the moodleoverflow where the new discussion post is being created.
     */
    private function build_prepost_create($moodleoverflowid) {
        global $DB, $SESSION, $USER;

        // Get the related moodleoverflow, course coursemodule and the contexts.
        $this->collect_information(false, $moodleoverflowid);

        // Check if the user can start a new discussion.
        if (!$this->check_user_can_create_discussion()) {

            // Catch unenrolled user.
            if (!isguestuser() && !is_enrolled($this->info->coursecontext)) {
                $SESSION->errorreturnurl = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $this->info->moodleoverflow->id]);
                if (enrol_selfenrol_available($this->info->course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php',  ['id' => $this->info->course->id,
                                             'returnurl' => '/mod/moodleoverflow/view.php?m=' . $this->info->moodleoverflow->id, ]),
                                             get_string('youneedtoenrol'));
                }
            }
            // Notify the user, that he can not post a new discussion.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }

        // Where is the user coming from?
        $SESSION->fromurl = get_local_referer(false);

        // Prepare the post.
        $this->assemble_prepost();
        $this->prepost->postid = null;
        $this->prepost->discussionid = null;
        $this->prepost->parentid = 0;
        $this->prepost->subject = '';
        $this->prepost->userid = $USER->id;
        $this->prepost->message = '';

        // Unset where the user is coming from.
        // Allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    /**
     * Function to prepare a new post that replies to an existing post.
     *
     * @param int $replypostid      The ID of the post that is being answered.
     */
    private function build_prepost_reply($replypostid) {
        global $DB, $PAGE, $SESSION, $USER, $CFG;

        // Get the related poost, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($replypostid, false);

        // Ensure the coursemodule is set correctly.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);

        // Prepare a post.
        $this->assemble_prepost();
        $this->prepost->postid = null;
        $this->prepost->parentid = $this->info->relatedpost->get_id();
        $this->prepost->userid = $USER->id;
        $this->prepost->message = '';

        // Check whether the user is allowed to post.
        if (!$this->check_user_can_create_reply()) {

            // Give the user the chance to enroll himself to the course.
            if (!isguestuser() && !is_enrolled($this->info->coursecontext)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php',
                    ['id' => $this->info->course->id,
                     'returnurl' => '/mod/moodleoverflow/view.php?m=' . $this->info->moodleoverflow->id,
                    ]), get_string('youneedtoenrol'));
            }
            // Print the error message.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }
        // Make sure the user can post here.
        if (!$this->info->cm->visible && !has_capability('moodle/course:viewhiddenactivities', $this->info->modulecontext)) {
            throw new moodle_exception('activityiscurrentlyhidden');
        }

        // Append 'RE: ' to the discussions subject.
        $strre = get_string('re', 'moodleoverflow');
        if (check_php_version('8.0.0')) {
            if (!(str_starts_with($this->prepost->subject, $strre))) {
                $this->prepost->subject = $strre . ' ' . $this->prepost->subject;
            }
        } else {
            // LEARNWEB-TODO: remove this else branch when support for php version 7.4 ends.
            if (!(substr($this->prepost->subject, 0, strlen($strre)) == $strre)) {
                $this->prepost->subject = $strre . ' ' . $this->prepost->subject;
            }
        }

        // Unset where the user is coming from.
        // Allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    /**
     * Function to prepare the edit of an user own existing post.
     *
     * @param int $editpostid       The ID of the post that is being edited.
     */
    private function build_prepost_edit($editpostid) {
        global $DB, $PAGE, $SESSION, $USER;

        // Get the related post, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($editpostid, false);

        // Set the pages context.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);

        // Check if the post can be edited.
        $beyondtime = ((time() - $this->info->relatedpost->created) > get_config('moodleoverflow', 'maxeditingtime'));

        // Please be aware that in future the use of get_db_object() should be replaced with $this->info->relatedpost,
        // as the review class should be refactored with the new way of working with posts.
        $alreadyreviewed = review::should_post_be_reviewed($this->info->relatedpost->get_db_object(), $this->info->moodleoverflow)
                           && $this->info->relatedpost->reviewed;
        if (($beyondtime || $alreadyreviewed) && !has_capability('mod/moodleoverflow:editanypost',
                                                                 $this->info->modulecontext)) {
            throw new moodle_exception('maxtimehaspassed', 'moodleoverflow', '',
                format_time(get_config('moodleoverflow', 'maxeditingtime')));
        }

        // If the current user is not the one who posted this post.
        if ($this->info->relatedpost->get_userid() != $USER->id) {

            // Check if the current user has not the capability to edit any post.
            if (!has_capability('mod/moodleoverflow:editanypost', $this->info->modulecontext)) {

                // Display the error. Capabilities are missing.
                throw new moodle_exception('cannoteditposts', 'moodleoverflow');
            }
        }

        // Load the $post variable.
        $this->assemble_prepost();

        // Unset where the user is coming from. This allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    /**
     * Function to prepare the deletion of a post.
     *
     * @param int $deletepostid     The ID of the post that is being deleted.
     */
    private function build_prepost_delete($deletepostid) {
        global $DB, $USER;

        // Get the related post, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($deletepostid, false);

        // Require a login and retrieve the modulecontext.
        require_login($this->info->course, false, $this->info->cm);

        // Check some capabilities.
        $this->check_user_can_delete_post();

        // Count all replies of this post.
        $this->info->replycount = $this->info->relatedpost->moodleoverflow_count_replies(false);
        if ($this->info->replycount >= 1) {
            $this->info->deletetype = 'plural';
        } else {
            $this->info->deletetype = 'singular';
        }
        // Build the prepost.
        $this->assemble_prepost();
        $this->prepost->deletechildren = true;
    }

    // Execute Functions.

    /**
     * Executes the creation of a new discussion.
     *
     * @param object $form The results from the post_form.
     * @throws moodle_exception if the discussion could not be added.
     */
    private function execute_create($form) {
        global $USER;
        // Check if the user is allowed to post.
        $this->check_user_can_create_discussion();

        // Set the post to not reviewed if questions should be reviewed and the user is not a reviewed themselves.
        if (review::get_review_level($this->info->moodleoverflow) >= review::QUESTIONS &&
                !capabilities::has(capabilities::REVIEW_POST, $this->info->modulecontext, $USER->id)) {
            $this->prepost->reviewed = 0;
        } else {
            $this->prepost->reviewed = 1;
        }

        // Get the discussion subject.
        $this->prepost->subject = $form->subject;

        // Create the discussion object.
        $discussion = discussion::construct_without_id($this->prepost->courseid, $this->prepost->moodleoverflowid,
                                                       $this->prepost->subject, 0, $this->prepost->userid,
                                                       $this->prepost->timenow, $this->prepost->timenow, $this->prepost->userid);
        if (!$discussion->moodleoverflow_add_discussion($this->prepost)) {
            throw new moodle_exception('couldnotadd', 'moodleoverflow');
        }

        // The creation was successful.
        $redirectmessage = \html_writer::tag('p', get_string("postaddedsuccess", "moodleoverflow"));

        // Trigger the discussion created event.
        $params = ['context' => $this->info->modulecontext, 'objectid' => $discussion->get_id()];
        $event = discussion_created::create($params);
        $event->trigger();

        // Subscribe to this thread.
        // Please be aware that in future the use of get_db_object() should be replaced with only $this->info->discussion,
        // as the subscription class should be refactored with the new way of working with posts.
        subscriptions::moodleoverflow_post_subscription($form, $this->info->moodleoverflow,
                                                                            $discussion->get_db_object(),
                                                                            $this->info->modulecontext);

        // Define the location to redirect the user after successfully posting.
        $redirectto = new moodle_url('/mod/moodleoverflow/view.php', ['m' => $form->moodleoverflow]);
        redirect(moodleoverflow_go_back_to($redirectto->out()), $redirectmessage, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Executes the reply to an existing post.
     *
     * @param object $form The results from the post_form.
     * @throws moodle_exception if the reply could not be added.
     */
    private function execute_reply($form) {
        // Check if the user has the capability to write a reply.
        $this->check_user_can_create_reply();

        // Set to not reviewed, if posts should be reviewed, and user is not a reviewer themselves.
        if (review::get_review_level($this->info->moodleoverflow) == review::EVERYTHING &&
                !has_capability('mod/moodleoverflow:reviewpost', \context_module::instance($this->info->cm->id))) {
            $this->prepost->reviewed = 0;
        } else {
            $this->prepost->reviewed = 1;
        }

        // Create the new post.
        if (!$newpostid = $this->info->discussion->moodleoverflow_add_post_to_discussion($this->prepost)) {
            throw new moodle_exception('couldnotadd', 'moodleoverflow');
        }

        // The creation was successful.
        $redirectmessage = \html_writer::tag('p', get_string("postaddedsuccess", "moodleoverflow"));
        $redirectmessage .= \html_writer::tag('p', get_string("postaddedtimeleft", "moodleoverflow",
                                              format_time(get_config('moodleoverflow', 'maxeditingtime'))));

        // Trigger the post created event.
        $params = ['context' => $this->info->modulecontext, 'objectid' => $newpostid,
                   'other' => ['discussionid' => $this->prepost->discussionid,
                               'moodleoverflowid' => $this->prepost->moodleoverflowid,
                              ],
                  ];
        $event = post_created::create($params);
        $event->trigger();

        // Subscribe to this thread.
        // Please be aware that in future the use of build_db_object() should be replaced with only $this->info->discussion,
        // as the subscription class should be refactored with the new way of working with posts.
        subscriptions::moodleoverflow_post_subscription($form, $this->info->moodleoverflow,
                                                                             $this->info->discussion->get_db_object(),
                                                                             $this->info->modulecontext);

        // Define the location to redirect the user after successfully posting.
        $redirectto = new moodle_url('/mod/moodleoverflow/discussion.php',
                                      ['d' => $this->prepost->discussionid, 'p' => $newpostid]);
        redirect(\moodleoverflow_go_back_to($redirectto->out()), $redirectmessage, null, \core\output\notification::NOTIFY_SUCCESS);

    }

    /**
     * Executes the edit of an existing post.
     *
     * @param object $form The results from the post_form.
     * @throws moodle_exception if the post could not be updated.
     */
    private function execute_edit($form) {
        global $USER, $DB;
        // Check if the user has the capability to edit his post.
        $this->check_user_can_edit_post();

        // If the post that is being edited is the parent post, the subject can be edited too.
        if ($this->prepost->parentid == 0) {
            $this->prepost->subject = $form->subject;
        }

        // Update the post.
        if (!$this->info->discussion->moodleoverflow_edit_post_from_discussion($this->prepost)) {
            throw new moodle_exception('couldnotupdate', 'moodleoverflow');
        }

        // The edit was successful.
        $redirectmessage = get_string('postupdated', 'moodleoverflow');
        if ($this->prepost->userid == $USER->id) {
            $redirectmessage = get_string('postupdated', 'moodleoverflow');
        } else {
            if (anonymous::is_post_anonymous($this->info->discussion, $this->info->moodleoverflow, $this->prepost->userid)) {
                $name = get_string('anonymous', 'moodleoverflow');
            } else {
                $realuser = $DB->get_record('user', ['id' => $this->prepost->userid]);
                $name = fullname($realuser);
            }
            $redirectmessage = get_string('editedpostupdated', 'moodleoverflow', $name);
        }

        // Trigger the post updated event.
        $params = ['context' => $this->info->modulecontext, 'objectid' => $form->edit,
                   'other' => ['discussionid' => $this->prepost->discussionid,
                               'moodleoverflowid' => $this->prepost->moodleoverflowid,
                              ],
                   'relateduserid' => $this->prepost->userid == $USER->id ? $this->prepost->userid : null,
                  ];
        $event = post_updated::create($params);
        $event->trigger();

        // Define the location to redirect the user after successfully editing.
        $redirectto = new moodle_url('/mod/moodleoverflow/discussion.php',
                                      ['d' => $this->prepost->discussionid, 'p' => $form->edit]);
        redirect(moodleoverflow_go_back_to($redirectto->out()), $redirectmessage, null, \core\output\notification::NOTIFY_SUCCESS);
    }

    /**
     * Executes the deletion of a post.
     *
     * @throws moodle_exception if the post could not be deleted.
     */
    public function execute_delete() {
        global $SESSION;
        $this->check_interaction('delete');

        // Check if the user has the capability to delete the post.
        $timepassed = time() - $this->info->relatedpost->created;
        $SESSION->errorreturnurl = new moodle_url('/mod/moodleoverflow/discussion.php',
                                                  ['d' => $this->info->discussion->get_id()]);
        if (($timepassed > get_config('moodleoverflow', 'maxeditingtime')) && !$this->info->deleteanypost) {
            throw new moodle_exception('cannotdeletepost', 'moodleoverflow');
        }

        // A normal user cannot delete his post if there are direct replies.
        if ($this->info->replycount && !$this->info->deleteanypost) {
            throw new moodle_exception('cannotdeletereplies', 'moodleoverflow');
        }

        // Check if the post is a parent post or not.
        if ($this->prepost->parentid == 0) {
            // Save the moodleoverflowid. Then delete the discussion.
            $moodleoverflowid = $this->info->discussion->get_moodleoverflowid();
            $this->info->discussion->moodleoverflow_delete_discussion($this->prepost);

            // Redirect the user back to the start page of the moodleoverflow instance.
            redirect('view.php?m=' . $moodleoverflowid);
        } else {
            $this->info->discussion->moodleoverflow_delete_post_from_discussion($this->prepost);
            $discussionurl = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $this->info->discussion->get_id()]);
            redirect(moodleoverflow_go_back_to($discussionurl));
        }
    }

    // Functions that uses the post.php to build the page.

    /**
     * Builds a part of confirmation page. The confirmation request box is being build by the post.php.
     */
    public function confirm_delete() {
        $this->check_interaction('delete');
        global $PAGE;
        moodleoverflow_set_return();
        $PAGE->navbar->add(get_string('delete', 'moodleoverflow'));
        $PAGE->set_title($this->info->course->shortname);
        $PAGE->set_heading($this->info->course->fullname);
        $PAGE->add_body_class('limitedwidth');
    }

    /**
     *
     * Builds and returns a post_form object where the users enters/edits the message and attachments of the post.
     * @param array $pageparams    An object that the post.php created.
     * @return object a mod_moodleoverflow_post_form object.
     */
    public function build_postform($pageparams) {
        global $USER, $CFG;
        // Require that the user is logged in properly and enrolled to the course.
        require_login($this->info->course, false, $this->info->cm);

        // Prepare the attachments.
        $draftitemid = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area($draftitemid, $this->info->modulecontext->id, 'mod_moodleoverflow', 'attachment',
                                empty($this->prepost->postid) ? null : $this->prepost->postid,
                                \mod_moodleoverflow_post_form::attachment_options($this->info->moodleoverflow));

        // If the post is anonymous, attachments should have an anonymous author when editing the attachment.
        if ($draftitemid && $this->interaction == 'edit' && anonymous::is_post_anonymous($this->info->discussion,
                $this->info->moodleoverflow, $this->prepost->userid)) {
            $usercontext = \context_user::instance($USER->id);
            $anonymousstr = get_string('anonymous', 'moodleoverflow');
            foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid) as $file) {
                $file->set_author($anonymousstr);
            }
        }

        // Prepare the form.
        $edit = $this->interaction == 'edit';
        $formarray = ['course' => $this->info->course, 'cm' => $this->info->cm, 'coursecontext' => $this->info->coursecontext,
                      'modulecontext' => $this->info->modulecontext, 'moodleoverflow' => $this->info->moodleoverflow,
                      'post' => $this->prepost, 'edit' => $edit,
                    ];

        // Declare the post_form.
        $mformpost = new \mod_moodleoverflow_post_form('post.php', $formarray, 'post', '', ['id' => 'mformmoodleoverflow']);

        // If the user is not the original author append an extra message to the message. (Happens when interaction = 'edit').
        if ($USER->id != $this->prepost->userid) {
            // Create a temporary object.
            $data = new stdClass();
            $data->date = userdate(time());
            $this->prepost->messageformat = editors_get_preferred_format();
            if ($this->prepost->messageformat == FORMAT_HTML) {
                $data->name = \html_writer::tag('a', $CFG->wwwroot . '/user/view.php?id' . $USER->id .
                                                '&course=' . $this->prepost->courseid . '">' . fullname($USER));
                $this->prepost->message .= \html_writer::tag('p', \html_writer::tag('span',
                             get_string('editedby', 'moodleoverflow', $data), ["class" => "edited"]));
            } else {
                $data->name = fullname($USER);
                $this->prepost->message .= "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
            }
            // Delete the temporary object.
            unset($data);
        }

        // Define the heading for the form.
        $formheading = '';
        if ($this->interaction == 'reply') {
            $heading = get_string('yourreply', 'moodleoverflow');
            $formheading = get_string('reply', 'moodleoverflow');
        } else {
            $heading = get_string('yournewtopic', 'moodleoverflow');
        }

        // Set data for the form.
        $mformpost->set_data([
             'attachments' => $draftitemid,
             'general' => $heading,
             'subject' => $this->prepost->subject,
             'message' => ['text' => $this->prepost->message,
                           'format' => editors_get_preferred_format(),
                           'itemid' => $this->prepost->postid, ],
             'userid' => $this->prepost->userid,
             'parent' => $this->prepost->parentid,
             'discussion' => $this->prepost->discussionid,
             'course' => $this->prepost->courseid,
            ]
            + $pageparams
        );

        return $mformpost;
    }

    // Helper functions.

    // Error handling functions.

    /**
     * Handles errors that occur in the post controller.
     *
     * @param string $errormessage
     * @return void
     */
    public function error_handling(string $errormessage): void {
        global $SESSION;
        notification::error($errormessage);
        isset($SESSION->errorreturnurl) ? redirect($SESSION->errorreturnurl) : redirect(new moodle_url('/my/'));
    }

    // Getter.

    /**
     * Returns the interaction type.
     * @return string $interaction
     */
    public function get_interaction() {
        return $this->interaction;
    }

    /**
     * Returns the gathered important information in the build_prepost_() functions.
     * @return object $info
     */
    public function get_information() {
        return $this->info;
    }

    /**
     * Retuns the prepared post.
     * @return object $prepost
     */
    public function get_prepost() {
        return $this->prepost;
    }

    // Functions that build the info and prepost object.

    /**
     * Builds the information object that is being used in the build prepost functions.
     * The variables are optional, but one is necessary to build the information object.
     * @param int $postid
     * @param int $moodleoverflowid
     */
    private function collect_information($postid = false, $moodleoverflowid = false) {
        if ($postid) {
            // The related post is the post that is being answered, edited, or deleted.
            $this->info->relatedpost = $this->check_post_exists($postid);
            $this->info->discussion = $this->check_discussion_exists($this->info->relatedpost->get_discussionid());
            $localmoodleoverflowid = $this->info->discussion->get_moodleoverflowid();
        } else {
            $localmoodleoverflowid = $moodleoverflowid;
        }
        $this->info->moodleoverflow = $this->check_moodleoverflow_exists($localmoodleoverflowid);
        $this->info->course = $this->check_course_exists($this->info->moodleoverflow->course);
        $this->info->cm = $this->check_coursemodule_exists($this->info->moodleoverflow->id, $this->info->course->id);
        $this->info->modulecontext = \context_module::instance($this->info->cm->id);
        $this->info->coursecontext = \context_course::instance($this->info->course->id);
    }

    /**
     * Assembles the prepost object. Helps to reduce code in the build_prepost functions.
     * Some prepost parameters will be assigned individually by the build_prepost functions.
     */
    private function assemble_prepost() {
        $this->prepost = new stdClass();
        $this->prepost->courseid = $this->info->course->id;
        $this->prepost->moodleoverflowid = $this->info->moodleoverflow->id;
        $this->prepost->modulecontext = $this->info->modulecontext;

        if ($this->interaction != 'create') {
            $this->prepost->discussionid = $this->info->discussion->get_id();
            $this->prepost->subject = $this->info->discussion->name;

            if ($this->interaction != 'reply') {
                $this->prepost->parentid = $this->info->relatedpost->get_parentid();
                $this->prepost->postid = $this->info->relatedpost->get_id();
                $this->prepost->userid = $this->info->relatedpost->get_userid();
                $this->prepost->message = $this->info->relatedpost->message;
            }
        }
    }


    // Interaction check.

    /**
     * Checks if the interaction is correct
     * @param string $interaction
     * @return true if the interaction is correct
     */
    private function check_interaction($interaction) {
        if ($this->interaction != $interaction) {
            throw new moodle_exception('wronginteraction' , 'moodleoverflow');
        }
        return true;
    }

    // Database checks.

    /**
     * Checks if the course exists. Returns the $DB->record of the course.
     * @param int $courseid
     * @return object $course
     */
    private function check_course_exists($courseid) {
        global $DB;
        if (!$course = $DB->get_record('course', ['id' => $courseid])) {
            throw new moodle_exception('invalidcourseid');
        }
        return $course;
    }

    /**
     * Checks if the coursemodule exists.
     * @param int $moodleoverflowid
     * @param int $courseid
     * @return object $cm
     */
    private function check_coursemodule_exists($moodleoverflowid, $courseid) {
        if (!$cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflowid,
                                                                    $courseid)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        return $cm;
    }

    /**
     * Checks if the related moodleoverflow exists.
     * @param int $moodleoverflowid
     * @return object $moodleoverflow
     */
    private function check_moodleoverflow_exists($moodleoverflowid) {
        // Get the related moodleoverflow instance.
        global $DB;
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', ['id' => $moodleoverflowid])) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }
        return $moodleoverflow;
    }

    /**
     * Checks if the related discussion exists.
     * @param int $discussionid
     * @return object $discussion
     */
    private function check_discussion_exists($discussionid) {
        global $DB;
        if (!$discussionrecord = $DB->get_record('moodleoverflow_discussions', ['id' => $discussionid])) {
            throw new moodle_exception('invaliddiscussionid', 'moodleoverflow');
        }
        return discussion::from_record($discussionrecord);
    }

    /**
     * Checks if a post exists.
     * @param int $postid
     * @return object $post
     */
    private function check_post_exists($postid) {
        global $DB;
        if (!$postrecord = $DB->get_record('moodleoverflow_posts', ['id' => $postid])) {
            throw new moodle_exception('invalidpostid', 'moodleoverflow');
        }
        return post::from_record($postrecord);
    }

    // Capability checks.

    /**
     * Checks if a user can create a discussion.
     * @return true
     * @throws moodle_exception
     */
    private function check_user_can_create_discussion() {
        if (!has_capability('mod/moodleoverflow:startdiscussion', $this->info->modulecontext)) {
            throw new moodle_exception('cannotcreatediscussion', 'moodleoverflow');
        }
        return true;
    }

    /**
     * Checks if a user can reply in a discussion.
     * @return true
     * @throws moodle_exception
     */
    private function check_user_can_create_reply() {
        if (!has_capability('mod/moodleoverflow:replypost', $this->info->modulecontext, $this->prepost->userid)) {
            throw new moodle_exception('cannotreply', 'moodleoverflow');
        }
        return true;
    }

    /**
     * Checks if a user can edit a post.
     * A user can edit if he can edit any post of if he edits his own post and has the ability to:
     * start a new discussion or to reply to a post.
     *
     * @return true
     * @throws moodle_exception
     */
    private function check_user_can_edit_post() {
        global $USER;
        $editanypost = has_capability('mod/moodleoverflow:editanypost', $this->info->modulecontext);
        $replypost = has_capability('mod/moodleoverflow:replypost', $this->info->modulecontext);
        $startdiscussion = has_capability('mod/moodleoverflow:startdiscussion', $this->info->modulecontext);
        $ownpost = ($this->prepost->userid == $USER->id);
        if (!(($ownpost && ($replypost || $startdiscussion)) || $editanypost)) {
            throw new moodle_exception('cannotupdatepost', 'moodleoverflow');
        }
        return true;
    }

    /**
     * Checks if a user can edit a post.
     * @return true
     * @throws moodle_exception
     */
    private function check_user_can_delete_post() {
        global $USER;
        $this->info->deleteownpost = has_capability('mod/moodleoverflow:deleteownpost', $this->info->modulecontext);
        $this->info->deleteanypost = has_capability('mod/moodleoverflow:deleteanypost', $this->info->modulecontext);
        if (!(($this->info->relatedpost->get_userid() == $USER->id && $this->info->deleteownpost) || $this->info->deleteanypost)) {

            throw new moodle_exception('cannotdeletepost', 'moodleoverflow');
        }
        return true;
    }
}
