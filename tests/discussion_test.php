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
 * PHP Unit Tests for the Discussion class.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;

use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Tests if the functions from the discussion class are working correctly.
 * As the discussion class works as an administrator of the post class, most of the testcases are already realized in the
 * post_test.php file.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\models\discussion
 */
final class discussion_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow test moodleoverflow */
    private $modflow;

    /** @var discussion a discussion of the student */
    private $discussion;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
        $this->discussion = $this->generator->create_question($this->modflow, $this->student)->get_discussion();
    }

    /**
     * Test, if a discussion is being created correctly
     */
    public function test_create_discussion(): void {
        global $DB;

        // Build a prepost object with important information.
        $time = time();
        $prepost = new \stdClass();
        $prepost->userid = $this->student->id;
        $prepost->timenow = $time;
        $prepost->message = 'a message';
        $prepost->messageformat = 1;
        $prepost->reviewed = 0;
        $prepost->formattachments = null;
        $prepost->modulecontext = $this->modflow->get_context();

        // Log in as the student.
        $this->setUser($this->student);

        // Build a new discussion object.
        $discussion = discussion::construct_without_id(
            $this->course->id,
            $this->modflow->id,
            'Discussion Topic',
            0,
            $this->student->id,
            $time,
            $time,
            $this->student->id
        );
        $discussionid = $discussion->add($prepost);
        $posts = $discussion->get_posts();
        $post = $posts[$discussion->get_firstpostid()];

        // The discussion and the firstpost should be in the DB.
        $dbdiscussion = $DB->get_record('moodleoverflow_discussions', ['id' => $discussion->get_id()]);
        $this->assertEquals($dbdiscussion->id, $discussionid);
        $this->assertEquals('Discussion Topic', $dbdiscussion->name);

        $dbpost = $DB->get_record('moodleoverflow_posts', ['id' => $discussion->get_firstpostid()]);
        $this->assertEquals($dbpost->id, $post->get_id());
        $this->assertEquals($dbpost->discussion, $post->get_discussionid());
        $this->assertEquals($prepost->message, $dbpost->message);
    }

    /**
     * Test, if a post and its attachment are deleted successfully.
     * @covers \mod_moodleoverflow\local\models\discussion::delete_post_from_discussion
     */
    public function test_delete_discussion(): void {
        global $DB;
        // Build the prepost object with necessary information.
        $prepost = new \stdClass();
        $prepost->modulecontext = $this->modflow->get_context();

        // Delete the discussion, but save the IDs first.
        $discussionid = $this->discussion->get_id();
        $postid = $this->discussion->get_firstpostid();
        $this->discussion->delete($prepost, $this->student->id);

        // The discussion and the post should not be in the DB anymore.
        $discussion = count($DB->get_records('moodleoverflow_discussions', ['id' => $discussionid]));
        $this->assertEquals(0, $discussion);

        $post = count($DB->get_records('moodleoverflow_posts', ['id' => $postid]));
        $this->assertEquals(0, $post);
    }
}
