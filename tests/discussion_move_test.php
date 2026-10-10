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

namespace mod_moodleoverflow;

use mod_moodleoverflow\event\discussion_moved;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Tests moving a discussion to another moodleoverflow: everything that belongs to the discussion has to move with it.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\discussion
 * @covers \mod_moodleoverflow\local\models\discussion
 */
final class discussion_move_test extends \advanced_testcase {
    /** @var \stdClass The course of both moodleoverflows. */
    private \stdClass $course;

    /** @var \stdClass A teacher who may move discussions and rates the answer. */
    private \stdClass $teacher;

    /** @var \stdClass A student who asks the question. */
    private \stdClass $student;

    /** @var \stdClass A student who answers the question. */
    private \stdClass $answerer;

    /** @var moodleoverflow The moodleoverflow the discussion is moved from. */
    private moodleoverflow $source;

    /** @var moodleoverflow The moodleoverflow the discussion is moved to. */
    private moodleoverflow $destination;

    /** @var discussion The discussion that is moved. */
    private discussion $discussion;

    /** @var \stdClass The question with an embedded image. */
    private \stdClass $question;

    /** @var \stdClass The answer with an attachment, upvoted by the teacher. */
    private \stdClass $answer;

    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->answerer = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->source = $this->helper_create_moodleoverflow();
        $this->destination = $this->helper_create_moodleoverflow();

        // A question with an embedded image and an answer with an attachment.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        [$discussionrecord, $this->question] = $generator->post_to_forum($this->source->get_db_object(), $this->student);
        $this->answer = $generator->reply_to_post($this->question, $this->answerer);
        $this->helper_create_file('post', $this->question->id);
        $this->helper_create_file('attachment', $this->answer->id);
        $DB->set_field('moodleoverflow_posts', 'attachment', 1, ['id' => $this->answer->id]);

        // The teacher upvotes the answer; the answerer's grade in the source is based on it.
        $generator->create_rating(['moodleoverflowid' => $this->source->id, 'discussionid' => $discussionrecord->id,
            'userid' => $this->teacher->id, 'postid' => $this->answer->id, 'rating' => RATING_UPVOTE]);
        moodleoverflow_update_all_grades_for_instance($this->source);

        // Rows of other tables that store the moodleoverflow of the discussion.
        $DB->insert_record('moodleoverflow_read', ['userid' => $this->student->id, 'postid' => $this->answer->id,
            'discussionid' => $discussionrecord->id, 'moodleoverflowid' => $this->source->id, 'firstread' => time(),
            'lastread' => time(), ]);
        $DB->insert_record('moodleoverflow_discuss_subs', ['userid' => $this->answerer->id,
            'moodleoverflow' => $this->source->id, 'discussion' => $discussionrecord->id, 'preference' => time(), ]);
        $DB->insert_record('moodleoverflow_mail_info', ['userid' => $this->student->id, 'courseid' => $this->course->id,
            'forumid' => $this->source->id, 'forumdiscussionid' => $discussionrecord->id, 'numberofposts' => 1, ]);

