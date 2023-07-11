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
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\capabilities;
use mod_moodleoverflow\review;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Class that makes checks to interact with posts.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_control {

    /** @var string the Interaction type */
    private $interaction;

    /** @var object information about the post like the related moodleoverflow, post etc. .*/
    private $information;

    /** @var object prepost for the classes/post/post_form.php */
    private $prepost;

    /**
     * Constructor
     *
     * @param object $urlparameter Parameter that were sent when post.php where opened.
     */
    public function __construct($urlparameter) {
        $this->information = new \stdClass;
        $this->detect_interaction($urlparameter); // Detects interaction and makes security checks.
    }

    /**
     * Returns the interaction type.
     */
    public function get_interaction() {
        return $this->interaction;
    }

    /**
     * Returns the gathered important information in the build_prepost_() functions.
     */
    public function get_information() {
        return $this->information;
    }

    /**
     * Retuns the prepared post.
     */
    public function get_prepost() {
        return $this->prepost;
    }

    /**
     * Detects the interaction
     * @param object $urlparamter parameter from the post.php
     */
    private function detect_interaction($urlparameter) {
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
            $this->information->moodleoverflowid = $urlparameter->create;
            $this->build_prepost_create($this->information->moodleoverflowid);

        } else if ($urlparameter->edit) {
            $this->interaction = 'edit';
            $this->information->editpostid = $urlparameter->edit;
            $this->build_prepost_edit($this->information->editpostid);

        } else if ($urlparameter->reply) {
            $this->interaction = 'reply';
            $this->information->replypostid = $urlparameter->edit;
            $this->build_prepost_reply($this->information->replypostid);

        } else if ($urlparameter->delete) {
            $this->interaction = 'delete';
            $this->information->deletepostid = $urlparameter->edit;
            $this->build_prepost_delete($this->information->deletepostid);
        } else {
            throw new moodle_exception('unknownaction');
        }
    }

    // Private functions.

    // Build_prepost functions: makes important checks and saves all important information in $prepost object.

    /**
     * Function to prepare a new discussion in moodleoverflow.
     *
     * @param int $moodleoverflowid     The ID of the moodleoverflow where the new discussion post is being created.
     */
    private function build_prepost_create($moodleoverflowid) {
        global $DB, $SESSION, $USER;
        // Check the moodleoverflow instance is valid.
        if (!$this->information->moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflowid))) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }
        // Get the related course.
        if (!$this->information->course = $DB->get_record('course', array('id' => $this->information->moodleoverflow->course))) {
            throw new moodle_exception('invalidcourseid');
        }
        // Get the related coursemodule.
        if (!$this->information->cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflowid,
                                                                     $this->information->course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        // Retrieve the contexts.
        $this->information->modulecontext = context_module::instance($this->information->cm->id);
        $this->information->coursecontext = context_module::instance($this->information->course->id);

        // Check if the user can start a new discussion.
        if (!moodleoverflow_user_can_post_discussion($this->information->moodleoverflow,
                                                     $this->information->cm,
                                                     $this->information->modulecontext)) {

            // Catch unenrolled user.
            if (!isguestuser() && !is_enrolled($this->information->coursecontext)) {
                if (enrol_selfenrol_available($this->information->course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php',
                                            array('id' => $this->information->course->id,
                                                  'returnurl' => '/mod/moodleoverflow/view.php?m=' .
                                                                 $this->information->moodleoverflow->id)),
                             get_string('youneedtoenrol'));
                }
            }
            // Notify the user, that he can not post a new discussion.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }
        // Where is the user coming from?
        $SESSION->fromurl = get_local_referer(false);

        // Prepare the post.
        $this->prepost = new stdClass();
        $this->prepost->courseid = $this->information->course->id;
        $this->prepost->moodleoverflowid = $this->information->moodleoverflow->id;
        $this->prepost->discussionid = 0;
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
        global $DB, $PAGE, $SESSION, $USER;
        // Check if the related post exists.
        if (!$this->information->parent = moodleoverflow_get_post_full($replypostid)) {
            throw new moodle_exception('invalidparentpostid', 'moodleoverflow');
        }

        // Check if the post is part of a valid discussion.
        if (!$this->information->discussion = $DB->get_record('moodleoverflow_discussions',
                                                              array('id' => $this->information->parent->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
        }

        // Check if the post is related to a valid moodleoverflow instance.
        if (!$this->information->moodleoverflow = $DB->get_record('moodleoverflow',
                                                                  array('id' => $this->information->discussion->moodleoverflow))) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }

        // Check if the moodleoverflow instance is part of a course.
        if (!$this->information->course = $DB->get_record('course', array('id' => $this->information->discussion->course))) {
            throw new moodle_exception('invalidcourseid');
        }

        // Retrieve the related coursemodule.
        if (!$this->information->cm = get_coursemodule_from_instance('moodleoverflow',
                                                                     $this->information->moodleoverflow->id,
                                                                     $this->information->course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        // Ensure the coursemodule is set correctly.
        $PAGE->set_cm($this->information->cm, $this->information->course, $this->information->moodleoverflow);

        // Retrieve the contexts.
        $this->information->modulecontext = context_module::instance($this->information->cm->id);
        $this->information->coursecontext = context_module::instance($this->information->course->id);

        // Check whether the user is allowed to post.
        if (!moodleoverflow_user_can_post($this->information->modulecontext, $this->information->parent)) {

            // Give the user the chance to enroll himself to the course.
            if (!isguestuser() && !is_enrolled($this->information->coursecontext)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php',
                                        array('id' => $this->information->course->id,
                                              'returnurl' => '/mod/moodleoverflow/view.php?m=' .
                                                             $this->information->moodleoverflow->id)),
                         get_string('youneedtoenrol'));
            }
            // Print the error message.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }
        // Make sure the user can post here.
        if (!$this->information->cm->visible &&
            !has_capability('moodle/course:viewhiddenactivities', $this->information->modulecontext)) {

            throw new moodle_exception('activityiscurrentlyhidden');
        }

        // Prepare a post.
        $this->prepost = new stdClass();
        $this->prepost->courseid = $this->information->course->id;
        $this->prepost->moodleoverflowid = $this->information->moodleoverflow->id;
        $this->prepost->discussionid = $this->information->discussion->id;
        $this->prepost->parentid = $this->information->parent->id;
        $this->prepost->subject = $this->information->discussion->name;
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
     * Function to prepare the edit of an existing post.
     *
     * @param int $editpostid       The ID of the post that is being edited.
     */
    private function build_prepost_edit($editpostid) {
        global $DB, $PAGE, $SESSION, $USER;

        // Third possibility: The user is editing his own post.

        // Check if the submitted post exists.
        if (!$this->information->relatedpost = moodleoverflow_get_post_full($editpostid)) {
            throw new moodle_exception('invalidpostid', 'moodleoverflow');
        }

        // Get the parent post of this post if it is not the starting post of the discussion.
        if ($this->information->relatedpost->parent) {
            if (!$this->information->parent = moodleoverflow_get_post_full($this->information->relatedpost->parent)) {
                throw new moodle_exception('invalidparentpostid', 'moodleoverflow');
            }
        }

        // Check if the post refers to a valid discussion.
        if (!$this->information->discussion = $DB->get_record('moodleoverflow_discussions',
                                                              array('id' => $this->information->relatedpost->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
        }

        // Check if the post refers to a valid moodleoverflow instance.
        if (!$this->information->moodleoverflow = $DB->get_record('moodleoverflow',
                                                                  array('id' => $this->information->discussion->moodleoverflow))) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoverflow');
        }

        // Check if the post refers to a valid course.
        if (!$this->information->course = $DB->get_record('course', array('id' => $this->information->discussion->course))) {
            throw new moodle_exception('invalidcourseid');
        }

        // Retrieve the related coursemodule.
        if (!$this->information->cm = get_coursemodule_from_instance('moodleoverflow',
                                                                     $this->information->moodleoverflow->id,
                                                                     $this->information->course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        // Retrieve contexts.
        $this->information->modulecontext = context_module::instance($this->information->cm->id);

        // Set the pages context.
        $PAGE->set_cm($this->information->cm, $this->information->course, $this->information->moodleoverflow);

        // Check if the post can be edited.
        $beyondtime = ((time() - $this->information->relatedpost->created) > get_config('moodleoverflow', 'maxeditingtime'));
        $alreadyreviewed = review::should_post_be_reviewed($this->information->relatedpost, $this->information->moodleoverflow)
                           && $this->information->relatedpost->reviewed;
        if (($beyondtime || $alreadyreviewed) && !has_capability('mod/moodleoverflow:editanypost',
                                                                 $this->information->modulecontext)) {

            throw new moodle_exception('maxtimehaspassed', 'moodleoverflow', '',
                format_time(get_config('moodleoverflow', 'maxeditingtime')));
        }

        // If the current user is not the one who posted this post.
        if ($this->information->relatedpost->userid <> $USER->id) {

            // Check if the current user has not the capability to edit any post.
            if (!has_capability('mod/moodleoverflow:editanypost', $this->information->modulecontext)) {

                // Display the error. Capabilities are missing.
                throw new moodle_exception('cannoteditposts', 'moodleoverflow');
            }
        }

        // Load the $post variable.
        $this->prepost = $this->information->relatedpost;
        $this->prepost->editid = $editpostid;
        $this->prepost->course = $this->information->course->id;
        $this->prepost->moodleoverflow = $this->information->moodleoverflow->id;

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

        // Check if the post is existing.
        if (!$this->information->relatedpost = moodleoverflow_get_post_full($deletepostid)) {
            throw new moodle_exception('invalidpostid', 'moodleoverflow');
        }

        // Get the related discussion.
        if (!$this->information->discussion = $DB->get_record('moodleoverflow_discussions',
                                                              array('id' => $this->information->relatedpost->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'moodleoverflow');
        }

        // Get the related moodleoverflow instance.
        if (!$this->information->moodleoverflow = $DB->get_record('moodleoverflow',
                                                                  array('id' => $this->information->discussion->moodleoverflow))) {
            throw new moodle_exception('invalidmoodleoverflowid', 'moodleoveflow');
        }

        // Get the related coursemodule.
        if (!$this->information->cm = get_coursemodule_from_instance('moodleoverflow',
                                                                     $this->information->moodleoverflow->id,
                                                                     $this->information->moodleoverflow->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        // Get the related course.
        if (!$this->information->course = $DB->get_record('course', array('id' => $this->information->moodleoverflow->course))) {
            throw new moodle_exception('invalidcourseid');
        }

        // Require a login and retrieve the modulecontext.
        require_login($this->information->course, false, $this->information->cm);
        $this->information->modulecontext = context_module::instance($this->information->cm->id);

        // Check some capabilities.
        $this->information->deleteownpost = has_capability('mod/moodleoverflow:deleteownpost', $this->information->modulecontext);
        $this->information->deleteanypost = has_capability('mod/moodleoverflow:deleteanypost', $this->information->modulecontext);
        if (!(($this->information->relatedpost->userid == $USER->id && $this->information->deleteownpost)
            || $this->information->deleteanypost)) {

            throw new moodle_exception('cannotdeletepost', 'moodleoverflow');
        }

        // Count all replies of this post.
        $this->information->replycount = moodleoverflow_count_replies($this->information->relatedpost, false);
    }

}
