<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

use completion_info;
use mod_moodleoverflow\completion\custom_completion;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\local\service\review;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests the completion rule "write at least n posts" and its update by the services.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\completion\custom_completion
 */
#[CoversClass(custom_completion::class)]
final class custom_completion_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course(['enablecompletion' => 1]);
    }

    /**
     * Test, if questions, answers and comments count, and if deleting a post takes the completion back.
     */
    public function test_completionposts(): void {
        $modflow = $this->create_moodleoverflow(['completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionposts' => 2]);
        $question = posting::create_discussion($modflow, $this->student->id, $this->content('Question', 'Topic'))
            ->get_first_post();
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->get_state($modflow, $this->student->id));

        $answer = posting::reply($question, $this->student->id, $this->content('Answer'));
        $this->assertEquals(COMPLETION_COMPLETE, $this->get_state($modflow, $this->student->id));

        posting::delete($answer, $this->student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->get_state($modflow, $this->student->id));
    }

    /**
     * Test, if a post that waits for its review counts once it is approved.
     */
    public function test_completionposts_counts_reviewed_posts(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow([
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionposts' => 1,
            'needsreview' => review_level::EVERYTHING->value,
        ]);
        $question = posting::create_discussion($modflow, $this->student->id, $this->content('Question', 'Topic'))
            ->get_first_post();
        $this->assertEquals(COMPLETION_INCOMPLETE, $this->get_state($modflow, $this->student->id));

        // The question was written before the review delay, so it can be reviewed now.
        $DB->set_field('moodleoverflow_posts', 'created', 100, ['id' => $question->get_id()]);
        review::approve(post::from_id($question->get_id()), $this->teacher->id);
        $this->assertEquals(COMPLETION_COMPLETE, $this->get_state($modflow, $this->student->id));
    }

    /**
     * Returns the completion state of a user in a moodleoverflow.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return int
     */
    private function get_state(moodleoverflow $modflow, int $userid): int {
        $completion = new completion_info($this->course);
        return (int) $completion->get_data($modflow->get_cm(), false, $userid)->completionstate;
    }

    /**
     * Content of a post in plain HTML, without files.
     *
     * @param string $message
     * @param string|null $subject The subject, only for a question
     * @return post_content
     */
    private function content(string $message, ?string $subject = null): post_content {
        return new post_content($message, FORMAT_HTML, null, null, $subject);
    }
}
