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
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use core\exception\moodle_exception;

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
    use moodleoverflow_test_trait;

    /** @var moodleoverflow The moodleoverflow the discussion is moved from. */
    private moodleoverflow $source;

    /** @var moodleoverflow The moodleoverflow the discussion is moved to. */
    private moodleoverflow $destination;

    /** @var discussion The discussion that is moved. */
    private discussion $discussion;

    /** @var post The question with an embedded image. */
    private post $question;

    /** @var post The answer of the other student with an attachment, upvoted by the teacher. */
    private post $answer;

    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->setup_course();
        $options = ['grademaxgrade' => 100, 'gradescalefactor' => 1];
        $this->source = $this->create_moodleoverflow($options);
        $this->destination = $this->create_moodleoverflow($options);

        // A question with an embedded image and an answer with an attachment.
        $this->question = $this->generator->create_question($this->source, $this->student);
        $this->answer = $this->generator->create_reply($this->question, $this->otherstudent);
        $this->discussion = $this->question->get_discussion();
        $this->generator->create_post_file($this->question, 'post', 'post.txt');
        $this->generator->create_post_file($this->answer, 'attachment', 'attachment.txt');

        // The teacher upvotes the answer; the answerer's grade in the source is based on it.
        $this->generator->create_rating($this->answer, $this->teacher, rating_type::UPVOTE);
        service\rating::update_all_grades($this->source);

        // Rows of other tables that store the moodleoverflow of the discussion.
        $discussionid = $this->discussion->get_id();
        $DB->insert_record('moodleoverflow_read', ['userid' => $this->student->id, 'postid' => $this->answer->get_id(),
            'discussionid' => $discussionid, 'moodleoverflowid' => $this->source->id, 'firstread' => time(),
            'lastread' => time(), ]);
        $DB->insert_record('moodleoverflow_discuss_subs', ['userid' => $this->otherstudent->id,
            'moodleoverflow' => $this->source->id, 'discussion' => $discussionid, 'preference' => time(), ]);
        $DB->insert_record('moodleoverflow_mail_info', ['userid' => $this->student->id, 'courseid' => $this->course->id,
            'forumid' => $this->source->id, 'forumdiscussionid' => $discussionid, 'numberofposts' => 1, ]);
    }

    /**
     * Test, if the discussion takes its files, ratings, grades and other rows to the destination.
     */
    public function test_move_discussion(): void {
        global $DB;
        $sink = $this->redirectEvents();
        $upvote = (int) get_config('moodleoverflow', 'votescaleupvote');
        $this->assertEquals($upvote, $this->helper_get_grade($this->source, $this->otherstudent));

        service\discussion::move($this->discussion, $this->destination, $this->teacher->id);

        // The discussion itself.
        $id = $this->discussion->get_id();
        $modflowid = $DB->get_field('moodleoverflow_discussions', 'moodleoverflow', ['id' => $id]);
        $this->assertEquals($this->destination->id, $modflowid);

        // Files: the posts find them in the destination, nothing is left in the source.
        $attachments = post::from_id($this->answer->get_id())->get_attachment_files();
        $this->assertCount(1, $attachments);
        $this->assertEquals('attachment.txt', reset($attachments)->get_filename());
        $fs = get_file_storage();
        $destinationcontextid = $this->destination->get_context()->id;
        $this->assertFalse($fs->is_area_empty($destinationcontextid, 'mod_moodleoverflow', 'post', $this->question->get_id()));
        foreach (['post' => $this->question->get_id(), 'attachment' => $this->answer->get_id()] as $filearea => $postid) {
            $this->assertTrue($fs->is_area_empty($this->source->get_context()->id, 'mod_moodleoverflow', $filearea, $postid));
        }

        // Ratings, and with them reputation and grades.
        $this->assertTrue($DB->record_exists('moodleoverflow_ratings', ['discussionid' => $id,
            'moodleoverflowid' => $this->destination->id]));
        $this->assertEquals(0, service\rating::get_instance_reputation($this->source, $this->otherstudent->id));
        $this->assertEquals($upvote, service\rating::get_instance_reputation($this->destination, $this->otherstudent->id));
        $this->assertEquals(0, $this->helper_get_grade($this->source, $this->otherstudent));
        $this->assertEquals($upvote, $this->helper_get_grade($this->destination, $this->otherstudent));

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
        $this->assertCount(1, post::from_id($this->answer->get_id())->get_attachment_files());
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
