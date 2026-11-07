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
 * PHPUnit tests for the user statistics.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_moodleoverflow;

use mod_moodleoverflow\local\dto\userstats\activity_day_dto;
use mod_moodleoverflow\local\dto\userstats\userstats_dto;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * PHPUnit tests for the user statistics.
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \mod_moodleoverflow\local\service\userstats
 */
#[CoversClass(service\userstats::class)]
final class userstats_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    /** @var moodleoverflow test moodleoverflow */
    private $modflow;

    /** @var post the question of the student */
    private $question1;

    /** @var post the question of the other student */
    private $question2;

    /** @var post the answer of the other student to the question of the student */
    private $answer1;

    /** @var post the answer of the student to the question of the other student */
    private $answer2;

    /**
     * Creates a course with a moodleoverflow, in which both students asked a question and answered the other one.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('showuserstats', 1, 'moodleoverflow');
        $this->setup_course();
        $this->modflow = $this->create_moodleoverflow();
        $this->question1 = $this->generator->create_question($this->modflow, $this->student);
        $this->question2 = $this->generator->create_question($this->modflow, $this->otherstudent);
        $this->answer1 = $this->generator->create_reply($this->question1, $this->otherstudent);
        $this->answer2 = $this->generator->create_reply($this->question2, $this->student);
    }

    /**
     * Test, if received votes are counted for the author of the post.
     */
    public function test_votes(): void {
        $this->generator->create_rating($this->answer1, $this->teacher, rating_type::UPVOTE);
        $this->generator->create_rating($this->answer2, $this->teacher, rating_type::DOWNVOTE);

        $this->assertEquals(1, $this->get_stat($this->otherstudent, 'forumupvotes'));
        $this->assertEquals(1, $this->get_stat($this->student, 'forumdownvotes'));
        $this->assertEquals(0, $this->get_stat($this->student, 'forumupvotes'));
    }

    /**
     * Test, if every reviewed post and every given rating counts one point of activity.
     */
    public function test_activity(): void {
        // The student wrote 2 posts and rates 3 times.
        $this->generator->create_rating($this->answer1, $this->student, rating_type::HELPFUL);
        $this->generator->create_rating($this->answer1, $this->student, rating_type::UPVOTE);
        $this->generator->create_rating($this->question2, $this->student, rating_type::DOWNVOTE);
        $this->assertEquals(5, $this->get_stat($this->student, 'forumactivity'));

        // A post that waits for its review is not published yet and does not count.
        $reviewed = $this->create_moodleoverflow(['needsreview' => review_level::EVERYTHING->value]);
        $this->generator->create_question($reviewed, $this->student);
        $this->assertEquals(0, $this->get_stat($this->student, 'forumactivity', $reviewed));
    }

    /**
     * Test, if the reputation of this forum and of the course are the ones the rating service computes.
     */
    public function test_reputation(): void {
        $this->generator->create_rating($this->answer1, $this->student, rating_type::HELPFUL);
        $this->generator->create_rating($this->answer1, $this->student, rating_type::UPVOTE);
        $this->generator->create_rating($this->answer1, $this->teacher, rating_type::SOLVED);

        // Only forums that share their reputation count for the course.
        $shared = $this->create_moodleoverflow(['coursewidereputation' => 1]);
        $sharedquestion = $this->generator->create_question($shared, $this->otherstudent);
        $this->generator->create_rating($sharedquestion, $this->teacher, rating_type::UPVOTE);

        $points = fn(string $name) => (int) get_config('moodleoverflow', 'votescale' . $name);
        $expected = $points('helpful') + $points('upvote') + $points('solved');
        $this->assertEquals($expected, $this->get_stat($this->otherstudent, 'forumreputation'));
        $this->assertEquals($points('vote'), $this->get_stat($this->student, 'forumreputation'));
        $this->assertEquals($points('upvote'), $this->get_stat($this->otherstudent, 'coursereputation'));
    }

    /**
     * Test, if posts, marks and received votes of hidden authors do not count, while votes always count for the rater.
     */
    public function test_anonymity(): void {
        global $DB;
        $DB->set_field('moodleoverflow', 'anonymous', anonymity::QUESTIONS->value, ['id' => $this->modflow->id]);
        $this->generator->create_rating($this->answer1, $this->student, rating_type::HELPFUL);
        $this->generator->create_rating($this->answer1, $this->student, rating_type::UPVOTE);
        $this->generator->create_rating($this->question2, $this->student, rating_type::UPVOTE);

        // The student's question and helpful mark would reveal the questioner; the answer and the votes count.
        $this->assertEquals(3, $this->get_stat($this->student, 'forumactivity'));
        // The other student's question is hidden, so its upvote does not count; the answer's upvote does.
        $this->assertEquals(1, $this->get_stat($this->otherstudent, 'forumupvotes'));

        $DB->set_field('moodleoverflow', 'anonymous', anonymity::EVERYTHING->value, ['id' => $this->modflow->id]);
        $this->assertEquals(2, $this->get_stat($this->student, 'forumactivity'));
        $this->assertEquals(0, $this->get_stat($this->otherstudent, 'forumupvotes'));
    }

    /**
     * Test, if the course numbers add up the forums whose statistics the viewer may see.
     */
    public function test_course_numbers(): void {
        global $DB;
        $other = $this->create_moodleoverflow();
        $this->generator->create_question($other, $this->student);
        $this->assertEquals(2, $this->get_stat($this->student, 'forumactivity'));
        $this->assertEquals(3, $this->get_stat($this->student, 'courseactivity'));

        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/moodleoverflow:viewanyrating', CAP_PROHIBIT, $teacherrole, $other->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertEquals(2, $this->get_stat($this->student, 'courseactivity'));
    }

    /**
     * Test, if only users with an active enrolment who can see the forum are listed, and only for those who may see
     * the statistics.
     */
    public function test_participants(): void {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($instance, $this->otherstudent->id, ENROL_USER_SUSPENDED);

        $userids = array_map(fn(userstats_dto $dto) => $dto->userid, $this->get_statistics());
        $this->assertEqualsCanonicalizing([$this->teacher->id, $this->student->id], $userids);

        $this->expectException(\moodle_exception::class);
        service\userstats::get_statistics($this->modflow, $this->student->id);
    }

    /**
     * Test, if every user links to their course profile and to the overview of their posts in the course.
     */
    public function test_urls(): void {
        $statistics = $this->get_statistics();
        $this->assertNotEmpty($statistics);
        foreach ($statistics as $dto) {
            $profileurl = new \moodle_url('/user/view.php', ['id' => $dto->userid, 'course' => $this->course->id]);
            $postsurl = new \moodle_url('/mod/moodleoverflow/user.php', ['user' => $dto->userid, 'course' => $this->course->id]);
            $this->assertEquals($profileurl->out(false), $dto->profileurl);
            $this->assertEquals($postsurl->out(false), $dto->postsurl);
        }
    }

    /**
     * Test, if the timeline counts the reviewed posts and the votes per day in the timezone of the viewer, for every
     * forum of the course whose statistics the viewer may see.
     */
    public function test_timeline(): void {
        global $DB;
        $berlin = new \DateTimeZone('Europe/Berlin');
        $time = fn(string $datetime) => (new \DateTimeImmutable($datetime, $berlin))->getTimestamp();
        $created = fn(post $post, string $datetime) =>
            $DB->set_field('moodleoverflow_posts', 'created', $time($datetime), ['id' => $post->get_id()]);
        $created($this->question1, '2026-10-24 23:30');
        $created($this->question2, '2026-10-25 00:30');
        $created($this->answer1, '2026-10-25 10:00');
        $created($this->answer2, '2026-10-27 10:00');
        // A post waiting for its review does not count.
        $DB->set_field('moodleoverflow_posts', 'reviewed', 0, ['id' => $this->answer2->get_id()]);

        // Votes count on the day they were given, marks do not count.
        $rated = fn(\stdClass $rating, string $datetime) =>
            $DB->set_field('moodleoverflow_ratings', 'firstrated', $time($datetime), ['id' => $rating->id]);
        $rated($this->generator->create_rating($this->question2, $this->student, rating_type::DOWNVOTE), '2026-10-25 12:00');
        $rated($this->generator->create_rating($this->answer1, $this->teacher, rating_type::UPVOTE), '2026-10-26 12:00');
        $rated($this->generator->create_rating($this->answer1, $this->otherstudent, rating_type::HELPFUL), '2026-10-26 12:00');

        $days = fn(array $dtos) => array_map(fn(activity_day_dto $dto) => [$dto->day, $dto->posts, $dto->votes], $dtos);
        $timelines = fn(\DateTimeZone $timezone) =>
            array_map($days, service\userstats::get_page_data($this->modflow, $this->teacher->id, $timezone)->timeline);
        $expected = [['2026-10-24', 1, 0], ['2026-10-25', 2, 1], ['2026-10-26', 0, 1]];
        $this->assertEquals([$this->modflow->id => $expected], $timelines($berlin));

        // The days start at midnight of the viewer: in London, the first two posts were written on the same day.
        $london = [['2026-10-24', 2, 0], ['2026-10-25', 1, 1], ['2026-10-26', 0, 1]];
        $this->assertEquals([$this->modflow->id => $london], $timelines(new \DateTimeZone('Europe/London')));

        // Every other forum has its own timeline, also without activity, as long as the viewer may see its statistics.
        $other = $this->create_moodleoverflow();
        $empty = $this->create_moodleoverflow();
        $created($this->generator->create_question($other, $this->student), '2026-10-25 12:00');
        $this->assertEquals(
            [$this->modflow->id => $expected, $other->id => [['2026-10-25', 1, 0]], $empty->id => []],
            $timelines($berlin)
        );

        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        assign_capability('mod/moodleoverflow:viewanyrating', CAP_PROHIBIT, $teacherrole, $other->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertEquals([$this->modflow->id => $expected, $empty->id => []], $timelines($berlin));
    }

    /**
     * Returns the statistics of the moodleoverflow as the teacher sees them.
     *
     * @param moodleoverflow|null $modflow The moodleoverflow, the test moodleoverflow by default.
     * @return userstats_dto[]
     */
    private function get_statistics(?moodleoverflow $modflow = null): array {
        return service\userstats::get_statistics($modflow ?? $this->modflow, $this->teacher->id);
    }

    /**
     * Returns one number of the statistics of a user.
     *
     * @param \stdClass $user
     * @param string $field A field of userstats_dto, e.g. 'forumactivity'.
     * @param moodleoverflow|null $modflow The moodleoverflow, the test moodleoverflow by default.
     * @return int
     */
    private function get_stat(\stdClass $user, string $field, ?moodleoverflow $modflow = null): int {
        foreach ($this->get_statistics($modflow) as $dto) {
            if ($dto->userid == $user->id) {
                return $dto->$field;
            }
        }
        $this->fail("The user $user->id is not listed in the statistics.");
    }
}
