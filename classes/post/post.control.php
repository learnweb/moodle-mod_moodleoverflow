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

    /** @var object information about the post like it's moodleoverflow and other */
    private $information;

    /** @var object prepost for the classes/post/post_form.php */
    private $prepost;

    /**
     * Constructor
     *
     * @param object $urlparameter Parameter that were sent when post.php where opened
     */
    public function __construct($urlparameter) {
        $this->information = new \stdClass;
        $this->detect_interaction($urlparameter); // Detects interaction and makes security checks.
    }

    public function get_interaction() {
        return $this->interaction;
    }

    public function get_information() {
        return $this->information;
    }

    private function security_checks_create($moodleoverflowid) {
        global $DB, $SESSION;
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
                    redirect(new moodle_url('/enrol/index.php', array(
                        'id' => $course->id,
                        'returnurl' => '/mod/moodleoverflow/view.php?m=' . $this->information->moodleoverflow->id
                    )), get_string('youneedtoenrol'));
                }
            }

            // Notify the user, that he can not post a new discussion.
            throw new moodle_exception('nopostmoodleoverflow', 'moodleoverflow');
        }
        // Where is the user coming from?
        $SESSION->fromurl = get_local_referer(false);

        // Load all the $post variables.
        $this->prepost = new stdClass();
        $this->prepost->course = $this->information->course->id;
        $this->prepost->moodleoverflow = $this->information->moodleoverflow->id;
        $this->prepost->discussion = 0;
        $this->prepost->parent = 0;
        $this->prepost->subject = '';
        $this->prepost->userid = $USER->id;
        $this->prepost->message = '';

        // Unset where the user is coming from.
        // Allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    private function security_checks_edit($postid) {
        global $DB;

    }

    private function security_checks_reply($replypostid) {
        global $DB;
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
    }

    private function security_checks_delete($deletepostid) {
        global $DB;
    }

    /**
     * Detects the interaction
     * @param object parameter from the post.php
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
            $this->security_checks_create($this->information->moodleoverflowid);

        } else if ($urlparameter->edit) {
            $this->interaction = 'edit';
            $this->information->editpostid = $urlparameter->edit;
            $this->security_checks_edit($this->information->editpostid);

        } else if ($urlparameter->reply) {
            $this->interaction = 'reply';
            $this->information->replypostid = $urlparameter->edit;
            $this->security_checks_reply($this->information->replypostid);

        } else if ($urlparameter->delete) {
            $this->interaction = 'delete';
            $this->information->deletepostid = $urlparameter->edit;
            $this->security_checks_delete($this->information->deletepostid);
        } else {
            throw new moodle_exception('unknownparameter', 'moodleoverflow');
        }
    }
}
