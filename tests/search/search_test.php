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

namespace mod_moodleoverflow\search;

use context;
use context_module;
use core_search\document;
use core_search\manager;
use mod_moodleoverflow\anonymous;
use mod_moodleoverflow\review;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/moodleoverflow/lib.php');

/**
 * PHPUnit tests for the moodleoverflow search areas.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group mod_moodleoverflow
 * @covers \mod_moodleoverflow\search\post
 * @covers \mod_moodleoverflow\search\activity
 */
final class search_test extends \advanced_testcase {
    /** @var string The area id of the post search area. */
    private string $postareaid;

    /** @var string The area id of the activity search area. */
    private string $activityareaid;

    /** @var \mod_moodleoverflow_generator The plugin generator. */
    private $generator;

    /** @var stdClass The course every moodleoverflow of these tests lives in. */
    private stdClass $course;

    /** @var stdClass A student, the author of most posts below. */
    private stdClass $student;

    /** @var stdClass A second student, without the review capability. */
    private stdClass $otherstudent;

    /** @var stdClass A teacher, who is allowed to review posts. */
    private stdClass $teacher;

    /**
     * Set up the course, its users and the search areas.
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enableglobalsearch', true);

        $this->postareaid = manager::generate_areaid('mod_moodleoverflow', 'post');
        $this->activityareaid = manager::generate_areaid('mod_moodleoverflow', 'activity');

        // Set \core_search::instance to the mock_search_engine, a working engine is not needed here.
        \testable_core_search::instance();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_moodleoverflow');
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->otherstudent = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Creates a moodleoverflow instance in the test course.
     *
     * @param array $record Fields overriding the defaults.
     * @return stdClass The moodleoverflow instance.
     */
    private function create_instance(array $record = []): stdClass {
        return $this->generator->create_instance(array_merge(['course' => $this->course->id], $record));
    }

    /**
     * Collects a search area recordset into an array indexed by record id.
     *
     * @param \core_search\base_mod $searcharea The search area to ask.
     * @param int $modifiedfrom Only return records modified at or after this timestamp.
     * @param context|null $context Optional context to restrict the scope to.
     * @return array The records, indexed by their id.
     */
    private function get_records(\core_search\base_mod $searcharea, int $modifiedfrom = 0, ?context $context = null): array {
        $recordset = $searcharea->get_document_recordset($modifiedfrom, $context);
        $records = [];
        foreach ($recordset as $record) {
            $records[$record->id] = $record;
        }
        $recordset->close();

        return $records;
    }

    /**
     * Both areas are enabled as soon as global search is, and can be switched off individually.
     * @return void
     */
    public function test_search_areas_enabled(): void {
        foreach ([$this->postareaid, $this->activityareaid] as $areaid) {
            $searcharea = manager::get_search_area($areaid);
            [$componentname, $varname] = $searcharea->get_config_var_name();

            // Enabled by default once global search is enabled.
            $this->assertTrue($searcharea->is_enabled());

            set_config($varname . '_enabled', 0, $componentname);
            $this->assertFalse($searcharea->is_enabled());

            set_config($varname . '_enabled', 1, $componentname);
            $this->assertTrue($searcharea->is_enabled());
        }
    }

