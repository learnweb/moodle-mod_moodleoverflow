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
use mod_moodleoverflow\post\post_control;
use mod_moodleoverflow\readtracking;
use mod_moodleoverflow\subscriptions;

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

        subscriptions::reset_moodleoverflow_cache();
        subscriptions::reset_discussion_cache();
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
     * The admin adds a moodleoverflow discussions with a tracking type. Used in the track_read_posts_feature.
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
        $this->automatic_add_discussion('admin', $name, $subject, 'Test post message');
    }

    /**
     * The admin adds a moodleoverflow discussion.
     *
     * @Given /^The admin posts "(?P<subject>[^"]*)" in "(?P<name>[^"]*)"$/
     *
     * @param string $subject
     * @param string $name
     * @return void
     */
    public function admin_adds_discussion(string $subject, string $name): void {
        $this->automatic_add_discussion('admin', $name, $subject, 'Test post message');
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
        $this->execute(
            "behat_course::i_add_to_course_section_and_i_fill_the_form_with",
            [$this->escape('moodleoverflow'), $this->escape($courseshortname), $this->escape($sectionnumber), $data]
        );
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
        // Navigate to moodleoverflow.
        $this->execute('behat_navigation::i_am_on_page_instance', [$this->escape($moodleoverflowname),
            'moodleoverflow activity', ]);
        $this->execute('behat_forms::press_button', get_string('addanewdiscussion', 'moodleoverflow'));

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string(
            'posttomoodleoverflow',
            'moodleoverflow'
        ));
        $this->execute('behat_general::i_wait_to_be_redirected');
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

    /**
     * Automatically adds a discussion to the DB without clicking all over the behat site. Used to minimize test cases that test
     * other things than the post.php itself.
     *
     * IMPORTANT!: Please note that this function is for testing purposes only, as the objects get searched by string values that
     *             need to be unique in the testing scenario
     * @Given User :username adds to :modflowname a discussion with topic :subject and message :message automatically
     * @param string $username User that adds the discussin
     * @param string $modflowname Name of the moodleoverflow
     * @param string $subject Topic of the discussion
     * @param string $message Message of the first post in the discussion
     * @return void
     * @throws dml_exception
     */
    public function automatic_add_discussion(string $username, string $modflowname, string $subject, string $message): void {
        global $DB;
        $user = $DB->get_record('user', ['username' => $username]);
        $moodleoverflow = $DB->get_record('moodleoverflow', ['name' => $modflowname]);
        $this->set_user($user);

        $postcontrol = new post_control();
        $postcontrol->detect_interaction((object) ['create' => $moodleoverflow->id, 'reply' => 0, 'edit' => 0, 'delete' => 0]);

        // Create the form the user filled in.
        $form = (object) [
            'attachments' => 0,
            'subject' => $subject,
            'message' => [
                'text' => $message,
                'format' => editors_get_preferred_format(),
            ],
            'moodleoverflow' => $moodleoverflow->id,
        ];
        $postcontrol->execute_interaction($form);
    }

    /**
     * Automatically adds a reply to a post the DB without clicking all over the behat site. Used to minimize test cases that test
     * other things than the post.php itself.
     *
     * IMPORTANT!: Please note that this function is for testing purposes only, as the objects get searched by string values that
     *             need to be unique in the testing scenario
     * @Given User :username replies :parentmessage with :message automatically
     * @param string $username User that adds the discussin
     * @param string $parentmessage Name of the moodleoverflow
     * @param string $message Message of the first post in the discussion
     * @return void
     */
    public function automatic_add_reply(string $username, string $parentmessage, string $message): void {
        global $DB;
        $user = $DB->get_record('user', ['username' => $username]);
        $this->set_user($user);
        $sql = "SELECT * FROM {moodleoverflow_posts} WHERE " .
            $DB->sql_compare_text('message') . " = " .
            $DB->sql_compare_text(':message');
        $parentpost = $DB->get_record_sql($sql, ['message' => $parentmessage]);
        $discussion = $DB->get_record('moodleoverflow_discussions', ['id' => $parentpost->discussion]);
        $postcontrol = new post_control();
        $postcontrol->detect_interaction((object) ['create' => 0, 'reply' => $parentpost->id, 'edit' => 0, 'delete' => 0]);

        $form = (object) [
            'attachments' => 0,
            'subject' => $discussion->name,
            'message' => [
                'text' => $message,
                'format' => editors_get_preferred_format(),
            ],
            'reply' => $parentpost->id,
            'parent' => $parentpost->id,
            'discussion' => $discussion->id,
        ];
        $postcontrol->execute_interaction($form);
    }

    /**
     * Checks if the current user is subscribed to a moodleoverflow.
     * @Given I should :type be subscribed to :modflowname
     * @param string $type "not" is the user should not be subscribed, empty if user should be subscribed
     * @param string $modflowname
     * @return void
     */
    public function should_be_subscribed(string $type, string $modflowname) {
        global $DB, $USER;
        $moodleoverflow = $DB->get_record('moodleoverflow', ['name' => $modflowname]);
        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
        if ($type == 'not') {
            if (subscriptions::is_subscribed($USER->id, $moodleoverflow, context_module::instance($cm->id))) {
                throw new Exception("User should not be subscribed but is already subscribed");
            }
        } else {
            $params = ['moodleoverflow' => $moodleoverflow->id, 'userid' => $USER->id];
            if ($DB->count_records('moodleoverflow_subscriptions', $params) != 1) {
                throw new Exception("User should be subscribed but is not");
            }
        }
    }

    /**
     * Checks if the current user has the readtracking on in a moodleoverflow.
     * @Given I should :type have readtracking on in :modflowname
     * @param string $type "not" is the user should not, empty if user should have readtracking on
     * @param string $modflowname
     * @return void
     */
    public function should_be_tracking(string $type, string $modflowname) {
        global $DB;
        $moodleoverflow = $DB->get_record('moodleoverflow', ['name' => $modflowname]);
        if ($type == 'not') {
            if (readtracking::moodleoverflow_is_tracked($moodleoverflow)) {
                throw new Exception("User should not have readtracking on but it is on");
            }
        } else {
            if (!readtracking::moodleoverflow_is_tracked($moodleoverflow)) {
                throw new Exception("User should have readtracking on but it is off");
            }
        }
    }

    /**
     * Set subscription and readtracking for a user in a moodleoverflow.
     * @Given User :username has in :modflowname subscription :substype and readtracking :readtype
     * @param string $username
     * @param string $modflowname
     * @param string $substype "on" or "off"
     * @param string $readtype "on" or "off"
     * @return void
     */
    public function set_subscription_readtracking(string $username, string $modflowname, string $substype, string $readtype): void {
        global $DB;
        $substype = $substype == "on" ? true : false;
        $readtype = $readtype == "on" ? true : false;
        $modflow = $DB->get_record('moodleoverflow', ['name' => $modflowname]);
        $user = $DB->get_record('user', ['username' => $username]);
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);
        $modcontext = context_module::instance($cm->id);
        if ($substype) {
            subscriptions::subscribe_user($user->id, $modflow, $modcontext, true);
        } else {
            subscriptions::unsubscribe_user($user->id, $modflow, $modcontext, true);
        }

        if ($readtype) {
            readtracking::moodleoverflow_start_tracking($modflow->id, $user->id);
        } else {
            readtracking::moodleoverflow_start_tracking($modflow->id, $user->id);
        }
    }

    // Internal helper functions.

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
