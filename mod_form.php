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

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\preset;
use mod_moodleoverflow\local\enum\rating_preference;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\enum\tracking_type;
use mod_moodleoverflow\local\models\moodleoverflow;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * The sections follow the questions a teacher asks: what is the forum for (presets, empty forums only), how do people
 * post (anonymity, review, length, answer times; open by default), votes and reputation, notifications, grades and
 * attachments. Fields that only matter after another choice are hidden until that choice is made.
 *
 * @package    mod_moodleoverflow
 * @copyright  2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_moodleoverflow_mod_form extends moodleform_mod {
    /** @var bool|null Whether the edited forum has discussions; null until it is checked once. */
    private ?bool $hasdiscussions = null;

    /**
     * Whether the forum has discussions. A forum that is being created has none.
     * @return bool
     */
    private function has_discussions(): bool {
        return $this->hasdiscussions ??= $this->get_instance()
            && moodleoverflow::from_id($this->get_instance())->has_discussions();
    }

    /**
     * Defines forms elements.
     */
    public function definition() {
        global $PAGE;
        $mform = $this->_form;

        if ($this->get_instance()) {
            $PAGE->requires->js_call_amd('mod_moodleoverflow/warnmodechange', 'init', [$this->get_current()->forcesubscribe]);
        }

        $this->add_general_elements();
        $this->add_posting_elements();
        $this->add_rating_elements();
        $this->add_notification_elements();
        $this->add_grade_elements();
        $this->add_attachment_elements();

        $this->standard_coursemodule_elements();
        $mform->disabledIf('completionusegrade', 'grademaxgrade', 'in', [0, '']);
        $mform->disabledIf('completionusegrade', 'gradescalefactor', 'in', [0, '']);

        $this->add_action_buttons();
    }

    /**
     * Name, description and, for a new forum, the choice of a typical use that fills in the other settings.
     */
    private function add_general_elements(): void {
        global $OUTPUT, $PAGE;
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('moodleoverflowname', 'moodleoverflow'), ['size' => '64']);
        $mform->setType('name', empty(get_config('moodleoverflow', 'formatstringstriptags')) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // A preset changes basic settings at once, which is only harmless while nothing has been written (e.g. lowering
        // the anonymity), so it is offered as long as the forum has no discussion. The choice itself is not stored.
        if ($this->has_discussions()) {
            return;
        }
        $options = ['' => get_string('preset:custom', 'moodleoverflow')];
        foreach (preset::cases() as $preset) {
            $options[$preset->value] = $preset->get_name();
        }
        $mform->addElement('select', 'preset', get_string('preset', 'moodleoverflow'), $options);
        $mform->addHelpButton('preset', 'preset', 'moodleoverflow');
        $mform->addElement('html', $OUTPUT->render_from_template('mod_moodleoverflow/form_presets', [
            'presets' => array_map(
                fn(preset $preset) => ['key' => $preset->value, 'name' => $preset->get_name(),
                    'description' => $preset->get_description()],
                preset::cases()
            ),
        ]));

        $settings = [];
        foreach (preset::cases() as $preset) {
            $settings[$preset->value] = $preset->get_settings();
        }
        $PAGE->requires->js_call_amd('mod_moodleoverflow/presets', 'init', [$settings]);
    }

    /**
     * How people post: anonymity, review, length of posts and the times in which answers are allowed.
     */
    private function add_posting_elements(): void {
        global $OUTPUT;
        $mform = $this->_form;
        $mform->addElement('header', 'postingheading', get_string('formposting', 'moodleoverflow'));
        $mform->setExpanded('postingheading');

        // Once there are discussions, anonymity can only be increased: lowering it would reveal the names of people who
        // wrote because they trusted to stay hidden. A forum without discussions offers every level.
        if (get_config('moodleoverflow', 'allowanonymous') == '1') {
            $lowest = $this->has_discussions() ? (int) ($this->current->anonymous ?? 0) : anonymity::NONE->value;
            $names = [
                anonymity::NONE->value => get_string('no'),
                anonymity::QUESTIONS->value => get_string('anonymous:only_questions', 'moodleoverflow'),
                anonymity::EVERYTHING->value => get_string('anonymous:everything', 'moodleoverflow'),
            ];
            $options = array_filter($names, fn(int $level) => $level >= $lowest, ARRAY_FILTER_USE_KEY);
            $mform->addElement('select', 'anonymous', get_string('anonymous', 'moodleoverflow'), $options);
            $mform->addHelpButton('anonymous', 'anonymous', 'moodleoverflow');
            $mform->setDefault('anonymous', anonymity::NONE->value);
        }

        if (get_config('moodleoverflow', 'allowreview') == 1) {
            $mform->addElement('select', 'needsreview', get_string('review', 'moodleoverflow'), [
                review_level::NONE->value => get_string('nothing', 'moodleoverflow'),
                review_level::QUESTIONS->value => get_string('questions', 'moodleoverflow'),
                review_level::EVERYTHING->value => get_string('questions_and_posts', 'moodleoverflow'),
            ]);
            $mform->addHelpButton('needsreview', 'review', 'moodleoverflow');
            $mform->setDefault('needsreview', review_level::NONE->value);
        }

        // Minimum and maximum words in one row: "Words per post [ 0 ] to [ 0 ]". The group is a flex row, which drops
        // plain spaces around the separator; the first field brings its own margin, the second needs a fixed space.
        $mform->addGroup([
            $mform->createElement('text', 'wordsmin', get_string('wordsmin', 'moodleoverflow'), ['size' => 4]),
            $mform->createElement('text', 'wordsmax', get_string('wordsmax', 'moodleoverflow'), ['size' => 4]),
        ], 'wordslimits', get_string('wordslimits', 'moodleoverflow'), get_string('wordsto', 'moodleoverflow') . '&nbsp;', false);
        $mform->addHelpButton('wordslimits', 'wordslimits', 'moodleoverflow');
        foreach (['wordsmin', 'wordsmax'] as $field) {
            $mform->setType($field, PARAM_INT);
            $mform->setDefault($field, 0);
        }

        // Answer times. Once there are answers, the start cannot be moved any more.
        $answersfound = $this->get_instance() && moodleoverflow::from_id($this->get_instance())->has_answers();
        if ($answersfound) {
            $warning = get_string('la_warning_answers', 'moodleoverflow') . ' ' .
                get_string('la_warning_conclusion', 'moodleoverflow');
            $mform->addElement('html', $OUTPUT->notification($warning, 'warning', false));
        }
        $mform->addElement('hidden', 'la_answersfound', $answersfound);
        $mform->setType('la_answersfound', PARAM_BOOL);
        $optional = ['optional' => true];
        $mform->addElement('date_time_selector', 'la_starttime', get_string('la_starttime', 'moodleoverflow'), $optional);
        $mform->addHelpButton('la_starttime', 'la_starttime', 'moodleoverflow');
        $mform->disabledIf('la_starttime', 'la_answersfound', 'eq', true);
        $mform->addElement('date_time_selector', 'la_endtime', get_string('la_endtime', 'moodleoverflow'), $optional);
        $mform->addHelpButton('la_endtime', 'la_endtime', 'moodleoverflow');
        $mform->addElement('hidden', 'la_error');
        $mform->setType('la_error', PARAM_TEXT);
    }

    /**
     * Votes, reputation and marks. Settings that only matter with votes and reputation are hidden while they are off;
     * the mark settings stay, because marks work without votes.
     */
    private function add_rating_elements(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'ratingheading', get_string('ratingheading', 'moodleoverflow'));

        // Without the site setting, votes and reputation are always on and there is nothing to choose.
        $canswitchoff = get_config('moodleoverflow', 'allowdisablerating') == 1;
        if ($canswitchoff) {
            $mform->addElement('selectyesno', 'allowrating', get_string('allowrating', 'moodleoverflow'));
            $mform->addHelpButton('allowrating', 'allowrating', 'moodleoverflow');
            $mform->setDefault('allowrating', 1);

            // Reputation comes from votes.
            $mform->addElement('selectyesno', 'allowreputation', get_string('allowreputation', 'moodleoverflow'));
            $mform->addHelpButton('allowreputation', 'allowreputation', 'moodleoverflow');
            $mform->setDefault('allowreputation', 1);
            $mform->hideIf('allowreputation', 'allowrating', 'eq', 0);
        }

        $mform->addElement('advcheckbox', 'coursewidereputation', get_string('coursewidereputation', 'moodleoverflow'));
        $mform->addHelpButton('coursewidereputation', 'coursewidereputation', 'moodleoverflow');
        $mform->setDefault('coursewidereputation', 1);

        $mform->addElement('advcheckbox', 'allownegativereputation', get_string('allownegativereputation', 'moodleoverflow'));
        $mform->addHelpButton('allownegativereputation', 'allownegativereputation', 'moodleoverflow');
        $mform->setDefault('allownegativereputation', 1);

        if ($canswitchoff) {
            // A field with several hideIf conditions is hidden if any of them is true.
            foreach (['coursewidereputation', 'allownegativereputation'] as $name) {
                $mform->hideIf($name, 'allowrating', 'eq', 0);
                $mform->hideIf($name, 'allowreputation', 'eq', 0);
            }
        }
        // Course-wide reputation would reveal authors across anonymous forums.
        if ($mform->elementExists('anonymous')) {
            $mform->hideIf('coursewidereputation', 'anonymous', 'gt', 0);
        }

        $mform->addElement('select', 'ratingpreference', get_string('ratingpreference', 'moodleoverflow'), [
            rating_preference::STARTER->value => get_string('starterrating', 'moodleoverflow'),
            rating_preference::TEACHER->value => get_string('teacherrating', 'moodleoverflow'),
        ]);
        $mform->addHelpButton('ratingpreference', 'ratingpreference', 'moodleoverflow');
        $mform->setDefault('ratingpreference', rating_preference::STARTER->value);

        $mform->addElement('advcheckbox', 'allowmultiplemarks', get_string('allowmultiplemarks', 'moodleoverflow'));
        $mform->addHelpButton('allowmultiplemarks', 'allowmultiplemarks', 'moodleoverflow');
        $mform->setDefault('allowmultiplemarks', 0);
    }

    /**
     * Subscription mode and read tracking.
     */
    private function add_notification_elements(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'subscriptiontrackingheader', get_string('subscriptiontrackingheader', 'moodleoverflow'));

        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'moodleoverflow'), [
            subscription_mode::OPTIONAL->value => get_string('subscriptionoptional', 'moodleoverflow'),
            subscription_mode::FORCED->value => get_string('subscriptionforced', 'moodleoverflow'),
            subscription_mode::AUTO->value => get_string('subscriptionauto', 'moodleoverflow'),
            subscription_mode::DISABLED->value => get_string('subscriptiondisabled', 'moodleoverflow'),
        ]);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'moodleoverflow');

        $forcedallowed = (bool) get_config('moodleoverflow', 'allowforcedreadtracking');
        $options = [
            tracking_type::OPTIONAL->value => get_string('trackingoptional', 'moodleoverflow'),
            tracking_type::OFF->value => get_string('trackingoff', 'moodleoverflow'),
        ];
        if ($forcedallowed) {
            $options[tracking_type::FORCED->value] = get_string('trackingon', 'moodleoverflow');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'moodleoverflow'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'moodleoverflow');
        $default = get_config('moodleoverflow', 'trackingtype');
        $mform->setDefault('trackingtype', !$forcedallowed && $default == tracking_type::FORCED->value
            ? tracking_type::OPTIONAL->value : $default);
    }

    /**
     * Grades from the reputation. Everything but the maximum grade is hidden until a maximum grade is set.
     */
    private function add_grade_elements(): void {
        global $COURSE;
        $mform = $this->_form;
        $mform->addElement('header', 'gradeheading', get_string('gradenoun'));

        $number = '/^(0|[1-9][0-9]*)$/';
        $mform->addElement('text', 'grademaxgrade', get_string('modgrademaxgrade', 'grades'), ['size' => 4]);
        $mform->setType('grademaxgrade', PARAM_INT);
        $mform->addRule('grademaxgrade', get_string('grademaxgradeerror', 'moodleoverflow'), 'regex', $number, 'client');

        $mform->addElement('text', 'gradescalefactor', get_string('scalefactor', 'moodleoverflow'), ['size' => 4]);
        $mform->addHelpButton('gradescalefactor', 'scalefactor', 'moodleoverflow');
        $mform->setType('gradescalefactor', PARAM_INT);
        $mform->addRule('gradescalefactor', get_string('scalefactorerror', 'moodleoverflow'), 'regex', $number, 'client');

        $mform->addElement('text', 'gradepass', get_string('gradepass', 'grades'), ['size' => 4]);
        $mform->addHelpButton('gradepass', 'gradepass', 'grades');
        $mform->setType('gradepass', PARAM_INT);
        $mform->addRule('gradepass', get_string('gradepasserror', 'moodleoverflow'), 'regex', $number, 'client');

        $dependent = ['gradescalefactor', 'gradepass'];
        if ($this->_features->gradecat) {
            $mform->addElement(
                'select',
                'gradecat',
                get_string('gradecategoryonmodform', 'grades'),
                grade_get_categories_menu($COURSE->id, $this->_outcomesused)
            );
            $mform->addHelpButton('gradecat', 'gradecategoryonmodform', 'grades');
            $dependent[] = 'gradecat';
        }
        foreach ($dependent as $field) {
            $mform->hideIf($field, 'grademaxgrade', 'in', [0, '']);
        }
    }

    /**
     * Attachments. The size limit is hidden while attachments are not allowed.
     */
    private function add_attachment_elements(): void {
        global $CFG, $COURSE;
        $mform = $this->_form;
        $mform->addElement('header', 'attachmentshdr', get_string('attachments', 'moodleoverflow'));

        $choices = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 50, 100];
        $choices = array_combine($choices, $choices);
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'moodleoverflow');
        $mform->setDefault('maxattachments', get_config('moodleoverflow', 'maxattachments'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, get_config('moodleoverflow', 'maxbytes'));
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'moodleoverflow'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'moodleoverflow');
        $mform->setDefault('maxbytes', get_config('moodleoverflow', 'maxbytes'));
        $mform->hideIf('maxbytes', 'maxattachments', 'eq', 0);
    }

    /**
     * Handles data postprocessing.
     *
     * @param array $data data from the form.
     */
    public function data_postprocessing($data) {
        if (isset($data->anonymous) && $data->anonymous != anonymity::NONE->value) {
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

        // Validate the word limits, 0 means no limit. They share one row, so the errors belong to the group.
        $wordsmin = (int) ($data['wordsmin'] ?? 0);
        $wordsmax = (int) ($data['wordsmax'] ?? 0);
        if ($wordsmin < 0 || $wordsmax < 0) {
            $errors['wordslimits'] = get_string('errorwordslimitnegative', 'moodleoverflow');
        } else if ($wordsmax > 0 && $wordsmax < $wordsmin) {
            $errors['wordslimits'] = get_string('errorwordsmaxbelowmin', 'moodleoverflow');
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
