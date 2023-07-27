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


namespace mod_moodleoverflow\post\post_control;

// Import namespace from the locallib, needs a check later which namespaces are really needed.
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\review;

use mod_moodleoverflow\post\post;
use mod_moodleoverflow\discussion;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

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
     * - edit (change the contennt of an existing post)
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
        $this->info = new \stdClass;
    }

    /**
     * Detects the interaction and builds the prepost.
     * @param object $urlparamter parameter from the post.php
     * @throws moodle_exception if the interaction is not correct.
     */
    public function detect_interaction($urlparameter) {
        $count = 0;
        $count += $urlparameter->create ? 1 : 0;
        $count += $urlparameter->reply ? 1 : 0;
        $count += $urlparameter->edit ? 1 : 0;
        $count += $urlparameter->delete ? 1 : 0;
        if ($count !== 1) {
            throw new coding_exception('Exactly one parameter should be specified!');
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
            $this->info->replypostid = $urlparameter->edit;
            $this->build_prepost_reply($this->info->replypostid);

        } else if ($urlparameter->delete) {
            $this->interaction = 'delete';
            $this->info->deletepostid = $urlparameter->edit;
            $this->build_prepost_delete($this->info->deletepostid);
        } else {
            throw new moodle_exception('unknownaction');
        }
    }

    /**
     * Controls the execution of an interaction.
     * @param object $form The results from the post_form.
     * @return bool if the execution succeded
     */
    public function execute_interaction($form) {
        // Redirect url in case of occuring errors.
        if (empty($SESSION->fromurl)) {
            $errordestination = '$CFG->wwwroot/mod/moodleoverflow/view.php?m=' . $this->prepost->moodleoverflowid;
        } else {
            $errordestination = $SESSION->fromurl;
        }

        // Format the submitted data.
        $this->prepost->messageformat = $fromform->message['format'];
        $this->prepost->message = $fromform->message['text'];
        $this->prepost->messagetrust = trusttext_trusted($this->prepost->modulecontext);

        // FEHLT.
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
            throw new moodle_exception('inaccurateparameter', 'moodleoverflow');
        }
        if ($postid) {
            $this->collect_information($postid, false);
        } else if ($moodleoverflowid) {
            $this->collect_information(false, $moodleoverflowid);
        }
        $this->info->modulecontext = context_module::instance($this->info->cm->id);

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
        if (!moodleoverflow_user_can_post_discussion($this->info->moodleoverflow, $this->info->cm, $this->info->modulecontext)) {

            // Catch unenrolled user.
            if (!isguestuser() && !is_enrolled($this->info->coursecontext)) {
                if (enrol_selfenrol_available($this->info->course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php',
                                            array('id' => $this->info->course->id,
                                                  'returnurl' => '/mod/moodleoverflow/view.php?m=' .
                                                                 $this->info->moodleoverflow->id)),
                             get_string('youneedtoenrol'));
                }
            }
            // Notify the user, that he can not post a new discussion.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }

        // Set the post to not reviewed if questions should be reviewed and the user is not a reviewed themselve.
        if (review::get_review_level($this->info->moodleoverflow) >= review::QUESTIONS &&
            !capabilities::has(capabilities::REVIEW_POST, $this->info->modulecontext, $USER->id)) {
            $reviewed = 0;
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
        $this->prepost->reviewed = $reviewed;  // IST DAS OKAY?!

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
        global $DB, $PAGE, $SESSION, $USER;

        // Get the related poost, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($replypostid, false);

        // Ensure the coursemodule is set correctly.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);

        // Check whether the user is allowed to post.
        if (!moodleoverflow_user_can_post($this->info->modulecontext, $this->info->parent)) {

            // Give the user the chance to enroll himself to the course.
            if (!isguestuser() && !is_enrolled($this->info->coursecontext)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php',
                                        array('id' => $this->info->course->id,
                                              'returnurl' => '/mod/moodleoverflow/view.php?m=' .
                                                             $this->info->moodleoverflow->id)),
                         get_string('youneedtoenrol'));
            }
            // Print the error message.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }
        // Make sure the user can post here.
        if (!$this->info->cm->visible && !has_capability('moodle/course:viewhiddenactivities', $this->info->modulecontext)) {
            throw new moodle_exception('activityiscurrentlyhidden');
        }

        // Prepare a post.
        $this->assemble_prepost();
        $this->prepost->userid = $USER->id;
        $this->prepost->message = '';

        // Append 'RE: ' to the discussions subject.
        $strre = get_string('re', 'moodleoverflow');
        if (!(substr($this->prepost->subject, 0, strlen($strre)) == $strre)) {
            $this->prepost->subject = $strre . ' ' . $this->prepost->subject;
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
        $alreadyreviewed = review::should_post_be_reviewed($this->info->relatedpost, $this->info->moodleoverflow)
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
        $this->assemble->prepost();

        // Unset where the user is coming from.
        // Allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    /**
     * Function to prepare the deletion of a post.
     *
     * @param int $deletepostid     The ID of the post that is being deleted.
     */
    private function build_prepost_delete($deletepostid) {
        global $DB, $USER;

        // Get the realted post, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($deletepostid, false);

        // Require a login and retrieve the modulecontext.
        require_login($this->info->course, false, $this->info->cm);

        // Check some capabilities.
        $this->info->deleteownpost = has_capability('mod/moodleoverflow:deleteownpost', $this->info->modulecontext);
        $this->info->deleteanypost = has_capability('mod/moodleoverflow:deleteanypost', $this->info->modulecontext);
        if (!(($this->info->relatedpost->get_userid() == $USER->id && $this->info->deleteownpost)
            || $this->info->deleteanypost)) {

            throw new moodle_exception('cannotdeletepost', 'moodleoverflow');
        }

        // Count all replies of this post.
        $this->info->replycount = moodleoverflow_count_replies($this->info->relatedpost, false);

        // Build the prepost.
        $this->assemble->prepost();
        $this->prepost->deletechildren = true;
    }

    // Execute Functions, that execute an interaction.

    private function execute_create() {
        $this->check_interaction('create');
    }

    private function execute_reply() {
        $this->check_interaction('reply');
    }

    private function execute_edit() {
        $this->check_interaction('edit');
    }

    public function execute_delete() {
        $this->check_interaction('delete');

        // Check if the user has the capability to delete the post.
        $timepassed = time() - $this->info->relatedpost->created;
        $url = new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $this->info->discussion->get_id()));
        if (($timepassed > get_config('moodleoverflow', 'maxeditingtime')) && !$this->info->deleteanypost) {
            throw new moodle_exception('cannotdeletepost', 'moodleoverflow', moodleoverflow_go_back_to($url));
        }

        // A normal user cannot delete his post if there are direct replies.
        if ($this->infro->replycount && !$this->info->deleteanypost) {
            throw new moodle_exception('cannotdeletereplies', 'moodleoverflow', moodleoverflow_go_back_to($url));
        }

        // Check if the post is a parent post or not.
        if ($this->prepost->get_parentid() == 0) {
            $this->info->discussion->moodleoverflow_delete_discussion($this->prepost);

            // Redirect the user back to the start page of the moodleoverflow instance.
            redirect('view.php?m=' . $this->info->discussion->get_moodleoverflowid());
        } else {
            $this->info->discussion->moodleoverflow_delete_post_from_discussion($this->prepost);
            $discussionurl = new moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $this->info->discussion->get_id()));
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
     * Builds and returns a post_form object where the users enters/edits the message and attachments of the post.
     * @param array $pageparams    An object that the post.php created.
     * @return object a mod_moodleoverflow_post_form object.
     */
    public function build_postform($pageparams) {
        // Require that the user is logged in properly and enrolled to the course.
        require_login($this->info->course, false, $this->info->cm);

        // Prepare the attachments.
        $draftitemid = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area($draftitemid, $this->info->modulecontext->id, 'mod_moodleoverflow', 'attachment',
                                empty($this->prepost->postid) ? null : $this->prepost->postid,
                                mod_moodleoverflow_post_form::attachment_options($this->info->moodleoverflow));

        // Prepare the form.
        $edit = $this->interaction == 'edit' ? true : false;
        $formarray = array( 'course' => $this->info->course, 'cm' => $this->info->cm, 'coursecontext' => $this->info->coursecontext,
                            'modulecontext' => $this->info->modulecontext, 'moodleoverflow' => $this->info->moodleoverflow,
                            'post' => $this->info->post, 'edit' => $edit);

        // Declare the post_form.
        $mformpost = new mod_moodleoverflow_post_form('post.php', $formarray, 'post', '', array('id' => 'mformmoodleoverflow'));

        // If the user is not the original author append an extra message to the message. (Happens when interaction = 'edit').
        if ($USER->id != $this->prepost->userid) {
            // Create a temporary object.
            $data = new \stdClass();
            $data->date = userdate(time());
            $this->prepost->messageformat = editors_get_preferred_format();
            if ($this->prepost->messageformat == FORMAT_HTML) {
                $data->name = '<a href="' . $CFG->wwwroot . '/user/view.php?id' . $USER->id . '&course=' .
                              $this->prepost->courseid . '">' . fullname($USER) . '</a>';
                $this->prepost->message .= '<p><span class="edited">(' . get_string('editedby', 'moodleoverflow', $data) .
                                           ')</span></p>';
            } else {
                $data->name = fullname($USER);
                $this->prepost->message .= "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
            }
            // Delete the temporary object.
            unset($data);
        }

        // Define the heading for the form.
        $formheading = '';
        if ($this->info->relatedpost->moodleoverflow_get_parentpost()) {
            $heading = get_string('yourreply', 'moodleoverflow');
            $formheading = get_string('reply', 'moodleoverflow');
        } else {
            $heading = get_string('yournewtopic', 'moodleoverflow');
        }

        // Set data for the form.
        $mformpost->set_data(array(
            'attachments' => $draftitemid, 'general' => $heading, 'subject' => $this->prepost->subject,
            'message' => array('text' => $this->prepost->message,
                               'format' => editors_get_preferred_format(),
                               'itemid' => $this->prepost->postid),
            'userid' => $this->prepost->userid, 'parent' => $this->prepost->parentid, 'discussion' => $this->prepost->discussionid,
            'course' => $this->prepost->courseid)
            + $pageparams
        );

        return $mformpost;
    }

    // Helper functions.

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
     * @return bool, if object could be build or not.
     */
    private function collect_information($postid = false, $moodleoverflowid = false) {
        if (!($postid xor $moodleoverflowid)) {
            throw new moodle_exception('inaccurateparameter', 'moodleoverflow');
            return false;
        }
        if ($postid) {
            $this->info->relatedpost = $this->check_post_exists($postid);
            $this->info->discussion = $this->check_discussion_exists($this->info->relatedpost->get_discussionid());
            $localmoodleoverflowid = $this->info->discussion->get_moodleoverflowid();
        } else {
            $localmoodleoverflowid = $moodleoverflowid;
        }
        $this->info->moodleoverflow = $this->check_moodleoverflow_exists($localmoodleoverflowid);
        $this->info->course = $this->check_course_exists($this->info->moodleoverflow->course);
        $this->info->cm = $this->check_coursemodule_exists($this->info->moodleoverflow->id, $this->info->course->id);
        $this->info->modulecontext = context_module::instance($this->info->cm->id);
        $this->info->coursecontext = context_module::instance($this->info->course->id);
        return true;
    }

    /**
     * Assembles the prepost object. Helps to reduce code in the build_prepost functions.
     * Some prepost parameters will be assigned individually by the build_prepost functions.
     */
    private function assemble_prepost() {
        $this->prepost = new \stdClass();
        $this->prepost->courseid = $this->info->course->id;
        $this->prepost->moodleoverflowid = $this->info->moodleoverflow->id;
        $this->prepost->modulecontext = $this->info->modulecontext;

        if ($this->interaction != 'create') {
            $this->prepost->postid = $this->info->relatedpost->get_id();
            $this->prepost->discussionid = $this->info->discussion->get_id();
            $this->prepost->parentid = $this->info->relatedpost->get_parentid();
            $this->prepost->subject = $this->info->discussion->name;

            if ($this->interaction != 'edit') {
                $this->prepost->userid = $this->info->relatedpost->get_userid();
                $this->prepost->message = $this->info->relatedpost->message();
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
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
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
        if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflowid))) {
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
        if (!$discussionrecord = $DB->get_record('moodleoverflow_discussions', array('id' => $discussionid))) {
            throw new moodle_exception('invaliddiscussionid', 'moodleoverflow');
        }
        $discussion = discussion::from_record($discussionrecord);
        return $discussion;
    }

    /**
     * Checks if a post exists.
     * @param int $postid
     * @return object $post
     */
    private function check_post_exists($postid) {
        global $DB;
        if (!$postrecord = $DB->get_record('moodleoverflow_posts', array('id' => $postid))) {
            throw new moodle_exception('invalidpostid', 'moodleoverflow');
        }
        $post = post::from_record($postrecord);
        return $post;
    }

}
