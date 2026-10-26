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

use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;

/**
 * Tests the discussion list and the post tree of the discussion service.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\discussion
 */
final class discussion_service_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
    }

    /**
     * Test, if an unreviewed question is only listed for its author and for reviewers.
     */
    public function test_get_discussions_hides_unreviewed_questions(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::QUESTIONS->value]);
        $reviewed = $this->generator->create_question($modflow, $this->teacher)->get_discussion();
        $unreviewed = $this->generator->create_question($modflow, $this->student)->get_discussion();

        $both = [$unreviewed->get_id(), $reviewed->get_id()];
        $this->assertEquals([$reviewed->get_id()], $this->get_discussion_ids($modflow, $this->otherstudent->id));
        $this->assertEquals($both, $this->get_discussion_ids($modflow, $this->student->id));
        $this->assertEquals($both, $this->get_discussion_ids($modflow, $this->teacher->id));
    }

    /**
     * Test, if the discussions are sorted by their start, the newest first, and by id when they start at the same time.
     */
    public function test_get_discussions_order(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();
        $old = $this->generator->create_question($modflow, $this->student)->get_discussion();
        $new = $this->generator->create_question($modflow, $this->student)->get_discussion();
        $sametime = $this->generator->create_question($modflow, $this->student)->get_discussion();
        $DB->set_field('moodleoverflow_discussions', 'timestart', 100, ['id' => $old->get_id()]);
        $DB->set_field('moodleoverflow_discussions', 'timestart', 300, ['id' => $new->get_id()]);
        $DB->set_field('moodleoverflow_discussions', 'timestart', 300, ['id' => $sametime->get_id()]);

        $expected = [$sametime->get_id(), $new->get_id(), $old->get_id()];
        $this->assertEquals($expected, $this->get_discussion_ids($modflow, $this->otherstudent->id));
    }

    /**
     * Test, if a user without the capability to view discussions gets an empty list, even for an own discussion.
     */
    public function test_get_discussions_without_viewdiscussion(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow();
        $this->generator->create_question($modflow, $this->student);

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/moodleoverflow:viewdiscussion', CAP_PROHIBIT, $studentrole, $modflow->get_context()->id);

        $this->assertEquals([], service\discussion::get_discussions($modflow, $this->student->id));
    }

    /**
     * Test, if the answers are ranked and each answer is followed by its comments, oldest first, including replies to
     * comments.
     */
    public function test_get_posts_tree_order(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        $upvoted = $this->generator->create_reply($question, $this->otherstudent);
        $comment = $this->generator->create_reply($answer, $this->teacher);
        $upvotedcomment = $this->generator->create_reply($upvoted, $this->teacher);
        $latercomment = $this->generator->create_reply($answer, $this->otherstudent);
        $replytocomment = $this->generator->create_reply($comment, $this->student);
        $this->generator->create_rating($upvoted, $this->teacher, rating_type::UPVOTE);

        $expected = [
            [$upvoted->get_id(), [$upvotedcomment->get_id()]],
            [$answer->get_id(), [$comment->get_id(), $latercomment->get_id(), $replytocomment->get_id()]],
        ];
        $this->assertEquals($expected, $this->get_tree_ids($question, $this->student->id));
    }

    /**
     * Test, if an unreviewed answer is only shown to its author, and its comments are hidden with it.
     */
    public function test_get_posts_tree_hides_unreviewed_answer(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->student);
        $comment = $this->generator->create_reply($answer, $this->teacher);

        $this->assertEquals([[$answer->get_id(), [$comment->get_id()]]], $this->get_tree_ids($question, $this->student->id));
        $this->assertEquals([], $this->get_tree_ids($question, $this->otherstudent->id));
    }

    /**
     * Returns the ids of the discussions a user can see, in the order of the service.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int[]
     */
    private function get_discussion_ids(moodleoverflow $modflow, int $userid): array {
        return array_values(array_map(
            fn(discussion $discussion) => $discussion->get_id(),
            service\discussion::get_discussions($modflow, $userid)
        ));
    }

    /**
     * Returns the post tree of a discussion as ids: one [answer id, comment ids] pair per answer. Checks the question.
     *
     * @param post $question
     * @param int $userid
     * @return array
     */
    private function get_tree_ids(post $question, int $userid): array {
        $tree = service\discussion::get_posts_tree($question->get_discussion(), $userid);
        $this->assertEquals($question->get_id(), $tree['question']->get_id());
        return array_map(
            fn(array $branch) => [$branch['answer']->get_id(), array_map(fn(post $post) => $post->get_id(), $branch['comments'])],
            $tree['answers']
        );
    }
}
