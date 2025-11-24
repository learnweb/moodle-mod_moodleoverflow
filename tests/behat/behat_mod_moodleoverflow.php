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
 * Steps definitions related with the moodleoverflow activity.
 *
 * @package    mod_moodleoverflow
 * @category   test
 * @copyright  2017 KennetWinter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

/**
 * moodleoverflow-related steps definitions.
 *
 * @package    mod_moodleoverflow
 * @category   test
 * @copyright  2017 KennetWinter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_moodleoverflow extends behat_base {
    /**
     * Prepares a basic background for moodleoverflow features.
     *
     * @Given /^I prepare a moodleoverflow feature background with users:$/
     * @return void
     */
    #[\core\attribute\example('I prepare a moodleoverflow feature background with users:
        | username | firstname | lastname | email             | idnumber | role    |
        | student1 | Student   | One      | student1@mail.com | 10       | student |
        | teacher  | Teacher   | One      | teacher@mail.com  | 50       | teacher |')]
    public function i_prepare_a_moodleoverflow_background(TableNode $data): void {
        $rows = $data->getHash();

        // Create one course.
        $this->execute('behat_data_generators::the_following_entities_exist', ['courses', new TableNode([
            ['fullname', 'shortname', 'category'],
            ['Course 1', 'C1', '0'],
        ])]);

        // Create and enrol user in course.
        foreach ($rows as $row) {
            $this->execute('behat_data_generators::the_following_entities_exist', ['users', new TableNode([
                ['username', 'firstname', 'lastname', 'email', 'idnumber'],
                [$row['username'], $row['firstname'], $row['lastname'], $row['email'], $row['idnumber']],
            ])]);
            $this->execute('behat_data_generators::the_following_entities_exist', ['course enrolments', new TableNode([
                ['user', 'course', 'role'],
                [$row['username'], 'C1', $row['role']],
            ])]);
        }
    }

    /**
     * Build a background for moodleoverflow tests.
     * Builds:
     * - A course
     * - One teacher and one student, both enrolled in the course
     * - A moodleoverflow activity
     * - A discussion started by the teacher
     * - A reply to the discussion by the student
     *
     * @Given /^I add a moodleoverflow discussion with posts from different users$/
     * @return void
     */
    public function i_add_a_moodleoverflow_discussion_with_posts_from_different_users(): void {
        global $DB;
        $this->i_prepare_a_moodleoverflow_background(new TableNode([
            ['username', 'firstname', 'lastname', 'email', 'idnumber', 'role'],
            ['teacher1', 'Tamaro', 'Walter', 'tamaro@mail.com', '10', 'teacher'],
            ['student1', 'John', 'Smith', 'john@mail.com', '11', 'student'],
        ]));

        $course = $DB->get_record('course', ['shortname' => 'C1']);
        $teacher = $DB->get_record('user', ['username' => 'teacher1']);
        $student = $DB->get_record('user', ['username' => 'student1']);
        $time = time();

        // Create activity.
        $this->execute('behat_data_generators::the_following_entities_exist', ['activities', new TableNode([
            ['activity', 'course', 'name'],
            ['moodleoverflow', 'C1', 'Moodleoverflow 1'],
        ])]);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['name' => 'Moodleoverflow 1']);

        // Create discussion.
        $discussionid = $DB->insert_record('moodleoverflow_discussions', ['course' => $course->id,
            'moodleoverflow' => $moodleoverflow->id, 'name' => 'Discussion 1', 'firstpost' => 1, 'userid' => $teacher->id,
            'timemodified' => $time, 'timestart' => $time, 'usermodified' => $teacher->id,
        ]);

        // Create teacher's post.
        $teacherpostid = $DB->insert_record('moodleoverflow_posts', ['discussion' => $discussionid,
            'moodleoverflow' => $moodleoverflow->id, 'parent' => 0, 'userid' => $teacher->id, 'created' => $time,
            'modified' => $time, 'message' => 'Message from teacher', 'messageformat' => 1, 'attachment' => '', 'mailed' => 1,
            'reviewed' => '1', 'timereviewed' => null,
        ]);

        // Update firstpost field in discussion.
        $record = $DB->get_record('moodleoverflow_discussions', ['id' => $discussionid]);
        $record->firstpost = $teacherpostid;
        $DB->update_record('moodleoverflow_discussions', $record);

        // Create student reply.
        $DB->insert_record('moodleoverflow_posts', ['discussion' => $discussionid, 'moodleoverflow' => $moodleoverflow->id,
            'parent' => $teacherpostid, 'userid' => $student->id, 'created' => $time, 'modified' => $time,
            'message' => 'Answer from student', 'messageformat' => 1, 'attachment' => '', 'mailed' => 1, 'reviewed' => '1',
            'timereviewed' => null,
        ]);
    }

    /**
     * The admins adds a moodleoverflow discussions with a tracking type. Used in the track_read_posts_feature.
     *
     * @Given /^The admin posts "(?P<subject>[^"]*)" in "(?P<name>[^"]*)" with tracking type "(?P<trackingtype>[^"]*)"$/
     *
     * @param string $subject
     * @param string $name
     * @param string $trackingtype
     * @return void
     */
    public function admin_adds_moodleoverflow_with_tracking_type(string $subject, string $name, string $trackingtype): void {
        $this->execute('behat_data_generators::the_following_entities_exist', ['activities', new TableNode([
            ['activity', 'course', 'name', 'intro', 'trackingtype'],
            ['moodleoverflow', 'C1', $name, 'Test moodleoverflow description', $trackingtype],
        ])]);
        $this->execute('behat_auth::i_log_in_as', 'admin');
        $this->execute('behat_navigation::i_am_on_course_homepage', 'Course 1');
        $this->i_add_a_moodleoverflow_discussion_to_moodleoverflow_with($name, new TableNode([
            ['Subject', $subject],
            ['Message', 'Test post message'],
        ]));
    }

    /**
     * Logs in as a user and navigates in a course to dedicated moodleoverflow discussion.
     *
     * @Given /^I navigate as "(?P<user>[^"]*)" to "(?P<course>[^"]*)" "(?P<modflow>[^"]*)" "(?P<discussion>[^"]*)"$/
     *
     * @param string $user The username to log in as
     * @param string $course The full name of the course
     * @param string $modflow The name of the moodleoverflow activity
     * @param string $discussion The name of the discussion
     * @return void
     * @throws Exception
     */
    public function i_navigate_as_user_to_the_discussion(string $user, string $course, string $modflow, string $discussion): void {
        $this->execute('behat_auth::i_log_in_as', $user);
        $this->execute('behat_navigation::i_am_on_course_homepage', $course);
        $this->execute('behat_general::click_link', $this->escape($modflow));

        // Make the navigation to the discussion optional.
        if ($discussion != "") {
            $this->execute('behat_general::click_link', $this->escape($discussion));
        }
    }

    /**
     * Clicks on the delete button of a moodleoverflow post.
     *
     * @Given /^I try to delete moodleoverflow post "(?P<postmessage_string>(?:[^"]|\\")*)"$/
     * @param string $postmessage
     * @return void
     * @throws Exception
     */
    public function i_try_to_delete_moodleoverflow_post(string $postmessage): void {
        // Find the div containing the post message and click the delete link within it.
        $this->execute('behat_general::i_click_on', [
            "//div[contains(@class, 'moodleoverflowpost')][contains(., '" . $this->escape($postmessage) . "')]//a[text()='Delete']",
            "xpath_element",
        ]);
        $this->execute('behat_general::i_click_on', ['Continue', 'button']);
    }

    /**
     * Clicks on the comment button of a moodleoverflow post
     *
     * @Given /^I comment "(?P<postmessage_string>(?:[^"]|\\")*)" with "(?P<replymessage_string>(?:[^"]|\\")*)"$/
     * @param string $postmessage
     * @param string $replymessage
     * @return void
     * @throws Exception
     */
    public function i_comment_moodleoverflow_post_with_message(string $postmessage, string $replymessage): void {
        $this->execute('behat_general::i_click_on', [
            "//div[contains(@class, 'moodleoverflowpost')][contains(., '" . $this->escape($postmessage) .
                "')]//a[text()='Comment']",
            "xpath_element",
        ]);
        $table = new TableNode([
            ['Message', $replymessage],
        ]);
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('posttomoodleoverflow', 'moodleoverflow'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * Adds a new moodleoverflow to the specified course and section.
     *
     * @Given I add a moodleoverflow to course :coursefullname section :sectionnum and I fill the form with:
     * @param string $courseshortname
     * @param string $sectionnumber
     * @param TableNode $data
     */
    public function i_add_moodleoverflow_to_course_and_fill_form(string $courseshortname, string $sectionnumber, TableNode $data) {
        global $CFG;

        if ($CFG->branch >= 404) {
            $this->execute(
                "behat_course::i_add_to_course_section_and_i_fill_the_form_with",
                [$this->escape('moodleoverflow'), $this->escape($courseshortname), $this->escape($sectionnumber), $data]
            );
        } else {
            // This is the code from the deprecated behat function "i_add_to_section_and_i_fill_the_form_with".
            // Add activity to section.
            $this->execute(
                "behat_course::i_add_to_section",
                [$this->escape('moodleoverflow'), $this->escape($sectionnumber)]
            );

            // Wait to be redirected.
            $this->execute('behat_general::wait_until_the_page_is_ready');

            // Set form fields.
            $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $data);

            // Save course settings.
            $this->execute("behat_forms::press_button", get_string('savechangesandreturntocourse'));
        }
    }

    /**
     * Adds a discussion to the moodleoverflow specified by it's name with the provided table data
     * (usually Subject and Message). The step begins from the moodleoverflow's course page.
     *
     * @Given /^I add a new discussion to "(?P<moodleoverflow_name_string>(?:[^"]|\\")*)" moodleoverflow with:$/
     * @param string    $moodleoverflowname
     * @param TableNode $table
     */
    public function i_add_a_moodleoverflow_discussion_to_moodleoverflow_with($moodleoverflowname, TableNode $table) {
        $this->add_new_discussion($moodleoverflowname, $table, get_string('addanewdiscussion', 'moodleoverflow'));
    }

    /**
     * Adds a reply to the starter post of the specified moodleoverflow.
     * The step begins from the moodleoverflow's page or from the moodleoverflow's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post
     * from "(?P<moodleoverflow_name_string>(?:[^"]|\\")*)" moodleoverflow with:$/
     *
     * @param string    $postsubject        The subject of the post
     * @param string    $moodleoverflowname The moodleoverflow name
     * @param TableNode $table
     */
    public function i_reply_post_from_moodleoverflow_with($postsubject, $moodleoverflowname, TableNode $table) {
        // Navigate to moodleoverflow.
        $this->execute('behat_general::click_link', $this->escape($moodleoverflowname));
        $this->execute('behat_general::click_link', $this->escape($postsubject));
        $this->execute('behat_general::click_link', get_string('reply', 'moodleoverflow'));

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('posttomoodleoverflow', 'moodleoverflow'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    // phpcs:disable moodle.Files.LineLength.TooLong
    /**
     * Checks that an element and selector type exists in another element and selector type on the current page.
     *
     * This step is for advanced users, use it if you don't find anything else suitable for what you need.
     *
     * @Given :element :selectortype should :text exist in the :discussiontitle and :optdiscussion moodleoverflow discussion card
     *
     * @param string $element The locator of the specified selector
     * @param string $selectortype The selector type
     * @param string $text Type not if the element should not exist, "" if it should
     * @param string $discussiontitle The discussion title
     * @param string $optdiscussion An optional second discussion to check
     */
    public function should_exist_in_the_moodleoverflow_discussion_card($element, $selectortype, $text, $discussiontitle, string $optdiscussion) {
        // phpcs:enable
        $containernode = $this->find_moodleoverflow_discussion_card($discussiontitle);

        if ($text === "not") {
            try {
                $this->find($selectortype, $element, false, $containernode, behat_base::get_reduced_timeout());
            } catch (ElementNotFoundException $e) {
                return; // Element not found as expected.
            }
            throw new ExpectationException(
                "The '{$element}' '{$selectortype}' exists in the '{$discussiontitle}' moodleoverflow discussion card",
                $this->getSession()
            );
        }

        $exception = new ElementNotFoundException(
            $this->getSession(),
            $selectortype,
            null,
            "$element in the moodleoverflow discussion card."
        );

        $this->find($selectortype, $element, $exception, $containernode);

        // Search for a second discussion if provided.
        if ($optdiscussion !== "") {
            $this->find_moodleoverflow_discussion_card($optdiscussion);
        }
    }

    // phpcs:disable moodle.Files.LineLength.TooLong
    /**
     * Click on the element of the specified type which is located inside the second element.
     *
     * @When /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" in the "(?P<element2_string>(?:[^"]|\\")*)" moodleoverflow discussion card$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     * @param string $discussiontitle The discussion title
     */
    public function i_click_on_in_the_moodleoverflow_discussion_card($element, $selectortype, $discussiontitle) {
        // phpcs:enable
        // Get the container node.
        $containernode = $this->find_moodleoverflow_discussion_card($discussiontitle);

        // Specific exception giving info about where can't we find the element.
        $exception = new ElementNotFoundException(
            $this->getSession(),
            $selectortype,
            null,
            "$element in the moodleoverflow discussion card."
        );

        // Looks for the requested node inside the container node.
        $node = $this->find($selectortype, $element, $exception, $containernode);
        $this->ensure_node_is_visible($node);
        $node->click();
    }

    /**
     * Sets the limited answer starttime attribute of a moodleoverflow to the current time.
     *
     * @Given I set the :activity moodleoverflow limitedanswerstarttime to now
     * @param string $activity
     * @return void
     */
    public function i_set_the_moodleoverflow_limitedanswerstarttime_to_now($activity): void {
        global $DB;

        if (!$activityrecord = $DB->get_record('moodleoverflow', ['name' => $activity])) {
            throw new Exception("Activity '$activity' not found");
        }
        // Update the specified field.
        $activityrecord->la_starttime = time();
        $DB->update_record('moodleoverflow', $activityrecord);
    }

    /**
     * Follows a sequence of clicks elements.
     *
     * @Given /^I click in moodleoverflow on "(?P<text_string>(?:[^"]|\\")*)" type:$/
     * @param string $type Type of element like "text", "checkbox" or "button".
     * @param TableNode $data
     * @return void
     * @throws DriverException
     */
    #[\core\attribute\example('I click in moodleoverflow on:
        | Test moodleoverflow | Topic 1 |')]
    public function i_click_in_moodleoverflow_on(string $type, TableNode $data): void {
        $elements = $data->getRow(0);
        foreach ($elements as $element) {
            $this->execute('behat_general::i_click_on', [$element, $type]);
        }
    }

    /**
     * Checks if a group of elements can be seen.
     *
     * @Given /^I should "(?P<text_string>(?:[^"]|\\")*)" see the elements:$/
     * @param string $text Type "not" when elements should not be visible, "" if they should be visible
     * @param TableNode $data
     * @return void
     */
    #[\core\attribute\example('I should see the elements:
        | Element 1 | Element 2 |')]
    public function i_see_elements_in_moodleoverflow(string $text, TableNode $data): void {
        $elements = $data->getRow(0);
        foreach ($elements as $element) {
            if ($text == "not") {
                $this->execute('behat_general::assert_page_not_contains_text', [$element]);
            } else {
                $this->execute('behat_general::assert_page_contains_text', [$element]);
            }
        }
    }

    // Internal helper functions.

    /**
     * Returns the steps list to add a new discussion to a moodleoverflow.
     *
     * Abstracts add a new topic and add a new discussion, as depending
     * on the moodleoverflow type the button string changes.
     *
     * @param string    $moodleoverflowname
     * @param TableNode $table
     * @param string    $buttonstr
     */
    protected function add_new_discussion($moodleoverflowname, TableNode $table, $buttonstr) {
        // Navigate to moodleoverflow.
        $this->execute('behat_navigation::i_am_on_page_instance', [$this->escape($moodleoverflowname),
            'moodleoverflow activity', ]);
        $this->execute('behat_forms::press_button', $buttonstr);

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string(
            'posttomoodleoverflow',
            'moodleoverflow'
        ));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * Gets the container node.
     * @param string $discussiontitle
     */
    protected function find_moodleoverflow_discussion_card(string $discussiontitle): \Behat\Mink\Element\Element {
        return $this->find(
            'xpath',
            '//*[contains(concat(" ",normalize-space(@class)," ")," moodleoverflowdiscussion ")][.//*[text()="' .
            $discussiontitle . '"]]'
        );
    }
}
