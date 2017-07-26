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
 * File containing the form definition to post in a moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to post in a moodleoverflow.
 *
 * @package   mod_moodleoverflow
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_post_form extends moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition() {

        $modform =& $this->_form;
        $post = $this->_customdata['post'];

        // Fill in the data depending on page params later using set_data.
        $modform->addElement('header', 'general', '');

        // The subject.
        $modform->addElement('text', 'subject', get_string('subject', 'moodleoverflow'), 'size="48"');
        $modform->setType('subject', PARAM_TEXT);
        $modform->addRule('subject', get_string('required'), 'required', null, 'client');
        $modform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // The message.
        $modform->addElement('editor', 'message', get_string('message', 'moodleoverflow'), null);
        $modform->setType('message', PARAM_RAW);
        $modform->addRule('message', get_string('required'), 'required', null, 'client');

        // Submit buttons.
        if (isset($post->edit)) {
            $strsubmit = get_string('savechanges');
        } else {
            $strsubmit = get_string('posttomoodleoverflow', 'moodleoverflow');
        }
        $this->add_action_buttons(true, $strsubmit);

        // The course.
        $modform->addElement('hidden', 'course');
        $modform->setType('course', PARAM_INT);

        // The moodleoverflow instance.
        $modform->addElement('hidden', 'moodleoverflow');
        $modform->setType('moodleoverflow', PARAM_INT);

        // The discussion.
        $modform->addElement('hidden', 'discussion');
        $modform->setType('discussion', PARAM_INT);

        // The parent post.
        $modform->addElement('hidden', 'parent');
        $modform->setType('parent', PARAM_INT);

        // Are we editing a post?
        $modform->addElement('hidden', 'edit');
        $modform->setType('edit', PARAM_INT);

        // Is it a reply?
        $modform->addElement('hidden', 'reply');
        $modform->setType('reply', PARAM_INT);

    }

    /**
     * Form validation.
     *
     * @param array $data data from the form.
     * @param array $files files uplaoded.
     * @return array of errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'moodleoverflow');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'moodleoverflow');
        }
        return $errors;
    }

}







