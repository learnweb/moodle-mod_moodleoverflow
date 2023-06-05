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
 * PHP Unit test for post related functions in the locallib.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../locallib.php');

/**
 * PHP Unit test for post related functions in the locallib.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_test extends \advanced_testcase {

    /** @var \stdClass test course */
    private $course;

    /** @var \stdClass coursemodule */
    private $coursemodule;

    /** @var \stdClass test moodleoverflow */
    private $moodleoverflow;

    /** @var \stdClass test teacher */
    private $teacher;

    /** @var \stdClass a discussion */
    private $discussion;

    /** @var \stdClass a post */
    private $post;

    /** @var \stdClass an attachment */
    private $attachment;

    /** @var \mod_moodleoverflow_generator $generator */
    private $generator;


    public function setUp(): void {
        $this->resetAfterTest();
        $this->helper_course_set_up();
    }

    public function tearDown(): void {
        // Clear all caches.
        \mod_moodleoverflow\subscriptions::reset_moodleoverflow_cache();
        \mod_moodleoverflow\subscriptions::reset_discussion_cache();
    }

    /**
     * Test if a post and its attachment are deleted successfully.
     * @covers ::moodleoverflow_delete_post
     */
    public function test_moodleoverflow_delete_post() {
        global $DB;

        $result = 0;
        // The attachment should exist.
        if ($DB->get_record('files', array('itemid' => $this->post->id))) {
            $result = 1;
        }
        $this->assertEquals(1, $result);

        // Delete the post from the teacher with its attachment.
        moodleoverflow_delete_post($this->post, false, $this->coursemodule, $this->moodleoverflow);

        // Now try to get the attachment.
        if (!$DB->get_record('files', array('itemid' => $this->post->id))) {
            $result = 2;
        }
        $this->assertEquals(2, $result);
    }

    /**
     * Test if a post and its attachment are deleted successfully.
     * @covers ::moodleoverflow_delete_discussion
     */
    public function test_moodleoverflow_delete_discussion() {
        global $DB;

        $result = 0;
        // The attachment should exist.
        if ($DB->get_record('files', array('itemid' => $this->post->id))) {
            $result = 1;
        }
        $this->assertEquals(1, $result);

        // Delete the post from the teacher with its attachment.
        moodleoverflow_delete_discussion($this->discussion[0], $this->course, $this->coursemodule, $this->moodleoverflow);

        // Now try to get the attachment.
        if (!$DB->get_record('files', array('itemid' => $this->post->id))) {
            $result = 2;
        }
        $this->assertEquals(2, $result);
    }

    /**
     * This function creates:
     * - a course with a moodleoverflow
     * - a new discussion with a post. The post has an attachment.
     */
    private function helper_course_set_up() {
        global $DB;
        // Create a new course with a moodleoverflow forum.
        $this->course = $this->getDataGenerator()->create_course();
        $location = array('course' => $this->course->id);
        $this->moodleoverflow = $this->getDataGenerator()->create_module('moodleoverflow', $location);
        $this->coursemodule = get_coursemodule_from_instance('moodleoverflow', $this->moodleoverflow->id);

        // Create a teacher.
        $this->teacher = $this->getDataGenerator()->create_user(array('firstname' => 'Tamaro', 'lastname' => 'Walter'));
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'student');

        // Create a discussion started from the teacher.
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->discussion = $this->generator->post_to_forum($this->moodleoverflow, $this->teacher);
        $this->post = $DB->get_record('moodleoverflow_posts', array('id' => $this->discussion[0]->firstpost), '*');

        // Create an attachment by inserting it directly in the database and update the post record.
        $this->attachment = new \stdClass();
        $this->attachment->contenthash = '81a897de6707916841bcafa3fb853377086744ba';
        $this->attachment->pathnamehash = 'bb9fe5ed6ab47359546f7df8858263d9c6814646';
        $modulecontext = \context_module::instance($this->coursemodule->id);
        $this->attachment->contextid = $modulecontext->id;
        $this->attachment->component = 'mod_moodleoverflow';
        $this->attachment->filearea = 'attachment';
        $this->attachment->itemid = $this->post->id;
        $this->attachment->filepath = '/';
        $this->attachment->filename = 'thisfile.png';
        $this->attachment->userid = $this->teacher->id;
        $this->attachment->filesize = 129595;
        $this->attachment->mimetype = 'image/png';
        $this->attachment->status = 0;
        $this->attachment->source = 'thisfile.png';
        $this->attachment->author = $this->teacher->firstname . ' ' . $this->teacher->lastname;
        $this->attachment->license = 'unknown';
        $this->attachment->timecreated = $this->post->created;
        $this->attachment->timemodified = $this->post->modified;
        $this->attachment->sortorder = 0;
        $this->attachment->referencefileid = null;
        $DB->insert_record('files', $this->attachment);

        $this->post->attachment = 1;
        $DB->update_record('moodleoverflow_posts', $this->post);

    }
}
