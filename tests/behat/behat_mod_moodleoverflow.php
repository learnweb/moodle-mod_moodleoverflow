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

use Behat\Gherkin\Node\TableNode as TableNode;
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
     * Adds a topic to the moodleoverflow specified by it's name. Useful for the Announcements and blog-style moodleoverflow.
     *
     * @Given /^I add a new topic to "(?P<moodleoverflow_name_string>(?:[^"]|\\")*)" moodleoverflow with:$/
     * @param string    $moodleoverflowname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_moodleoverflow_with($moodleoverflowname, TableNode $table) {
        $this->add_new_discussion($moodleoverflowname, $table, get_string('addanewtopic', 'moodleoverflow'));
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
     * Adds a reply to the specified post of the specified moodleoverflow.
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
            'moodleoverflow activity']);
        $this->execute('behat_forms::press_button', $buttonstr);

        // Fill form and post.
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', get_string('posttomoodleoverflow',
            'moodleoverflow'));
        $this->execute('behat_general::i_wait_to_be_redirected');
    }

    /**
     * Gets the container node.
     * @param string $discussiontitle
     */
    protected function find_moodleoverflow_discussion_card(string $discussiontitle): \Behat\Mink\Element\Element {
        return $this->find('xpath',
            '//*[contains(concat(" ",normalize-space(@class)," ")," moodleoverflowdiscussion ")][.//*[text()="'.
            $discussiontitle . '"]]');
    }

    /**
     * Checks that an element and selector type exists in another element and selector type on the current page.
     *
     * This step is for advanced users, use it if you don't find anything else suitable for what you need.
     *
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should exist in the
     *         "(?P<element2_string>(?:[^"]|\\")*)" moodleoverflow discussion card$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $element The locator of the specified selector
     * @param string $selectortype The selector type
     * @param string $discussiontitle The discussion title
     */
    public function should_exist_in_the_moodleoverflow_discussion_card($element, $selectortype, $discussiontitle) {
        // Get the container node.
        $containernode = $this->find_moodleoverflow_discussion_card($discussiontitle);

        // Specific exception giving info about where can't we find the element.
        $exception = new ElementNotFoundException($this->getSession(), $selectortype, null,
        "$element in the moodleoverflow discussion card.");

        // Looks for the requested node inside the container node.
        $this->find($selectortype, $element, $exception, $containernode);
    }

    /**
     * Click on the element of the specified type which is located inside the second element.
     *
     * @When /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" in the
     *                    "(?P<element2_string>(?:[^"]|\\")*)" moodleoverflow discussion card$/
     * @param string $element Element we look for
     * @param string $selectortype The type of what we look for
     * @param string $discussiontitle The discussion title
     */
    public function i_click_on_in_the_moodleoverflow_discussion_card($element, $selectortype, $discussiontitle) {
        // Get the container node.
        $containernode = $this->find_moodleoverflow_discussion_card($discussiontitle);

        // Specific exception giving info about where can't we find the element.
        $exception = new ElementNotFoundException($this->getSession(), $selectortype, null,
            "$element in the moodleoverflow discussion card.");

        // Looks for the requested node inside the container node.
        $node = $this->find($selectortype, $element, $exception, $containernode);
        $this->ensure_node_is_visible($node);
        $node->click();
    }

    /**
     * Checks that an element and selector type does not exist in another element and selector type on the current page.
     *
     * This step is for advanced users, use it if you don't find anything else suitable for what you need.
     *
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should not exist in the
     *         "(?P<element2_string>(?:[^"]|\\")*)" moodleoverflow discussion card$/
     * @throws ExpectationException
     * @param string $element The locator of the specified selector
     * @param string $selectortype The selector type
     * @param string $discussiontitle The discussion title
     */
    public function should_not_exist_in_the_moodleoverflow_discussion_card($element, $selectortype, $discussiontitle) {
        // Get the container node.
        $containernode = $this->find_moodleoverflow_discussion_card($discussiontitle);

        // Will throw an ElementNotFoundException if it does not exist, but, actually it should not exist, so we try &
        // catch it.
        try {
            // Looks for the requested node inside the container node.
            $this->find($selectortype, $element, false, $containernode, behat_base::get_reduced_timeout());
        } catch (ElementNotFoundException $e) {
            // We expect the element to not be found.
            return;
        }

        // The element was found and should not have been. Throw an exception.
        throw new ExpectationException(
            "The '{$element}' '{$selectortype}' exists in the '{$discussiontitle}' moodleoverflow discussion card",
            $this->getSession()
        );
    }
}
