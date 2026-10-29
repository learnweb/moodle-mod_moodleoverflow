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
 * The module moodleoverflow tests.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use core_external\external_api;
use mod_moodleoverflow\event\rating_created;
use mod_moodleoverflow\event\rating_deleted;
use mod_moodleoverflow\event\rating_updated;
use mod_moodleoverflow\external\record_vote;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\rating_preference;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use stdClass;

/**
 * PHPUnit tests for ratings: votes, marks, reputation, grades and the sorting of answers.
 *
 * @package mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\service\rating
 * @covers \mod_moodleoverflow\external\record_vote
 */
final class ratings_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow Instance with the default settings */
    private moodleoverflow $modflow;

    /** @var post Question of the student */
    private post $question;

    /** @var post Answer of the other student */
    private post $answer;

    /** @var post Answer of the teacher */
    private post $teacheranswer;

    /** @var rating_preference Which mark comes first when sorting */
    private rating_preference $preference;

    /** @var stdClass answer to sort, modified first */
    private stdClass $answer1;

    /** @var stdClass answer to sort */
    private stdClass $answer2;

    /** @var stdClass answer to sort */
    private stdClass $answer3;

    /** @var stdClass answer to sort */
    private stdClass $answer4;

    /** @var stdClass answer to sort */
    private stdClass $answer5;

    /** @var stdClass answer to sort, modified last */
    private stdClass $answer6;

    /**
     * Test setUp.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
        [$this->modflow, $this->question, $this->answer, $this->teacheranswer] = $this->create_discussion();
        $this->create_sorting_answers();

        // Distinct reputation points, so every sum shows which ratings counted.
        foreach (['vote' => 1, 'upvote' => 10, 'downvote' => -5, 'solved' => 100, 'helpful' => 1000] as $name => $points) {
            set_config('votescale' . $name, $points, 'moodleoverflow');
        }
    }

    // Votes.

    /**
     * Test, if a vote is stored once, can be changed and removed, and triggers the rating events.
     */
    public function test_vote(): void {
        global $DB;
        $sink = $this->redirectEvents();
        $count = fn() => $DB->count_records('moodleoverflow_ratings', ['postid' => $this->answer->get_id()]);

        service\rating::vote($this->answer, $this->student->id, rating_type::UPVOTE);
        service\rating::vote($this->answer, $this->student->id, rating_type::UPVOTE);
        $this->assertSame(rating_type::UPVOTE, service\rating::get_user_vote($this->answer, $this->student->id));
        $this->assertEquals(1, $count());
        $record = $DB->get_record('moodleoverflow_ratings', ['postid' => $this->answer->get_id()]);
        $this->assertEquals($this->question->get_discussionid(), $record->discussionid);
        $this->assertEquals($this->modflow->id, $record->moodleoverflowid);
        $this->assertEquals($record->firstrated, $record->lastchanged);

        service\rating::vote($this->answer, $this->student->id, rating_type::DOWNVOTE);
        $this->assertSame(rating_type::DOWNVOTE, service\rating::get_user_vote($this->answer, $this->student->id));
        $this->assertEquals(1, $count());
        $votes = service\rating::get_user_votes($this->get_discussion(), $this->student->id);
        $this->assertSame([$this->answer->get_id() => rating_type::DOWNVOTE], $votes);

        service\rating::remove_vote($this->answer, $this->student->id);
        service\rating::remove_vote($this->answer, $this->student->id);
        $this->assertNull(service\rating::get_user_vote($this->answer, $this->student->id));
        $this->assertEquals(0, $count());

        // Repeating a vote or a removal changes nothing, so it triggers no event.
        $events = $this->get_rating_events($sink);
        $this->assertEquals([rating_created::class, rating_updated::class, rating_deleted::class], array_map('get_class', $events));
        foreach ($events as $event) {
            $this->assertEquals($this->student->id, $event->userid);
            $this->assertEquals($record->id, $event->objectid);
        }
        $snapshot = $events[2]->get_record_snapshot('moodleoverflow_ratings', $record->id);
        $this->assertEquals(rating_type::DOWNVOTE->value, $snapshot->rating);
    }

    /**
     * Test, if changing or removing a vote needs the admin setting allowratingchange, while marks do not.
     */
    public function test_allowratingchange(): void {
        $canchange = fn(post $post, stdClass $user) => service\rating::can_change_vote(
            $post,
            $user->id,
            service\rating::get_user_vote($post, $user->id)
        );
        set_config('allowratingchange', 0, 'moodleoverflow');
        $this->assertTrue($canchange($this->answer, $this->student));

        service\rating::vote($this->answer, $this->student->id, rating_type::UPVOTE);
        $this->assertFalse($canchange($this->answer, $this->student));
        $this->assertTrue($canchange($this->teacheranswer, $this->student));
        $change = fn() => service\rating::vote($this->answer, $this->student->id, rating_type::DOWNVOTE);
        $remove = fn() => service\rating::remove_vote($this->answer, $this->student->id);
        $this->assert_refused($change, 'noratingchangeallowed');
        $this->assert_refused($remove, 'noratingchangeallowed');
        $this->assertSame(rating_type::UPVOTE, service\rating::get_user_vote($this->answer, $this->student->id));

        service\rating::mark($this->answer, $this->teacher->id, rating_type::SOLVED);
        service\rating::unmark($this->answer, $this->teacher->id, rating_type::SOLVED);
        $this->assertEquals([], service\rating::get_marked_post_ids($this->get_discussion(), rating_type::SOLVED));

        // With rating changes, a vote can always be changed. Nobody can change a vote they may not give.
        set_config('allowratingchange', 1, 'moodleoverflow');
        $this->assertTrue($canchange($this->answer, $this->student));
        $this->assertFalse($canchange($this->answer, $this->otherstudent));
    }

    /**
     * Test, if votes on own posts and marks passed as votes are refused.
     */
    public function test_vote_refused(): void {
        $vote = fn() => service\rating::vote($this->answer, $this->otherstudent->id, rating_type::UPVOTE);
        $remove = fn() => service\rating::remove_vote($this->answer, $this->otherstudent->id);
        $this->assert_refused($vote, 'noratemoodleoverflow');
        $this->assert_refused($remove, 'noratemoodleoverflow');

        $this->expectException(\coding_exception::class);
        service\rating::vote($this->answer, $this->student->id, rating_type::SOLVED);
    }

    // Marks.

    /**
     * Test, if a mark moves to the newly marked answer when multiple marks are not allowed.
     */
    public function test_mark_moves_without_multiple_marks(): void {
        global $DB;
        service\rating::mark($this->answer, $this->teacher->id, rating_type::SOLVED);
        $sink = $this->redirectEvents();
        service\rating::mark($this->teacheranswer, $this->teacher->id, rating_type::SOLVED);
        service\rating::mark($this->teacheranswer, $this->teacher->id, rating_type::SOLVED);

        $marked = fn(rating_type $mark) => service\rating::get_marked_post_ids($this->get_discussion(), $mark);
        $this->assertEquals([$this->teacheranswer->get_id()], $marked(rating_type::SOLVED));
        $this->assertEquals(1, $DB->count_records('moodleoverflow_ratings', ['rating' => rating_type::SOLVED->value]));
        $events = $this->get_rating_events($sink);
        $this->assertEquals([rating_deleted::class, rating_created::class], array_map('get_class', $events));

        // The other kind of mark is independent.
        service\rating::mark($this->answer, $this->student->id, rating_type::HELPFUL);
        $this->assertEquals([$this->answer->get_id()], $marked(rating_type::HELPFUL));
        $this->assertEquals([$this->teacheranswer->get_id()], $marked(rating_type::SOLVED));
    }

    /**
     * Test, if several answers keep their marks when multiple marks are allowed.
     */
    public function test_mark_with_multiple_marks(): void {
        [, $question, $answer, $teacheranswer] = $this->create_discussion(['allowmultiplemarks' => 1]);
        $discussion = discussion::from_id($question->get_discussionid());

        service\rating::mark($teacheranswer, $this->teacher->id, rating_type::SOLVED);
        service\rating::mark($answer, $this->teacher->id, rating_type::SOLVED);
        service\rating::mark($answer, $this->teacher->id, rating_type::SOLVED);
        $marked = service\rating::get_marked_post_ids($discussion, rating_type::SOLVED);
        $this->assertEquals([$teacheranswer->get_id(), $answer->get_id()], $marked);

        service\rating::unmark($teacheranswer, $this->teacher->id, rating_type::SOLVED);
        $this->assertEquals([$answer->get_id()], service\rating::get_marked_post_ids($discussion, rating_type::SOLVED));
    }

    /**
     * Test, if unmarking removes every mark of that kind on the post, no matter who set it.
     */
    public function test_unmark(): void {
        // Earlier versions could store the same mark twice.
        $this->generator->create_rating($this->answer, $this->teacher, rating_type::SOLVED);
        $this->generator->create_rating($this->answer, $this->teacher, rating_type::SOLVED);
        $this->generator->create_rating($this->answer, $this->student, rating_type::HELPFUL);
        $otherteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $sink = $this->redirectEvents();

        service\rating::unmark($this->answer, $otherteacher->id, rating_type::SOLVED);
        service\rating::unmark($this->answer, $otherteacher->id, rating_type::SOLVED);

        $discussion = $this->get_discussion();
        $this->assertEquals([], service\rating::get_marked_post_ids($discussion, rating_type::SOLVED));
        $this->assertEquals([$this->answer->get_id()], service\rating::get_marked_post_ids($discussion, rating_type::HELPFUL));
        $events = $this->get_rating_events($sink);
        $this->assertEquals([rating_deleted::class, rating_deleted::class], array_map('get_class', $events));
        $this->assertEquals($otherteacher->id, $events[0]->userid);
    }

    /**
     * Test, if only teachers set solved marks, only the questioner sets helpful marks and votes are no marks.
     */
    public function test_mark_refused(): void {
        $solved = rating_type::SOLVED;
        $helpful = rating_type::HELPFUL;
        $this->assert_refused(fn() => service\rating::mark($this->answer, $this->student->id, $solved), 'notteacher');
        $this->assert_refused(fn() => service\rating::unmark($this->answer, $this->student->id, $solved), 'notteacher');
        $this->assert_refused(fn() => service\rating::mark($this->answer, $this->teacher->id, $helpful), 'notstartuser');
        $this->assert_refused(fn() => service\rating::unmark($this->answer, $this->teacher->id, $helpful), 'notstartuser');

        $this->expectException(\coding_exception::class);
        service\rating::mark($this->answer, $this->teacher->id, rating_type::UPVOTE);
    }

    // Reads.

    /**
     * Test, if the ratings of every post of a discussion are counted, also of posts without ratings.
     */
    public function test_get_post_ratings(): void {
        service\rating::vote($this->answer, $this->student->id, rating_type::UPVOTE);
        service\rating::vote($this->answer, $this->teacher->id, rating_type::UPVOTE);
        service\rating::vote($this->teacheranswer, $this->student->id, rating_type::DOWNVOTE);
        service\rating::mark($this->answer, $this->teacher->id, rating_type::SOLVED);
        service\rating::mark($this->answer, $this->student->id, rating_type::HELPFUL);

        $ratings = service\rating::get_post_ratings($this->get_discussion());
        $ids = [$this->question->get_id(), $this->answer->get_id(), $this->teacheranswer->get_id()];
        $this->assertEqualsCanonicalizing($ids, array_keys($ratings));
        $row = fn(post $post, int $up, int $down, int $solved, int $helpful) => ['postid' => $post->get_id(),
            'upvotes' => $up, 'downvotes' => $down, 'issolved' => $solved, 'ishelpful' => $helpful];
        $this->assertSame($row($this->question, 0, 0, 0, 0), (array) $ratings[$this->question->get_id()]);
        $this->assertSame($row($this->answer, 2, 0, 1, 1), (array) $ratings[$this->answer->get_id()]);
        $this->assertSame($row($this->teacheranswer, 0, 1, 0, 0), (array) $ratings[$this->teacheranswer->get_id()]);
    }

    // Reputation.

    /**
     * Test, if received ratings only count where the author is visible, while the bonus for voting always counts.
     */
    public function test_reputation_and_anonymity(): void {
        // Received: question +10, answer in own discussion +10, answer elsewhere -5 + 100 + 1000. Given: one vote +1.
        $expected = [
            anonymity::NONE->value => 10 + 10 - 5 + 100 + 1000 + 1,
            anonymity::QUESTIONS->value => -5 + 100 + 1000 + 1,
            anonymity::EVERYTHING->value => 1,
        ];
        foreach ($expected as $anonymous => $reputation) {
            $modflow = $this->create_moodleoverflow(['anonymous' => $anonymous]);
            $ownquestion = $this->generator->create_question($modflow, $this->student);
            $ownanswer = $this->generator->create_reply($ownquestion, $this->student);
            $otherquestion = $this->generator->create_question($modflow, $this->otherstudent);
            $answer = $this->generator->create_reply($otherquestion, $this->student);
            $this->generator->create_rating($ownquestion, $this->teacher, rating_type::UPVOTE);
            $this->generator->create_rating($ownanswer, $this->teacher, rating_type::UPVOTE);
            $this->generator->create_rating($answer, $this->teacher, rating_type::DOWNVOTE);
            $this->generator->create_rating($answer, $this->teacher, rating_type::SOLVED);
            $this->generator->create_rating($answer, $this->otherstudent, rating_type::HELPFUL);
            $this->generator->create_rating($otherquestion, $this->student, rating_type::UPVOTE);
            // Marking gives the marker no reputation.
            $this->generator->create_rating($ownanswer, $this->student, rating_type::HELPFUL);

            $actual = service\rating::get_instance_reputation($modflow, $this->student->id);
            $this->assertEquals($reputation, $actual, anonymity::from($anonymous)->name);
        }
    }

    /**
     * Test, if the reputation only becomes negative when the instance allows it.
     */
    public function test_negative_reputation(): void {
        foreach ([0 => 0, 1 => -5] as $allownegative => $reputation) {
            [$modflow, , $answer] = $this->create_discussion(['allownegativereputation' => $allownegative]);
            $this->generator->create_rating($answer, $this->teacher, rating_type::DOWNVOTE);
            $this->assertEquals($reputation, service\rating::get_instance_reputation($modflow, $this->otherstudent->id));
        }
    }

    /**
     * Test, if the course reputation sums the instances of the course that share their reputation.
     */
    public function test_course_reputation(): void {
        [$shared] = $this->create_upvoted_discussion(['coursewidereputation' => 1]);
        $this->create_upvoted_discussion(['coursewidereputation' => 1]);
        [$own] = $this->create_upvoted_discussion(['coursewidereputation' => 0]);
        $othercourse = $this->getDataGenerator()->create_course();
        $this->create_upvoted_discussion(['coursewidereputation' => 1, 'course' => $othercourse->id]);

        $this->assertEquals(20, service\rating::get_course_reputation($this->course->id, $this->otherstudent->id));
        $this->assertEquals(20, service\rating::get_reputation($shared, $this->otherstudent->id));
        $this->assertEquals(10, service\rating::get_instance_reputation($shared, $this->otherstudent->id));
        $this->assertEquals(10, service\rating::get_reputation($own, $this->otherstudent->id));
    }

    // Grades.

    /**
     * Test, if every use case updates the grades of the affected users in the plugin table and in the gradebook.
     */
    public function test_grades(): void {
        [$modflow, , $answer] = $this->create_discussion(['grademaxgrade' => 50, 'gradescalefactor' => 2]);

        // The author gets 10 / 2, the voter 1 / 2.
        service\rating::vote($answer, $this->student->id, rating_type::UPVOTE);
        $this->assertEquals(5, $this->get_grade($modflow, $this->otherstudent));
        $this->assertEquals(0.5, $this->get_grade($modflow, $this->student));

        // 110 / 2 is capped at the maximum grade. Marking gives the marker no grade.
        service\rating::mark($answer, $this->teacher->id, rating_type::SOLVED);
        $this->assertEquals(50, $this->get_grade($modflow, $this->otherstudent));
        $this->assertNull($this->get_grade($modflow, $this->teacher));
        $gradebook = grade_get_grades($this->course->id, 'mod', 'moodleoverflow', $modflow->id, $this->otherstudent->id);
        $this->assertEquals(50, $gradebook->items[0]->grades[$this->otherstudent->id]->grade);

        service\rating::unmark($answer, $this->teacher->id, rating_type::SOLVED);
        $this->assertEquals(5, $this->get_grade($modflow, $this->otherstudent));
        service\rating::remove_vote($answer, $this->student->id);
        $this->assertEquals(0, $this->get_grade($modflow, $this->otherstudent));
        $this->assertEquals(0, $this->get_grade($modflow, $this->student));
    }

    /**
     * Test, if grades use the reputation of the instance even if the reputation is shared with the course.
     */
    public function test_grades_use_instance_reputation(): void {
        $this->create_upvoted_discussion(['coursewidereputation' => 1]);
        [$modflow, , $answer] = $this->create_discussion(['coursewidereputation' => 1, 'grademaxgrade' => 50,
            'gradescalefactor' => 1, ]);

        service\rating::vote($answer, $this->student->id, rating_type::UPVOTE);
        $this->assertEquals(20, service\rating::get_reputation($modflow, $this->otherstudent->id));
        $this->assertEquals(10, $this->get_grade($modflow, $this->otherstudent));
    }

    /**
     * Test, if instances without grading store no grades and if all grades of an instance can be rebuilt.
     */
    public function test_update_all_grades(): void {
        global $DB;
        service\rating::vote($this->answer, $this->student->id, rating_type::UPVOTE);
        $this->assertFalse($DB->record_exists('moodleoverflow_grades', []));

        [$modflow, $question, $answer] = $this->create_discussion(['grademaxgrade' => 50, 'gradescalefactor' => 1]);
        $this->generator->create_rating($answer, $this->student, rating_type::UPVOTE);
        // The privacy provider sets the author of deleted posts to 0; nobody gets their grade.
        $authorless = $this->generator->create_reply($question, $this->teacher);
        $DB->set_field('moodleoverflow_posts', 'userid', 0, ['id' => $authorless->get_id()]);

        // Authors and raters get a grade, also when their reputation is 0 like the teacher's.
        service\rating::update_all_grades($modflow);
        $grades = $DB->get_records_menu('moodleoverflow_grades', ['moodleoverflowid' => $modflow->id], '', 'userid, grade');
        $this->assertEquals([$this->otherstudent->id => 10, $this->student->id => 1, $this->teacher->id => 0], $grades);
    }

    // External function.

    /**
     * Test, if record_vote calls the use case of the rating and returns the new state of the post.
     */
    public function test_record_vote(): void {
        $postid = $this->answer->get_id();
        $call = fn(int $postid, int $rating, bool $remove = false) => external_api::clean_returnvalue(
            record_vote::execute_returns(),
            record_vote::execute($postid, $rating, $remove)
        );

        $this->setUser($this->student);
        $result = $call($postid, rating_type::UPVOTE->value);
        $this->assertEquals(['postrating' => 1, 'ownerreputation' => 10, 'raterreputation' => 1,
            'ownerid' => $this->otherstudent->id, 'uservote' => rating_type::UPVOTE->value, 'canchange' => true,
            'markedposts' => [], ], $result);

        $result = $call($postid, rating_type::UPVOTE->value, true);
        $this->assertEquals(0, $result['postrating']);
        $this->assertEquals(0, $result['uservote']);
        $this->assertNull(service\rating::get_user_vote($this->answer, $this->student->id));

        // Marks return all posts with the mark, so the page can show where the mark moved.
        $this->setUser($this->teacher);
        $result = $call($this->teacheranswer->get_id(), rating_type::SOLVED->value);
        $this->assertEquals([$this->teacheranswer->get_id()], $result['markedposts']);
        $result = $call($postid, rating_type::SOLVED->value);
        $this->assertEquals([$postid], $result['markedposts']);
        $this->assertEquals([$postid], service\rating::get_marked_post_ids($this->get_discussion(), rating_type::SOLVED));
        $result = $call($postid, rating_type::SOLVED->value, true);
        $this->assertEquals([], $result['markedposts']);
        $this->assertEquals([], service\rating::get_marked_post_ids($this->get_discussion(), rating_type::SOLVED));

        // Only the values of rating_type are ratings.
        foreach ([0, 5, 10, 20] as $invalid) {
            $this->assert_refused(fn() => record_vote::execute($postid, $invalid), 'invalidratingid');
        }
    }

    // Sorting of answers.

    /**
     * Tests service\rating::sort_answers().
     * Test case: Every group of rating exists (helful and solved posts, only helpful/solved and none)
     */
    public function test_answersorting_everygroup(): void {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test with every group of rating.

        // Create a array of the posts, save the sorted post and compare them to the order that they should have.
        $posts = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->answer1, $this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Change the rating preference of the teacher and sort again.
        $this->set_ratingpreferences(1);
        $rightorder = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Tests service\rating::sort_answers().
     * Test case: One group of rating does not exist
     */
    public function test_answersorting_threegroups(): void {
        // Create helpful, solved, up and downvotes ratings.
        $this->create_everygroup();

        // Test without posts that are only marked as solved.
        $posts = [$this->answer1, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->answer1, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Test without posts that are only marked as helpful.
        $posts = [$this->answer1, $this->answer2, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->answer1, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Test without posts that are marked as both helpful and solved.
        $posts = [$this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->set_ratingpreferences(0);
        $rightorder = [$this->answer3, $this->answer2, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);

        $this->set_ratingpreferences(1);
        $rightorder = [$this->answer2, $this->answer3, $this->answer4, $this->answer6, $this->answer5];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Tests service\rating::sort_answers().
     * Test case: two groups of rating do not exist
     */
    public function test_answersorting_twogroups(): void {
        $this->set_ratingpreferences(0);

        // Test case 1: helpful and solved post, only solved posts.
        $this->process_groups('sh', 's');

        // Test case 2: helpful and solved post, only helpful posts.
        $this->process_groups('sh', 'h');

        // Test case 3: helpful and solved post, not-marked posts.
        $this->process_groups('sh', 'o');

        // Test case 4: only solved posts and only helpful posts with ratingpreferences = 0.
        $this->set_ratingpreferences(0);
        $rightorder = [$this->answer6, $this->answer5, $this->answer4, $this->answer2, $this->answer1, $this->answer3];
        $this->process_groups('s', 'h', $rightorder);

        // Test case 5: only solved posts and only helpful posts with ratingpreferences = 1.
        $this->set_ratingpreferences(1);
        $this->process_groups('s', 'h');

        // Test case 6: only solved posts and not-marked posts.
        $this->create_twogroups('s', 'o');
        $posts = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5, $this->answer4];
        $this->assert_order($posts, $rightorder);

        // Test case 6: only helpful posts and not-marked posts.
        $this->process_groups('h', 'o');
    }

    /**
     * Tests service\rating::sort_answers().
     * Test case: Only one group of rating exists, so only:
     * - helpful and solved posts, or
     * - helpful, or
     * - solved, or
     * - not marked
     * Extended Test Case: If the votesdifference is the same, the post should be sorted by their time of creation/modification.
     */
    public function test_answersorting_onegroup(): void {
        $this->set_ratingpreferences(0);

        // Test case 1: only solved and helpful posts.
        $this->create_onegroup('sh');
        $posts = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = [$this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 2: only solvedposts.
        $this->create_onegroup('s');
        $rightorder = [$this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 3: only helpful posts.
        $this->create_onegroup('h');
        $rightorder = [$this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);

        // Test case 4: only not marked posts.
        $this->create_onegroup('o');
        $rightorder = [$this->answer4, $this->answer6, $this->answer3, $this->answer1, $this->answer2, $this->answer5];
        $this->assert_order($posts, $rightorder);

        // Now set the votesdifference equal for this group, sort again and check if they are sorted correctly after time.
        $this->set_votesdifference();
        $rightorder = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $this->assert_order($posts, $rightorder);
    }

    // Helper functions.

    /**
     * Creates a moodleoverflow with a question of the student and answers of the other student and the teacher.
     *
     * @param array $options Settings of the moodleoverflow.
     * @return array [moodleoverflow, question, answer, teacher answer]
     */
    private function create_discussion(array $options = []): array {
        $modflow = isset($options['course'])
            ? moodleoverflow::from_id($this->getDataGenerator()->create_module('moodleoverflow', $options)->id)
            : $this->create_moodleoverflow($options);
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->otherstudent);
        $teacheranswer = $this->generator->create_reply($question, $this->teacher);
        return [$modflow, $question, $answer, $teacheranswer];
    }

    /**
     * Creates a discussion whose answer of the other student is upvoted by the teacher (+10 reputation).
     *
     * @param array $options Settings of the moodleoverflow.
     * @return array [moodleoverflow, question, answer, teacher answer]
     */
    private function create_upvoted_discussion(array $options): array {
        $created = $this->create_discussion($options);
        $this->generator->create_rating($created[2], $this->teacher, rating_type::UPVOTE);
        return $created;
    }

    /**
     * Loads the discussion of the setUp freshly, so it contains all its posts.
     *
     * @return discussion
     */
    private function get_discussion(): discussion {
        return discussion::from_id($this->question->get_discussionid());
    }

    /**
     * Returns the rating events from an event sink.
     *
     * @param \phpunit_event_sink $sink
     * @return \core\event\base[]
     */
    private function get_rating_events(\phpunit_event_sink $sink): array {
        $classes = [rating_created::class, rating_updated::class, rating_deleted::class];
        return array_values(array_filter($sink->get_events(), fn($event) => in_array(get_class($event), $classes)));
    }

    /**
     * Returns the grade of a user from the grade table of the plugin.
     *
     * @param moodleoverflow $modflow
     * @param stdClass $user
     * @return float|null Null if the user has no grade.
     */
    private function get_grade(moodleoverflow $modflow, stdClass $user): ?float {
        global $DB;
        $grade = $DB->get_field('moodleoverflow_grades', 'grade', ['moodleoverflowid' => $modflow->id, 'userid' => $user->id]);
        return $grade === false ? null : (float) $grade;
    }

    /**
     * Checks that an action is refused with an error code.
     *
     * @param callable $action
     * @param string $errorcode
     */
    private function assert_refused(callable $action, string $errorcode): void {
        try {
            $action();
            $this->fail("The action was not refused with $errorcode.");
        } catch (\moodle_exception $e) {
            $this->assertEquals($errorcode, $e->errorcode);
        }
    }

    /**
     * Creates six answers to sort, modified one after the other.
     */
    private function create_sorting_answers(): void {
        foreach (range(1, 6) as $i) {
            $this->{'answer' . $i} = (object) ['id' => $i, 'modified' => $i];
        }
    }

    /**
     * Sorts the answers and checks that they are in the expected order. The reversed input must give the same order,
     * so the order comes from the ratings and the time of modification, not from the input.
     *
     * @param array $posts The answers to sort.
     * @param array $rightorder The answers in the order they should have.
     */
    private function assert_order(array $posts, array $rightorder): void {
        foreach ([$posts, array_reverse($posts)] as $input) {
            $sorted = service\rating::sort_answers($input, $this->preference);
            $this->assertEquals(array_column($rightorder, 'id'), array_keys($sorted));
        }
    }

    /**
     * Returns the six answers.
     *
     * @return stdClass[]
     */
    private function answers(): array {
        return [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
    }

    /**
     * Sets the ratingpreferences to 1 or 0:
     * 1 = solved posts will be shown above helpful posts.
     * 0 = helpful posts will be shown above solved posts.
     * @param int  $preference  the rating preference
     */
    private function set_ratingpreferences($preference) {
        $this->preference = rating_preference::from($preference);
    }

    /**
     * Sets the the votesdifference equal.
     * The Right Order now (if the answers are in the same group) is:
     * answer1, answer2, answer3, answer4, answer5, answer6.
     * @return void
     */
    private function set_votesdifference() {
        foreach ($this->answers() as $answer) {
            $this->set_votes($answer, 1, 1);
        }
    }

    // Creation functions, that create different rating situations of the posts in a discussion.

    /**
     * creates a rating of every type by adding attributes to the post:
     * - post that is solved and helpful
     * . post that is only helpful
     * - post that is only solved
     * - post that is not marked
     */
    private function create_everygroup() {
        // Answer => group, upvotes, downvotes.
        $ratings = [['sh', 0, 0], ['s', 0, 0], ['h', 0, 0], ['o', 1, 0], ['o', 0, 1], ['o', 0, 0]];
        foreach ($this->answers() as $i => $answer) {
            [$group, $upvotes, $downvotes] = $ratings[$i];
            $this->set_group($group, $answer);
            $this->set_votes($answer, $upvotes, $downvotes);
        }
    }

    /**
     * Creates a rating of one group for every post in the discussion
     * Creates up and downvotes
     * @param string $group
     * A Group can be:
     * - both as solution and helpful marked posts (sh)
     * - only solution posts (s)
     * - only helpful (h)
     * - no mark (o)
     */
    private function create_onegroup($group) {
        // Answer => upvotes, downvotes. Votesdifference: 0, -1, 1, 5, -2, 2.
        $votes = [[4, 4], [1, 2], [3, 2], [5, 0], [0, 2], [4, 2]];
        foreach ($this->answers() as $i => $answer) {
            $this->set_group($group, $answer);
            $this->set_votes($answer, ...$votes[$i]);
        }
        // Rightorder = answer4 , answer6, answer3, answer1, answer2, answer5.
    }

    /**
     * Creates ratings of the posts of the assigned groups in the discussion.
     * Creates up and downvotes
     * @param string $group1 Group of the first three answers.
     * @param string $group2 Group of the last three answers.
     * A Group can be:
     * - both as solution and helpful marked posts (sh)
     * - only solution posts (s)
     * - only helpful (h)
     * - no mark (o)
     */
    private function create_twogroups($group1, $group2) {
        // Answer => upvotes, downvotes. Votesdifference: -1, 3, -2, 0, 1, 2.
        $votes = [[3, 4], [4, 1], [0, 2], [5, 5], [6, 5], [4, 2]];
        foreach ($this->answers() as $i => $answer) {
            $this->set_group($i < 3 ? $group1 : $group2, $answer);
            $this->set_votes($answer, ...$votes[$i]);
        }

        // The Rightorder depends now on the group parameter.
        // Rightorder (sh,s) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (sh,h) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (sh,o) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (s,h) = answer6, answer5, answer4, answer2, answer1, answer3. with ratingpreference = 0.
        // Rightorder (s,h) = answer2, answer1, answer3, answer6, answer5, answer4. with ratingpreference = 1
        // Rightorder (s,o) = answer2, answer1, answer3, answer6, answer5, answer4.
        // Rightorder (h,o) = answer2, answer1, answer3, answer6, answer5, answer4.
    }

    /**
     * Executing the sort function and comparing the sorted post to the expected order.
     * @param string $group1
     * @param string $group2
     * @param array $orderposts
     * @return void
     */
    private function process_groups(string $group1, string $group2, array $orderposts = []) {
        $this->create_twogroups($group1, $group2);
        $posts = [$this->answer1, $this->answer2, $this->answer3, $this->answer4, $this->answer5, $this->answer6];
        $rightorder = $orderposts ?: [$this->answer2, $this->answer1, $this->answer3, $this->answer6, $this->answer5,
            $this->answer4, ];
        $this->assert_order($posts, $rightorder);
    }

    /**
     * Sets a post to a group of mark.
     * @param string $group Group can be: solved+helpful (sh), solved (s), helpful (h), not marked (o)
     * @param object $answer
     */
    private function set_group(string $group, object $answer) {
        $answer->markedhelpful = (int) in_array($group, ['sh', 'h']);
        $answer->markedsolution = (int) in_array($group, ['sh', 's']);
    }

    /**
     * Sets the votes and votesdifference of a post.
     * @param mixed $answer     The post
     * @param int $upvotes
     * @param int $downvotes
     * @return void
     */
    private function set_votes($answer, $upvotes, $downvotes) {
        $answer->upvotes = $upvotes;
        $answer->downvotes = $downvotes;
        $answer->votesdifference = $answer->upvotes - $answer->downvotes;
    }
}