    /**
     * Every post of every instance is handed to the indexer, and the scope can be restricted.
     * @return void
     */
    public function test_posts_indexing(): void {
        global $DB;

        $searcharea = manager::get_search_area($this->postareaid);
        $this->assertInstanceOf(post::class, $searcharea);

        $modflow1 = $this->create_instance();
        $modflow2 = $this->create_instance();

        [$discussion1, $firstpost1] = $this->generator->post_to_forum($modflow1, $this->student);
        $reply1 = $this->generator->reply_to_post($firstpost1, $this->otherstudent);
        [$discussion2, $firstpost2] = $this->generator->post_to_forum($modflow2, $this->student);

        // Without a context restriction, every post of every instance is returned.
        $records = $this->get_records($searcharea);
        $this->assertCount(3, $records);
        $this->assertArrayHasKey($firstpost1->id, $records);
        $this->assertArrayHasKey($reply1->id, $records);
        $this->assertArrayHasKey($firstpost2->id, $records);

        // The record carries everything get_document() needs beyond the post itself.
        $record = $records[$reply1->id];
        $this->assertEquals($modflow1->id, $record->moodleoverflowid);
        $this->assertEquals($this->course->id, $record->courseid);
        $this->assertEquals($discussion1->name, $record->discussionname);
        $this->assertEquals($this->student->id, $record->discussionuserid);

        // Restricted to one module context, only that instance's posts are returned.
        $cm1 = get_coursemodule_from_instance('moodleoverflow', $modflow1->id);
        $records = $this->get_records($searcharea, 0, context_module::instance($cm1->id));
        $this->assertCount(2, $records);
        $this->assertArrayHasKey($firstpost1->id, $records);
        $this->assertArrayHasKey($reply1->id, $records);
        $this->assertArrayNotHasKey($firstpost2->id, $records);

        // Restricted to a course context, every post of that course is returned.
        $records = $this->get_records($searcharea, 0, \context_course::instance($this->course->id));
        $this->assertCount(3, $records);

        // Posts modified before the given timestamp are skipped.
        $DB->set_field('moodleoverflow_posts', 'modified', 1000, ['id' => $firstpost1->id]);
        $DB->set_field('moodleoverflow_posts', 'modified', 2000, ['id' => $firstpost2->id]);
        $records = $this->get_records($searcharea, 2000);
        $this->assertArrayHasKey($firstpost2->id, $records);
        $this->assertArrayNotHasKey($firstpost1->id, $records);
    }

