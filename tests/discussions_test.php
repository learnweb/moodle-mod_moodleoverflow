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
 * PHPUnit Tests for testing discussion retrieval
 *
 * @package   mod_moodleoverflow
 * @copyright 2020 Jan Dageförde <jan@dagefor.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * PHPUnit Tests for testing discussion retrieval
 *
 * @package   mod_moodleoverflow
 * @copyright 2020 Jan Dageförde <jan@dagefor.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 //basic_testcase --> no database changes or edits 
 //advanced_testcase --> with database changes and edits

 //class is a testcase, meaning a collection of usecases in a certain category
class mod_moodleoverflow_discussions_testcase extends advanced_testcase {


	//function represents a usecase in the category
	/*
    public function test_a_fresh_forum_has_an_empty_discussion_list() {

		//assert determains value that must be true
		// can a dicussion be found? here it searches for empty
        $this->assertEquals(count($discussions), 0);
    }
	*/

    public function test_a_discussion_can_be_retrieved() {
		$this->resetAfterTest();

		$user = $this->getDataGenerator()->create_user();

		$course = $this->getDataGenerator()->create_course();
		$moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);

		// todo: discussion erzeugen
		// todo: add plugin DataGenerator from mooodleoverflow
		$plugindatagernator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
		$plugindatagernator -> create_discussion(['course' => $course->id, 'moodleoverflow' => $moodleoverflow->id, 'userid' => $user->id], $moodleoverflow);

		$coursemodule = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
		$discussions = moodleoverflow_get_discussions($coursemodule);

		// can a dicussion be found? here it searches for an entry
        $this->assertEquals(1, count($discussions));
    }
	
	public function test_new_forum_is_empty() {
		$this->resetAfterTest();

		$course = $this->getDataGenerator()->create_course();
		$moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $course->id]);

		$coursemodule = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id);
		$discussions = moodleoverflow_get_discussions($coursemodule);

		$this->assertEquals(0, count($discussions));
	}
}