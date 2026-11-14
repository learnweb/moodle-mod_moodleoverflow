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

use core\exception\access_denied_exception;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the discussion list, the post tree and the export of the discussion service.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\discussion
 */
#[CoversClass(service\discussion::class)]
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
     * Test, if a discussion of the list keeps the question it was loaded with when it loads its other posts, so the
     * posts share one discussion object.
     */
    public function test_get_discussions_keeps_the_question(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);

        $discussion = array_values(service\discussion::get_discussions($modflow, $this->teacher->id))[0];
        $first = $discussion->get_first_post();
        $posts = $discussion->get_posts();

        $this->assertEquals([$question->get_id(), $answer->get_id()], array_keys($posts));
        $this->assertSame($first, $posts[$question->get_id()]);
        $this->assertSame($discussion, $posts[$answer->get_id()]->get_discussion());
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
     * Test, if the search finds every word in the title or the posts, ignoring case and HTML, and keeps the order.
     */
    public function test_search(): void {
        $modflow = $this->create_moodleoverflow();
        $older = $this->generator->create_question($modflow, $this->student, ['name' => 'Loops in Python']);
        $newer = $this->generator->create_question($modflow, $this->student, ['message' => '<p>while</p><p>loop</p>']);
        $this->generator->create_reply($older, $this->teacher, ['message' => 'Use a WHILE loop.']);
        $discussions = service\discussion::get_discussions($modflow, $this->student->id);

        $ids = fn(string $query) => array_values(array_map(
            fn(discussion $discussion) => $discussion->get_id(),
            service\discussion::search($discussions, $query, $this->student->id)
        ));
        $this->assertEquals([$older->get_discussionid()], $ids('python'));
        $this->assertEquals([$newer->get_discussionid(), $older->get_discussionid()], $ids('loop while'));
        $this->assertEquals([], $ids('python while java'));
    }

    /**
     * Test, if the text of a post the user cannot see does not make its discussion a match.
     */
    public function test_search_ignores_hidden_posts(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $this->generator->create_reply($question, $this->student, ['message' => 'Secret answer']);

        $search = fn(int $userid) => service\discussion::search(
            service\discussion::get_discussions($modflow, $userid),
            'secret',
            $userid
        );
        $this->assertCount(1, $search($this->student->id));
        $this->assertCount(0, $search($this->otherstudent->id));
    }

    /**
     * Test, if the export has one row per post in page order, with the type, the author, the plain text and the votes.
     */
    public function test_export(): void {
        $modflow = $this->create_moodleoverflow();
        $question = $this->generator->create_question($modflow, $this->student, ['name' => 'Q&A']);
        $answer = $this->generator->create_reply($question, $this->otherstudent, ['message' => '<p>Because of this.</p>']);
        $comment = $this->generator->create_reply($answer, $this->teacher);
        $this->generator->create_rating($answer, $this->teacher, rating_type::UPVOTE);

        $rows = service\discussion::export($modflow, $this->teacher->id);

        $this->assertEquals([$question->get_id(), $answer->get_id(), $comment->get_id()], array_column($rows, 'postid'));
        $this->assertEquals(['question', 'answer', 'comment'], array_column($rows, 'posttype'));
        $this->assertEquals('Q&A', $rows[1]['subject']);
        $this->assertEquals(fullname($this->otherstudent), $rows[1]['authorname']);
        $this->assertEquals('Because of this.', trim($rows[1]['message']));
        $this->assertEquals(3, $rows[1]['wordcount']);
        $this->assertEquals(1, $rows[1]['upvotescount']);
    }

    /**
     * Test, if anonymous authors appear with their pseudonym in the export, also for teachers.
     */
    public function test_export_anonymous(): void {
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $this->generator->create_reply($question, $this->otherstudent);

        $expected = [get_string('questioner', 'mod_moodleoverflow'), get_string('answerer', 'mod_moodleoverflow', 1)];
        $this->assertEquals($expected, array_column(service\discussion::export($modflow, $this->teacher->id), 'authorname'));
    }

    /**
     * Test, if the export only has the posts the user can see.
     */
    public function test_export_hides_posts_the_user_cannot_see(): void {
        $modflow = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->teacher);
        $this->generator->create_reply($question, $this->student);
        $this->allow_capability('mod/moodleoverflow:exportforum', 'student', $modflow);

        $rows = service\discussion::export($modflow, $this->otherstudent->id);
        $this->assertEquals([$question->get_id()], array_column($rows, 'postid'));
    }

    /**
     * Test, if users without the capability cannot export.
     */
    public function test_export_needs_capability(): void {
        $modflow = $this->create_moodleoverflow();
        $this->generator->create_question($modflow, $this->student);

        $this->expectException(access_denied_exception::class);
        service\discussion::export($modflow, $this->student->id);
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
