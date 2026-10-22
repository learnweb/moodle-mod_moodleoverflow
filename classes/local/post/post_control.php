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

namespace mod_moodleoverflow\local\post;

// Import namespace from the locallib, needs a check later which namespaces are really needed.
use core\exception\coding_exception;
use core\notification;
use dml_exception;
use html_writer;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\form\post_form;
use core\exception\moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * This Class controls the manipulation of posts and acts as controller of interactions with the post.php
 * It is important to interact with posts.
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
    private string $interaction;

    /** @var stdClass information about the post like the related moodleoverflow, post etc.
     * Difference between info and prepost: Info has objects, prepost mostly ID's and string like the message of the post.
     */
    private stdClass $info;

    /** @var stdClass prepost for the classes/post_form.php,
     * This object is more like a prototype of a post and it's not in the database.
     * difference between info and prepost: Info has objects, prepost mostly ID's and strings like the message of the post.
     */
    private stdClass $prepost;

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
    public function detect_interaction(stdClass $urlparameter): void {
        $count = 0;
        $count += $urlparameter->create ? 1 : 0;
        $count += $urlparameter->reply ? 1 : 0;
        $count += $urlparameter->edit ? 1 : 0;
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
        }
    }

    /**
     * This function is used when a guest enters the post.php.
     * Parameters will be checked so that the post.php can redirect the user to the right site.
     * @param false|int $postid
     * @param false|int $moodleoverflowid
     * @return stdClass $this->information // The gathered information.
     * @throws coding_exception
     */
    public function catch_guest(false|int $postid = false, false|int $moodleoverflowid = false): stdClass {
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
     * @param int $moodleoverflowid The ID of the moodleoverflow where the new discussion post is being created.
     * @throws moodle_exception
     */
    private function build_prepost_create(int $moodleoverflowid): void {
        global $SESSION, $USER;

        // Get the related moodleoverflow, course coursemodule and the contexts.
        $this->collect_information(false, $moodleoverflowid);

        // Check if the user can start a new discussion.
        permissions::ensure(permissions::can_start_discussion($this->info->moodleoverflow, $USER->id), 'nopostmoodleoverflow');

        // Catch an unenrolled user and give the user the chance to enroll himself to the course.
        $this->catch_unenrolled();

        // Where is the user coming from?
        $SESSION->fromurl = get_local_referer(false);
        $SESSION->errorreturnurl = get_local_referer(false);

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
     * @param int $replypostid The ID of the post that is being answered.
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function build_prepost_reply(int $replypostid): void {
        global $PAGE, $SESSION, $USER;

        // Get the related poost, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($replypostid);

        // Ensure the coursemodule is set correctly.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);

        // Prepare a post.
        $this->assemble_prepost();
        $this->prepost->postid = null;
        $this->prepost->parentid = $this->info->relatedpost->get_id();
        $this->prepost->userid = $USER->id;
        $this->prepost->message = '';

        // Check whether the user is allowed to post.
        permissions::ensure(permissions::can_reply($this->info->relatedpost, $USER->id), 'cannotreply');

        // Catch an unenrolled user and give the user the chance to enroll himself to the course.
        $this->catch_unenrolled();

        // Unset where the user is coming from.
        // Allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    /**
     * Function to prepare the edit of an user own existing post.
     *
     * @param int $editpostid The ID of the post that is being edited.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function build_prepost_edit(int $editpostid): void {
        global $PAGE, $SESSION, $USER;

        // Get the related post, discussion, moodleoverflow, course, coursemodule and contexts.
        $this->collect_information($editpostid);

        // Set the pages context.
        $PAGE->set_cm($this->info->cm, $this->info->course, $this->info->moodleoverflow);

        // Check if the post can be edited.
        permissions::ensure(permissions::can_edit_post($this->info->relatedpost, $USER->id), 'cannoteditposts');

        // Load the $post variable.
        $this->assemble_prepost();

        // Unset where the user is coming from. This allows to calculate the correct return url later.
        unset($SESSION->fromdiscussion);
    }

    // Functions that uses the post.php to build the page.

    /**
     *
     * Builds and returns a post_form object where the users enters/edits the message and attachments of the post.
     * @param array $pageparams An object that the post.php created.
     * @return post_form a post_form object.
     * @throws coding_exception
     * @throws \core\exception\moodle_exception
     */
    public function build_postform(array $pageparams): post_form {
        global $USER;
        // Require that the user is logged in properly and enrolled to the course.
        require_login($this->info->course, false, $this->info->cm);

        // Prepare the attachments.
        $draftitemid = file_get_submitted_draft_itemid('attachments');
        file_prepare_draft_area(
            $draftitemid,
            $this->info->modulecontext->id,
            'mod_moodleoverflow',
            'attachment',
            empty($this->prepost->postid) ? null : $this->prepost->postid,
            $this->info->moodleoverflow->get_attachment_options()
        );

        // If the post is anonymous, attachments should have an anonymous author when editing the attachment.
        if (
            $draftitemid
            && $this->interaction == 'edit'
            && $this->info->moodleoverflow->is_author_anonymous($this->info->discussion->get_userid() === $this->prepost->userid)
        ) {
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
        $mformpost = new post_form('post.php', $formarray, 'post', '', ['id' => 'mformmoodleoverflow']);

        // A new post uses the preferred editor format; an edited post keeps its own format.
        $format = $this->interaction == 'edit' ? $this->prepost->messageformat : editors_get_preferred_format();
        if ($USER->id != $this->prepost->userid) {
            // Create a temporary object.
            $data = new stdClass();
            $data->date = userdate(time());
            if ($format == FORMAT_HTML) {
                $profileurl = new moodle_url('/user/view.php', ['id' => $USER->id, 'course' => $this->prepost->courseid]);
                $data->name = html_writer::link($profileurl, fullname($USER));
                $this->prepost->message .= html_writer::tag(
                    'p',
                    html_writer::tag('span', get_string('editedby', 'moodleoverflow', $data), ['class' => 'edited'])
                );
            } else {
                $data->name = fullname($USER);
                $this->prepost->message .= "\n\n(" . get_string('editedby', 'moodleoverflow', $data) . ')';
            }
            // Delete the temporary object.
            unset($data);
        }

        // Define the heading for the form.
        $heading = ($this->interaction == 'reply') ? get_string('yourreply', 'moodleoverflow')
                                                   : get_string('yournewtopic', 'moodleoverflow');

        // Copy the embedded files of the message into a draft area, so the editor shows and keeps them.
        $messagedraftid = file_get_submitted_draft_itemid('message');
        $message = file_prepare_draft_area(
            $messagedraftid,
            $this->info->modulecontext->id,
            'mod_moodleoverflow',
            'post',
            empty($this->prepost->postid) ? null : $this->prepost->postid,
            $this->info->moodleoverflow->get_editor_options(empty($this->prepost->postid) ? null : $this->prepost->postid),
            $this->prepost->message
        );

        // Set data for the form.
        $mformpost->set_data([
            'attachments' => $draftitemid,
            'general' => $heading,
            'subject' => $this->prepost->subject,
            'message' => ['text' => $message, 'format' => $format, 'itemid' => $messagedraftid],
            'userid' => $this->prepost->userid,
            'parent' => $this->prepost->parentid,
            'discussion' => $this->prepost->discussionid,
            'course' => $this->prepost->courseid,
        ] + $pageparams);

        return $mformpost;
    }

    /**
     * Display the original post when a user replies to it.
     *
     * @throws moodle_exception|dml_exception
     */
    public function display_original_post(): string {
        global $PAGE, $OUTPUT, $USER;
        if ($this->interaction == 'reply') {
            $PAGE->requires->js_call_amd('mod_moodleoverflow/show_post', 'init');
            $data = (object) [
                'postid' => $this->info->relatedpost->get_id(),
                'postcontent' => $this->info->relatedpost->get_message_formatted(),
                'attachments' => $this->info->relatedpost->get_attachments($OUTPUT),
                'byname' => $this->info->relatedpost->get_authorlink($USER->id)['link'],
            ];
            return $OUTPUT->render_from_template('mod_moodleoverflow/post_original', $data);
        }
        return '';
    }

    // Helper functions.

    // Error handling functions.

    /**
     * Handles errors that occur in the post controller.
     *
     * @param string $errormessage
     * @return void
     * @throws moodle_exception
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
    public function get_interaction(): string {
        return $this->interaction;
    }

    /**
     * Returns the gathered important information in the build_prepost_() functions.
     * @return stdClass $info
     */
    public function get_information(): stdClass {
        return $this->info;
    }

    /**
     * Retuns the prepared post.
     * @return stdClass $prepost
     */
    public function get_prepost(): stdClass {
        return $this->prepost;
    }

    // Functions that build the info and prepost object.

    /**
     * Builds the information object that is being used in the build prepost functions.
     * The variables are optional, but one is necessary to build the information object.
     * @param false|int $postid
     * @param false|int $moodleoverflowid
     * @throws moodle_exception
     */
    private function collect_information(false|int $postid = false, false|int $moodleoverflowid = false): void {
        if ($postid) {
            // The related post is the post that is being answered, edited, or deleted.
            $this->info->relatedpost = post::from_id($postid);
            $this->info->discussion = $this->info->relatedpost->get_discussion();
            $localmoodleoverflowid = $this->info->discussion->get_moodleoverflowid();
        } else {
            $localmoodleoverflowid = $moodleoverflowid;
        }
        $this->info->moodleoverflow = moodleoverflow::from_id($localmoodleoverflowid);
        $this->info->course = $this->info->moodleoverflow->get_course();
        $this->info->cm = $this->info->moodleoverflow->get_cm();
        $this->info->modulecontext = $this->info->moodleoverflow->get_context();
        $this->info->coursecontext = \context_course::instance($this->info->course->id);
    }

    /**
     * Assembles the prepost object. Helps to reduce code in the build_prepost functions.
     * Some prepost parameters will be assigned individually by the build_prepost functions.
     */
    private function assemble_prepost(): void {
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
                $this->prepost->messageformat = $this->info->relatedpost->messageformat;
            }
        }
    }

    // Capability checks.

    /**
     * Catches an unenrolled users and gives chance for enrollment.
     *
     * @return void
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    private function catch_unenrolled(): void {
        global $SESSION;
        if (!isguestuser() && !is_enrolled($this->info->coursecontext) && !is_viewing($this->info->coursecontext)) {
            if (enrol_selfenrol_available($this->info->course->id)) {
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(
                    new moodle_url('/enrol/index.php', ['id' => $this->info->course->id,
                    'returnurl' => '/mod/moodleoverflow/view.php?m=' . $this->info->moodleoverflow->id, ]),
                    get_string('youneedtoenrol')
                );
            }
        }
    }
}
