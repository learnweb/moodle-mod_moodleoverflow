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
 * The main moodleoverflow configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $COURSE;

        // Define the modform.
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('moodleoverflowname', 'moodleoverflow'), array('size' => '64'));
        if (!empty(get_config('moodleoverflow', 'formatstringstriptags'))) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Attachments.
        $mform->addElement('header', 'attachmentshdr', get_string('attachments', 'moodleoverflow'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, get_config('moodleoverflow', 'maxbytes'));
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'moodleoverflow');
        $mform->setDefault('maxbytes', get_config('moodleoverflow', 'maxbytes'));

        $choices = array(
            0   => 0,
            1   => 1,
            2   => 2,
            3   => 3,
            4   => 4,
            5   => 5,
            6   => 6,
            7   => 7,
            8   => 8,
            9   => 9,
            10  => 10,
            20  => 20,
            50  => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'moodleoverflow');
        $mform->setDefault('maxattachments', get_config('moodleoverflow', 'maxattachments'));

        // Subscription Handling.
        $mform->addElement('header', 'subscriptiontrackingheader', get_string('subscriptiontrackingheader', 'moodleoverflow'));

        // Prepare the array with options for the subscription state.
        $options = array();
        $options[MOODLEOVERFLOW_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'moodleoverflow');
        $options[MOODLEOVERFLOW_FORCESUBSCRIBE] = get_string('subscriptionforced', 'moodleoverflow');
        $options[MOODLEOVERFLOW_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'moodleoverflow');
        $options[MOODLEOVERFLOW_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled', 'moodleoverflow');

        // Create the option to set the subscription state.
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'moodleoverflow'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'moodleoverflow');

        // Set the options for the default readtracking.
        $options = array();
        $options[MOODLEOVERFLOW_TRACKING_OPTIONAL] = get_string('trackingoptional', 'moodleoverflow');
        $options[MOODLEOVERFLOW_TRACKING_OFF] = get_string('trackingoff', 'moodleoverflow');
        if (get_config('moodleoverflow', 'allowforcedreadtracking')) {
            $options[MOODLEOVERFLOW_TRACKING_FORCED] = get_string('trackingon', 'moodleoverflow');
        }

        // Create the option to set the readtracking state.
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'moodleoverflow'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'moodleoverflow');

        // Choose the default tracking type.
        $default = get_config('moodleoverflow', 'trackingtype');
        if ((!get_config('moodleoverflow', 'allowforcedreadtracking')) AND ($default == MOODLEOVERFLOW_TRACKING_FORCED)) {
            $default = MOODLEOVERFLOW_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        // Rating options.
        $mform->addElement('header', 'ratingheading', get_string('ratingheading', 'moodleoverflow'));

        // Which rating is more important?
        $options = array();
        $options[MOODLEOVERFLOW_PREFERENCE_STARTER] = get_string('starterrating', 'moodleoverflow');
        $options[MOODLEOVERFLOW_PREFERENCE_TEACHER] = get_string('teacherrating', 'moodleoverflow');
        $mform->addElement('select', 'ratingpreference', get_string('ratingpreference', 'moodleoverflow'), $options);
        $mform->addHelpButton('ratingpreference', 'ratingpreference', 'moodleoverflow');
        $mform->setDefault('ratingpreference', MOODLEOVERFLOW_PREFERENCE_STARTER);

        // Course wide reputation?
        $mform->addElement('selectyesno', 'coursewidereputation', get_string('coursewidereputation', 'moodleoverflow'));
        $mform->addHelpButton('coursewidereputation', 'coursewidereputation', 'moodleoverflow');
        $mform->setDefault('coursewidereputation', MOODLEOVERFLOW_REPUTATION_COURSE);

        // Allow negative reputations?
        $mform->addElement('selectyesno', 'allownegativereputation', get_string('allownegativereputation', 'moodleoverflow'));
        $mform->addHelpButton('allownegativereputation', 'allownegativereputation', 'moodleoverflow');
        $mform->setDefault('allownegativereputation', MOODLEOVERFLOW_REPUTATION_NEGATIVE);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }
}
