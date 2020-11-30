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

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . "/../../../lib/cronlib.php");

class mod_moodleoverflow_search_testcase extends advanced_testcase {

    public function test_for_no_content() {
        $this->resetAfterTest();
        global $CFG;
        $CFG->enableglobalsearch = 1;
        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $search = \core_search\manager::instance();
        $results = $search->search((object)['q' => ""]);
        // Will find the site itself, so 1 result is ok.
        $this->assertEquals(1, count($results));
    }

    public function test_discussion_discussion() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        [$discussion, $post1] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);
        $post2 = $moodleoverflowgen->post_to_discussion($moodleoverflow, $discussion, $user);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $discussion->name]);
        $this->assertEquals(2, count($results));
        $this->assertEquals($post1->id, $results[0]->get('itemid'));
        $this->assertEquals($post2->id, $results[1]->get('itemid'));
    }

    public function test_discussion_post() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        [$discussion, $post] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $post->message]);
        $this->assertEquals(1, count($results));
        $this->assertEquals($post->id, $results[0]->get('itemid'));
    }

    public function test_deleted_discussion() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        [$discussion, $post] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);

        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id);
        moodleoverflow_delete_discussion($discussion, $course, $cm, $moodleoverflow);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $post->message]);
        $this->assertEquals(0, count($results));

        $accessmanager = new \mod_moodleoverflow\search\moodleoverflowposts();
        $access = $accessmanager->check_access($post->id);
        $this->assertEquals(2, $access);
    }

    public function test_deleted_post() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user);

        [$discussion, $post] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);
        $post2 = $moodleoverflowgen->post_to_discussion($moodleoverflow, $discussion, $user);

        $cm = get_coursemodule_from_instance('moodleoverflow', $moodleoverflow->id, $course->id);
        moodleoverflow_delete_post($post2, true, $course, $cm, $moodleoverflow);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $post2->message]);
        $this->assertEquals(0, count($results));

        $accessmanager = new \mod_moodleoverflow\search\moodleoverflowposts();
        $access = $accessmanager->check_access($post2->id);
        $this->assertEquals(2, $access);
    }

    public function test_forbidden_discussion() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user2);

        [$discussion, $post] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $discussion->name]);
        $this->assertEquals(0, count($results));
    }

    public function test_forbidden_post() {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enableglobalsearch = 1;

        $course = $this->getDataGenerator()->create_course();
        $moodleoverflow = $this->getDataGenerator()->create_module("moodleoverflow", array("course" => $course));
        $moodleoverflowgen = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->setUser($user2);

        [$discussion, $post] = $moodleoverflowgen->post_to_forum($moodleoverflow, $user);

        $searchmanager = \core_search\manager::instance();
        $searchmanager->index(true);
        $results = $searchmanager->search((object)['q' => $post->message]);
        $this->assertEquals(0, count($results));

        $accessmanager = new \mod_moodleoverflow\search\moodleoverflowposts();
        $access = $accessmanager->check_access($post->id);
        $this->assertEquals(0, $access);
    }

}