    /**
     * A post document carries the discussion name as its title, since posts have no subject of their own.
     * @return void
     */
    public function test_posts_document(): void {
        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance();
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);
        $context = context_module::instance($cm->id);

        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student, (object) [
            'name' => 'The haystack',
            'message' => '<p>The needle is in here</p>',
        ]);
        $reply = $this->generator->reply_to_post($firstpost, $this->otherstudent);

        $records = $this->get_records($searcharea);

        $doc = $searcharea->get_document($records[$firstpost->id]);
        $this->assertInstanceOf(document::class, $doc);
        $this->assertEquals($firstpost->id, $doc->get('itemid'));
        $this->assertEquals('The haystack', $doc->get('title'));
        $this->assertStringContainsString('The needle is in here', $doc->get('content'));
        $this->assertEquals($context->id, $doc->get('contextid'));
        $this->assertEquals($this->course->id, $doc->get('courseid'));
        $this->assertEquals(manager::NO_OWNER_ID, $doc->get('owneruserid'));
        $this->assertEquals($this->student->id, $doc->get('userid'));
        $this->assertEquals($firstpost->modified, $doc->get('modified'));

        // Answers reuse the discussion name, prefixed, so results stay tellable apart.
        $replydoc = $searcharea->get_document($records[$reply->id]);
        $this->assertEquals(get_string('re', 'mod_moodleoverflow') . ' ' . $discussion->name, $replydoc->get('title'));
        $this->assertEquals($this->otherstudent->id, $replydoc->get('userid'));
    }

    /**
     * A document is only flagged as new when the post was created after the last indexing run.
     * @return void
     */
    public function test_posts_document_is_new(): void {
        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance();
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $record = $this->get_records($searcharea)[$firstpost->id];

        $doc = $searcharea->get_document($record, ['lastindexedtime' => $firstpost->created - 10]);
        $this->assertTrue($doc->get_is_new());

        $doc = $searcharea->get_document($record, ['lastindexedtime' => $firstpost->created + 10]);
        $this->assertFalse($doc->get_is_new());
    }

    /**
     * In a moodleoverflow where the question is anonymous, the asker must not be attributed in the index.
     * @return void
     */
    public function test_posts_document_question_anonymous(): void {
        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance(['anonymous' => anonymous::QUESTION_ANONYMOUS]);
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $ownreply = $this->generator->reply_to_post($firstpost, $this->student);
        $otherreply = $this->generator->reply_to_post($firstpost, $this->otherstudent);

        $records = $this->get_records($searcharea);

        // The question itself is anonymous, so no userid may reach the index.
        $this->assertFalse($searcharea->get_document($records[$firstpost->id])->is_set('userid'));

        // The asker stays anonymous on their own answers too, which is how the plugin displays them.
        $this->assertFalse($searcharea->get_document($records[$ownreply->id])->is_set('userid'));

        // Everybody else is attributed as usual.
        $doc = $searcharea->get_document($records[$otherreply->id]);
        $this->assertTrue($doc->is_set('userid'));
        $this->assertEquals($this->otherstudent->id, $doc->get('userid'));
    }

    /**
     * In a fully anonymous moodleoverflow, nobody may be attributed in the index.
     * @return void
     */
    public function test_posts_document_everything_anonymous(): void {
        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance(['anonymous' => anonymous::EVERYTHING_ANONYMOUS]);
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $reply = $this->generator->reply_to_post($firstpost, $this->otherstudent);

        $records = $this->get_records($searcharea);

        $this->assertFalse($searcharea->get_document($records[$firstpost->id])->is_set('userid'));
        $this->assertFalse($searcharea->get_document($records[$reply->id])->is_set('userid'));
    }

    /**
     * Only users who can see a post in the activity may see it in the search results.
     * @return void
     */
    public function test_posts_access(): void {
        global $DB;

        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance();
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $goingaway = $this->generator->reply_to_post($firstpost, $this->student);

        // Enrolled users see the post.
        $this->setUser($this->student);
        $this->assertEquals(manager::ACCESS_GRANTED, $searcharea->check_access($firstpost->id));
        $this->setUser($this->otherstudent);
        $this->assertEquals(manager::ACCESS_GRANTED, $searcharea->check_access($firstpost->id));

        // A user who cannot see the activity cannot see its posts either.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertEquals(manager::ACCESS_DENIED, $searcharea->check_access($firstpost->id));

        // A post that no longer exists is reported as deleted, so it gets dropped from the index.
        $this->setUser($this->student);
        $DB->delete_records('moodleoverflow_posts', ['id' => $goingaway->id]);
        $this->assertEquals(manager::ACCESS_DELETED, $searcharea->check_access($goingaway->id));
    }

    /**
     * A post waiting for review is only visible to its author and to users who may review it.
     * @return void
     */
    public function test_posts_access_needing_review(): void {
        global $DB;

        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance(['needsreview' => review::EVERYTHING]);

        // The student cannot review, so their question starts out unreviewed.
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $this->assertEquals(0, $DB->get_field('moodleoverflow_posts', 'reviewed', ['id' => $firstpost->id]));

        // The author sees their own post while it waits.
        $this->setUser($this->student);
        $this->assertEquals(manager::ACCESS_GRANTED, $searcharea->check_access($firstpost->id));

        // So does the teacher, who has to review it.
        $this->setUser($this->teacher);
        $this->assertEquals(manager::ACCESS_GRANTED, $searcharea->check_access($firstpost->id));

        // Nobody else does.
        $this->setUser($this->otherstudent);
        $this->assertEquals(manager::ACCESS_DENIED, $searcharea->check_access($firstpost->id));

        // Once reviewed, the post is visible to everyone in the course. Every search request builds
        // its own search area, so ask a fresh one rather than the instance that cached the post.
        $DB->set_field('moodleoverflow_posts', 'reviewed', 1, ['id' => $firstpost->id]);
        $this->assertEquals(manager::ACCESS_GRANTED, (new post())->check_access($firstpost->id));
    }

    /**
     * Attachments and files embedded in the message are both handed to the indexer.
     * @return void
     */
    public function test_attach_files(): void {
        $searcharea = manager::get_search_area($this->postareaid);
        $this->assertTrue($searcharea->uses_file_indexing());

        $modflow = $this->create_instance();
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);
        $context = context_module::instance($cm->id);
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);

        $fs = get_file_storage();
        foreach (['attachment' => 'attached.txt', 'post' => 'inline.txt'] as $filearea => $filename) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_moodleoverflow',
                'filearea' => $filearea,
                'itemid' => $firstpost->id,
                'filepath' => '/',
                'filename' => $filename,
            ], 'Findable file content');
        }

        $doc = $searcharea->get_document($this->get_records($searcharea)[$firstpost->id]);
        $this->assertCount(0, $doc->get_files());

        $searcharea->attach_files($doc);
        $filenames = array_map(fn($file) => $file->get_filename(), $doc->get_files());
        sort($filenames);
        $this->assertEquals(['attached.txt', 'inline.txt'], $filenames);
    }

    /**
     * A result links to the post inside its discussion, and to the moodleoverflow it belongs to.
     * @return void
     */
    public function test_posts_urls(): void {
        $searcharea = manager::get_search_area($this->postareaid);

        $modflow = $this->create_instance();
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);
        [$discussion, $firstpost] = $this->generator->post_to_forum($modflow, $this->student);
        $reply = $this->generator->reply_to_post($firstpost, $this->otherstudent);

        $doc = $searcharea->get_document($this->get_records($searcharea)[$reply->id]);

        $expected = new moodle_url('/mod/moodleoverflow/discussion.php', ['d' => $discussion->id], 'p' . $reply->id);
        $this->assertEquals($expected->out(), $searcharea->get_doc_url($doc)->out());

        $expected = new moodle_url('/mod/moodleoverflow/view.php', ['id' => $cm->id]);
        $this->assertEquals($expected->out(), $searcharea->get_context_url($doc)->out());
    }

    /**
     * The activity area indexes the name and the introduction of an instance.
     * @return void
     */
    public function test_activity_indexing(): void {
        $searcharea = manager::get_search_area($this->activityareaid);
        $this->assertInstanceOf(activity::class, $searcharea);

        $modflow = $this->create_instance([
            'name' => 'Findable moodleoverflow',
            'intro' => '<p>An introduction with a needle in it</p>',
        ]);
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);
        $context = context_module::instance($cm->id);

        $records = $this->get_records($searcharea);
        $this->assertArrayHasKey($modflow->id, $records);

        $doc = $searcharea->get_document($records[$modflow->id]);
        $this->assertEquals('Findable moodleoverflow', $doc->get('title'));
        $this->assertStringContainsString('An introduction with a needle in it', $doc->get('content'));
        $this->assertEquals($context->id, $doc->get('contextid'));
        $this->assertEquals($this->course->id, $doc->get('courseid'));
        $this->assertEquals(manager::NO_OWNER_ID, $doc->get('owneruserid'));
    }

    /**
     * A newly created instance is flagged as new, thanks to CREATED_FIELD_NAME.
     * @return void
     */
    public function test_activity_document_is_new(): void {
        $searcharea = manager::get_search_area($this->activityareaid);

        $modflow = $this->create_instance();
        $record = $this->get_records($searcharea)[$modflow->id];

        $doc = $searcharea->get_document($record, ['lastindexedtime' => $record->timecreated - 10]);
        $this->assertTrue($doc->get_is_new());

        $doc = $searcharea->get_document($record, ['lastindexedtime' => $record->timecreated + 10]);
        $this->assertFalse($doc->get_is_new());
    }

    /**
     * A hidden activity is not visible in the search results.
     *
     * Enrolment itself is not checked here: core_search filters the contexts a user can reach before
     * it ever asks the area, so the area only has to rule on the item.
     *
     * @return void
     */
    public function test_activity_access(): void {
        $searcharea = manager::get_search_area($this->activityareaid);

        $modflow = $this->create_instance();
        $cm = get_coursemodule_from_instance('moodleoverflow', $modflow->id);

        $this->setUser($this->student);
        $this->assertEquals(manager::ACCESS_GRANTED, $searcharea->check_access($modflow->id));

        // Hidden from students, so it must not show up for them.
        set_coursemodule_visible($cm->id, 0);
        rebuild_course_cache($this->course->id, true);
        $this->assertEquals(manager::ACCESS_DENIED, (new activity())->check_access($modflow->id));

        // An instance that no longer exists is reported as deleted, so it gets dropped from the index.
        $this->assertEquals(manager::ACCESS_DELETED, $searcharea->check_access($modflow->id + 1000));
    }
}
