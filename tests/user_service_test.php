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
 * PHPUnit tests for the overview of the posts of a user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service\user_service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * PHPUnit tests for the overview of the posts of a user.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_moodleoverflow\local\service\user_service
 */
#[CoversClass(user_service::class)]
final class user_service_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow test moodleoverflow */
    private $modflow;

    /** @var post the question of the student */
    private $question;

    /** @var post the answer of the student to the question of the other student */
    private $answer;

    /**
     * Creates a course with a moodleoverflow, in which the student asked a question and answered one of the other
     * student. The answer was written after the question.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
        $this->question = $this->generator->create_question($this->modflow, $this->student);
        $otherquestion = $this->generator->create_question($this->modflow, $this->otherstudent);
        $this->answer = $this->generator->create_reply($otherquestion, $this->student);
        $this->set_created($this->question, 1000);
        $this->set_created($this->answer, 2000);
    }

    /**
     * Test, if a user sees all of their own posts, the newest first, with the links to the post, the discussion and
     * the moodleoverflow.
     */
    public function test_own_posts(): void {
        $posts = user_service::get_all_user_posts($this->student->id, $this->student->id);
        $this->assertEquals([$this->answer->get_id(), $this->question->get_id()], array_column($posts, 'id'));

        $question = $posts[1];
        $this->assertEquals($this->course->id, $question['courseid']);
        $this->assertEquals($this->modflow->name, $question['modflow']);
        $this->assertEquals($this->question->get_discussion()->name, $question['discussionsubject']);
        $this->assertEquals($this->question->get_link()->out(), $question['posturl']);
        $this->assertEquals($this->question->get_discussion()->get_link()->out(), $question['discussionurl']);
        $this->assertEquals($this->modflow->get_link()->out(), $question['modflowurl']);
    }

    /**
     * Test, if a user without posts has an empty overview.
     */
    public function test_no_posts(): void {
        $this->assertEquals([], user_service::get_all_user_posts($this->teacher->id, $this->student->id));
    }

    /**
     * Test, if other viewers do not get the posts whose author is hidden, while the author still sees them.
     */
    public function test_anonymity(): void {
        global $DB;
        $DB->set_field('moodleoverflow', 'anonymous', anonymity::QUESTIONS->value, ['id' => $this->modflow->id]);
        $this->assertEquals([$this->answer->get_id()], $this->get_postids($this->otherstudent));
        $this->assertEquals([$this->answer->get_id(), $this->question->get_id()], $this->get_postids($this->student));

        $DB->set_field('moodleoverflow', 'anonymous', anonymity::EVERYTHING->value, ['id' => $this->modflow->id]);
        $this->assertEquals([], $this->get_postids($this->otherstudent));
        $this->assertEquals([$this->answer->get_id(), $this->question->get_id()], $this->get_postids($this->student));
    }

    /**
     * Test, if a post that waits for its review is only shown to its author and to reviewers.
     */
    public function test_review(): void {
        $reviewed = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $pending = $this->generator->create_question($reviewed, $this->student);
        $this->set_created($pending, 3000);

        $published = [$this->answer->get_id(), $this->question->get_id()];
        $this->assertEquals($published, $this->get_postids($this->otherstudent));
        $this->assertEquals(array_merge([$pending->get_id()], $published), $this->get_postids($this->student));
        $this->assertEquals(array_merge([$pending->get_id()], $published), $this->get_postids($this->teacher));
    }

    /**
     * Test, if posts in a course the viewer cannot access are left out.
     */
    public function test_other_course(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $othercourse->id, 'student');
        $record = $this->getDataGenerator()->create_module('moodleoverflow', ['course' => $othercourse->id]);
        $elsewhere = $this->generator->create_question(moodleoverflow::from_id($record->id), $this->student);
        $this->set_created($elsewhere, 3000);

        $here = [$this->answer->get_id(), $this->question->get_id()];
        $this->assertEquals($here, $this->get_postids($this->otherstudent));
        $this->assertEquals($here, $this->get_postids($this->teacher));
        $this->assertEquals(array_merge([$elsewhere->get_id()], $here), $this->get_postids($this->student));
    }

    /**
     * Returns the ids of the posts of the student that a viewer sees, in the order of the overview.
     *
     * @param \stdClass $viewer
     * @return int[]
     */
    private function get_postids(\stdClass $viewer): array {
        return array_column(user_service::get_all_user_posts($this->student->id, $viewer->id), 'id');
    }

    /**
     * Sets when a post was written, so that the order of the overview does not depend on posts of the same second.
     *
     * @param post $post
     * @param int $created
     */
    private function set_created(post $post, int $created): void {
        global $DB;
        $DB->set_field('moodleoverflow_posts', 'created', $created, ['id' => $post->get_id()]);
    }
}