        $this->discussion = discussion::from_id($discussionrecord->id);
    }

    /**
     * Test, if the discussion takes its files, ratings, grades and other rows to the destination.
     */
    public function test_move_discussion(): void {
        global $DB;
        $sink = $this->redirectEvents();
        $upvote = (int) get_config('moodleoverflow', 'votescaleupvote');
        $this->assertEquals($upvote, $this->helper_get_grade($this->source, $this->answerer));

        service\discussion::move($this->discussion, $this->destination, $this->teacher->id);

        // The discussion itself.
        $id = $this->discussion->get_id();
        $modflowid = $DB->get_field('moodleoverflow_discussions', 'moodleoverflow', ['id' => $id]);
        $this->assertEquals($this->destination->id, $modflowid);

        // Files: the posts find them in the destination, nothing is left in the source.
        $attachments = post::from_id($this->answer->id)->get_attachment_files();
        $this->assertCount(1, $attachments);
        $this->assertEquals('attachment.txt', reset($attachments)->get_filename());
        $fs = get_file_storage();
        $destinationcontextid = $this->destination->get_context()->id;
        $this->assertFalse($fs->is_area_empty($destinationcontextid, 'mod_moodleoverflow', 'post', $this->question->id));
        foreach (['post' => $this->question->id, 'attachment' => $this->answer->id] as $filearea => $postid) {
            $this->assertTrue($fs->is_area_empty($this->source->get_context()->id, 'mod_moodleoverflow', $filearea, $postid));
        }

        // Ratings, and with them reputation and grades.
        $this->assertTrue($DB->record_exists('moodleoverflow_ratings', ['discussionid' => $id,
            'moodleoverflowid' => $this->destination->id]));
        $this->assertEquals(0, ratings::get_reputation_instance($this->source->id, $this->answerer->id));
        $this->assertEquals($upvote, ratings::get_reputation_instance($this->destination->id, $this->answerer->id));
        $this->assertEquals(0, $this->helper_get_grade($this->source, $this->answerer));
        $this->assertEquals($upvote, $this->helper_get_grade($this->destination, $this->answerer));

        // Read records, discussion subscriptions and the digest queue.
        $this->assertTrue($DB->record_exists('moodleoverflow_read', ['discussionid' => $id,
            'moodleoverflowid' => $this->destination->id]));
        $this->assertTrue($DB->record_exists('moodleoverflow_discuss_subs', ['discussion' => $id,
            'moodleoverflow' => $this->destination->id]));
        $this->assertTrue($DB->record_exists('moodleoverflow_mail_info', ['forumdiscussionid' => $id,
            'forumid' => $this->destination->id]));

        // The posts are indexed again in the destination.
        $this->assertTrue($DB->record_exists('search_index_requests', ['contextid' => $destinationcontextid,
            'searcharea' => 'mod_moodleoverflow-post']));

        // The move is logged in the destination.
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof discussion_moved));
        $this->assertCount(1, $events);
        $this->assertEquals($id, $events[0]->objectid);
        $this->assertEquals($destinationcontextid, $events[0]->contextid);
        $this->assertEquals($this->source->id, $events[0]->other['frommoodleoverflowid']);
        $this->assertEquals($this->destination->id, $events[0]->other['tomoodleoverflowid']);
    }

    /**
     * Test, if a user who may not move the discussion changes nothing.
     */
    public function test_move_discussion_without_permission(): void {
        global $DB;
        try {
            service\discussion::move($this->discussion, $this->destination, $this->student->id);
            $this->fail('A student must not move a discussion.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidmovedestination', $e->errorcode);
        }

        $id = $this->discussion->get_id();
        $this->assertEquals($this->source->id, $DB->get_field('moodleoverflow_discussions', 'moodleoverflow', ['id' => $id]));
        $this->assertTrue($DB->record_exists('moodleoverflow_ratings', ['discussionid' => $id,
            'moodleoverflowid' => $this->source->id]));
        $this->assertCount(1, post::from_id($this->answer->id)->get_attachment_files());
    }

    /**
     * Creates a graded moodleoverflow in the course.
     *
     * @return moodleoverflow
     */
    private function helper_create_moodleoverflow(): moodleoverflow {
        $options = ['course' => $this->course->id, 'grademaxgrade' => 100, 'gradescalefactor' => 1];
        $record = $this->getDataGenerator()->create_module('moodleoverflow', $options);
        return moodleoverflow::from_id($record->id);
    }

    /**
     * Creates a text file in a file area of a post in the source.
     *
     * @param string $filearea 'attachment' or 'post'
     * @param int $postid The post id, used as item id
     * @return void
     */
    private function helper_create_file(string $filearea, int $postid): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $this->source->get_context()->id,
            'component' => 'mod_moodleoverflow',
            'filearea' => $filearea,
            'itemid' => $postid,
            'filepath' => '/',
            'filename' => "$filearea.txt",
        ], 'content');
    }

    /**
     * Returns the grade of a user in a moodleoverflow from the plugin's grade table.
     *
     * @param moodleoverflow $modflow
     * @param \stdClass $user
     * @return float
     */
    private function helper_get_grade(moodleoverflow $modflow, \stdClass $user): float {
        global $DB;
        $conditions = ['moodleoverflowid' => $modflow->id, 'userid' => $user->id];
        return (float) $DB->get_field('moodleoverflow_grades', 'grade', $conditions);
    }
}
