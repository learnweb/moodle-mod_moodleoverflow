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
 * The main moodleoverflow configuration form.
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\review;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_mod_form extends moodleform_mod {
    /**
     * Defines forms elements.
     */
    public function definition() {
        global $CFG, $COURSE, $PAGE, $DB;

        // Define the modform.
        $mform = $this->_form;

        if ($this->get_instance()) {
            $PAGE->requires->js_call_amd(
                'mod_moodleoverflow/warnmodechange',
                'init',
                [$this->get_current()->forcesubscribe]
            );
        }

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('moodleoverflowname', 'moodleoverflow'), ['size' => '64']);
        if (!empty(get_config('moodleoverflow', 'formatstringstriptags'))) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        $currentsetting = $this->current && property_exists($this->current, 'anonymous') ? $this->current->anonymous : 0;
        $possiblesettings = [
                anonymous::EVERYTHING_ANONYMOUS => get_string('anonymous:everything', 'moodleoverflow'),
        ];

        if ($currentsetting <= anonymous::QUESTION_ANONYMOUS) {
            $possiblesettings[anonymous::QUESTION_ANONYMOUS] = get_string('anonymous:only_questions', 'moodleoverflow');
        }

        if ($currentsetting == anonymous::NOT_ANONYMOUS) {
            $possiblesettings[anonymous::NOT_ANONYMOUS] = get_string('no');
        }

        if (get_config('moodleoverflow', 'allowanonymous') == '1') {
            $mform->addElement('select', 'anonymous', get_string('anonymous', 'moodleoverflow'), $possiblesettings);
            $mform->addHelpButton('anonymous', 'anonymous', 'moodleoverflow');
            $mform->setDefault('anonymous', anonymous::NOT_ANONYMOUS);
        }

        if (get_config('moodleoverflow', 'allowreview') == 1) {
            $possiblesettings = [
                    review::NOTHING => get_string('nothing', 'moodleoverflow'),
                    review::QUESTIONS => get_string('questions', 'moodleoverflow'),
                    review::EVERYTHING => get_string('questions_and_posts', 'moodleoverflow'),
            ];

            $mform->addElement('select', 'needsreview', get_string('review', 'moodleoverflow'), $possiblesettings);
            $mform->addHelpButton('needsreview', 'review', 'moodleoverflow');
            $mform->setDefault('needsreview', review::NOTHING);
        }

        // Attachments.
        $mform->addElement('header', 'attachmentshdr', get_string('attachments', 'moodleoverflow'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, get_config('moodleoverflow', 'maxbytes'));
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'moodleoverflow');
        $mform->setDefault('maxbytes', get_config('moodleoverflow', 'maxbytes'));

        $choices = [
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100,
        ];
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'moodleoverflow');
        $mform->setDefault('maxattachments', get_config('moodleoverflow', 'maxattachments'));

        // Subscription Handling.
        $mform->addElement('header', 'subscriptiontrackingheader', get_string('subscriptiontrackingheader', 'moodleoverflow'));

        // Prepare the array with options for the subscription state.
        $options = [];
        $options[MOODLEOVERFLOW_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'moodleoverflow');
        $options[MOODLEOVERFLOW_FORCESUBSCRIBE] = get_string('subscriptionforced', 'moodleoverflow');
        $options[MOODLEOVERFLOW_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'moodleoverflow');
        $options[MOODLEOVERFLOW_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled', 'moodleoverflow');

        // Create the option to set the subscription state.
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'moodleoverflow'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'moodleoverflow');

        // Set the options for the default readtracking.
        $options = [];
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
        if ((!get_config('moodleoverflow', 'allowforcedreadtracking')) && ($default == MOODLEOVERFLOW_TRACKING_FORCED)) {
            $default = MOODLEOVERFLOW_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        // Grade options.
        $mform->addElement(
            'header',
            'gradeheading',
            get_string('gradenoun')
        );

        $mform->addElement('text', 'grademaxgrade', get_string('modgrademaxgrade', 'grades'));
        $mform->setType('grademaxgrade', PARAM_INT);
        $mform->addRule(
            'grademaxgrade',
            get_string('grademaxgradeerror', 'moodleoverflow'),
            'regex',
            '/^(0|[1-9][0-9]*)$/',
            'client'
        );

        $mform->addElement('text', 'gradescalefactor', get_string('scalefactor', 'moodleoverflow'));
        $mform->addHelpButton('gradescalefactor', 'scalefactor', 'moodleoverflow');
        $mform->setType('gradescalefactor', PARAM_INT);
        $mform->addRule(
            'gradescalefactor',
            get_string('scalefactorerror', 'moodleoverflow'),
            'regex',
            '/^(0|[1-9][0-9]*)$/',
            'client'
        );

        $mform->addElement('text', 'gradepass', get_string('gradepass', 'grades'));
        $mform->addHelpButton('gradepass', 'gradepass', 'grades');
        $mform->setType('gradepass', PARAM_INT);
        $mform->addRule('gradepass', get_string('gradepasserror', 'moodleoverflow'), 'regex', '/^(0|[1-9][0-9]*)$/', 'client');

        if ($this->_features->gradecat) {
            $mform->addElement(
                'select',
                'gradecat',
                get_string('gradecategoryonmodform', 'grades'),
                grade_get_categories_menu($COURSE->id, $this->_outcomesused)
            );
            $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
        }

        // Rating options.
        $mform->addElement('header', 'ratingheading', get_string('ratingheading', 'moodleoverflow'));

        // Which rating is more important?
        $options = [];
        $options[MOODLEOVERFLOW_PREFERENCE_STARTER] = get_string('starterrating', 'moodleoverflow');
        $options[MOODLEOVERFLOW_PREFERENCE_TEACHER] = get_string('teacherrating', 'moodleoverflow');
        $mform->addElement('select', 'ratingpreference', get_string('ratingpreference', 'moodleoverflow'), $options);
        $mform->addHelpButton('ratingpreference', 'ratingpreference', 'moodleoverflow');
        $mform->setDefault('ratingpreference', MOODLEOVERFLOW_PREFERENCE_STARTER);

        if (get_config('moodleoverflow', 'allowdisablerating') == 1) {
            // Allow Rating.
            $mform->addElement('selectyesno', 'allowrating', get_string('allowrating', 'moodleoverflow'));
            $mform->addHelpButton('allowrating', 'allowrating', 'moodleoverflow');
            $mform->setDefault('allowrating', MOODLEOVERFLOW_RATING_ALLOW);

            // Allow Reputation.
            $mform->addElement('selectyesno', 'allowreputation', get_string('allowreputation', 'moodleoverflow'));
            $mform->addHelpButton('allowreputation', 'allowreputation', 'moodleoverflow');
            $mform->setDefault('allowreputation', MOODLEOVERFLOW_REPUTATION_ALLOW);
        }

        // Course wide reputation?
        $mform->addElement('selectyesno', 'coursewidereputation', get_string('coursewidereputation', 'moodleoverflow'));
        $mform->addHelpButton('coursewidereputation', 'coursewidereputation', 'moodleoverflow');
        $mform->setDefault('coursewidereputation', MOODLEOVERFLOW_REPUTATION_COURSE);
        $mform->hideIf('coursewidereputation', 'anonymous', 'gt', 0);

        // Allow negative reputations?
        $mform->addElement('selectyesno', 'allownegativereputation', get_string('allownegativereputation', 'moodleoverflow'));
        $mform->addHelpButton('allownegativereputation', 'allownegativereputation', 'moodleoverflow');
        $mform->setDefault('allownegativereputation', MOODLEOVERFLOW_REPUTATION_NEGATIVE);

        // Allow multiple marks of helpful/solved.
        $mform->addElement('advcheckbox', 'allowmultiplemarks', get_string('allowmultiplemarks', 'moodleoverflow'));
        $mform->addHelpButton('allowmultiplemarks', 'allowmultiplemarks', 'moodleoverflow');
        $mform->setDefault('allowmultiplemarks', 0);

        // Limited answer options.
        $mform->addElement('header', 'limitedanswerheading', get_string('la_heading', 'moodleoverflow'));

        $answersfound = false;
        if (!empty($this->current->id)) {
            // Check if there are already answered posts in this moodleoverflow and place a warning if so.
            $sql = 'SELECT COUNT(*) AS answerposts
            FROM {moodleoverflow_discussions} discuss JOIN {moodleoverflow_posts} posts
            ON discuss.id = posts.discussion
                WHERE posts.parent != 0
                AND discuss.moodleoverflow = ' . $this->current->id . ';';
            $answerpostscount = $DB->get_records_sql($sql);
            $answerpostscount = $answerpostscount[array_key_first($answerpostscount)]->answerposts;
            $answersfound = $answerpostscount > 0;
            if ($answersfound) {
                $warningstring = get_string('la_warning_answers', 'moodleoverflow');
                $warningstring .= '<br>' . get_string('la_warning_conclusion', 'moodleoverflow');
                $htmlwarning = html_writer::div($warningstring, 'alert alert-warning', ['role' => 'alert']);
                $mform->addElement('html', $htmlwarning);
            }
        }

        // Limited answer setting elements..
        $mform->addElement('hidden', 'la_answersfound', $answersfound);
        $mform->setType('la_answersfound', PARAM_BOOL);
        $mform->addElement(
            'date_time_selector',
            'la_starttime',
            get_string('la_starttime', 'moodleoverflow'),
            ['optional' => true]
        );

        $mform->addHelpButton('la_starttime', 'la_starttime', 'moodleoverflow');
        $mform->disabledIf('la_starttime', 'la_answersfound', 'eq', true);

        $mform->addElement(
            'date_time_selector',
            'la_endtime',
            get_string('la_endtime', 'moodleoverflow'),
            ['optional' => true]
        );

        $mform->addHelpButton('la_endtime', 'la_endtime', 'moodleoverflow');

        $mform->addElement('hidden', 'la_error');
        $mform->setType('la_error', PARAM_TEXT);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        $mform->disabledIf('completionusegrade', 'grademaxgrade', 'in', [0, '']);
        $mform->disabledIf('completionusegrade', 'gradescalefactor', 'in', [0, '']);

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Handles data postprocessing.
     *
     * @param array $data data from the form.
     */
    public function data_postprocessing($data) {
        if (isset($data->anonymous) && $data->anonymous != anonymous::NOT_ANONYMOUS) {
            $data->coursewidereputation = false;
        }
    }

    /**
     * Handles data preprocessing.
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (isset($defaultvalues['gradepass'])) {
            $defaultvalues['gradepass'] = (int)$defaultvalues['gradepass'];
        }
    }

    /**
     * Validates set data in mod_form
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // Validate the grade settings.
        if (isset($data['gradepass']) && isset($data['grademaxgrade']) && $data['gradepass'] > $data['grademaxgrade']) {
            $errors['gradepass'] = get_string('gradepassvalidationerror', 'moodleoverflow');
        }

        // Validate that the limited answer settings.
        $currenttime = time();
        $isstarttime = !empty($data['la_starttime']);
        $isendtime = !empty($data['la_endtime']);

        if ($isstarttime && $data['la_starttime'] < $currenttime) {
            $errors['la_starttime'] = get_string('la_starttime_ruleerror', 'moodleoverflow');
        }
        if ($isendtime) {
            if ($data['la_endtime'] < $currenttime) {
                $errors['la_endtime'] = get_string('la_endtime_ruleerror', 'moodleoverflow');
            }

            if ($isstarttime && $data['la_endtime'] <= $data['la_starttime']) {
                if (isset($errors['la_endtime'])) {
                    $errors['la_endtime'] .= '<br>' . get_string('la_sequence_error', 'moodleoverflow');
                } else {
                    $errors['la_endtime'] = get_string('la_sequence_error', 'moodleoverflow');
                }
            }
        }

        return $errors;
    }
}
