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

use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\output\author;
use mod_moodleoverflow\tests\moodleoverflow_test_trait;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests how the author of a post is shown.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\output\author
 * @covers \mod_moodleoverflow\local\models\discussion::get_pseudonym
 */
#[CoversClass(author::class)]
#[CoversClass(discussion::class)]
final class author_test extends \advanced_testcase {
    use moodleoverflow_test_trait;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_course();
    }

    /**
     * Test, if the author is shown by name, as "you", by pseudonym, or as anonymous when the author was removed.
     */
    public function test_export_for_template(): void {
        global $DB;
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::QUESTIONS->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $answer = $this->generator->create_reply($question, $this->teacher);

        $this->assertEquals('Questioner', $this->get_name($question, $this->teacher->id));
        $this->assertEquals('Anonymous (You)', $this->get_name($question, $this->student->id));
        $this->assertEquals(fullname($this->teacher), $this->get_name($answer, $this->student->id));

        // The privacy provider removed the author; load the post again to see the change.
        $DB->set_field('moodleoverflow_posts', 'userid', 0, ['id' => $answer->get_id()]);
        $this->assertEquals('Anonymous', $this->get_name(post::from_id($answer->get_id()), $this->student->id));
    }

    /**
     * Test, if hidden authors get pseudonyms numbered by their first post, so that readers can tell them apart.
     */
    public function test_pseudonyms(): void {
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $first = $this->generator->create_reply($question, $this->otherstudent);
        $second = $this->generator->create_reply($question, $this->teacher);
        $again = $this->generator->create_reply($second, $this->otherstudent);

        $this->assertEquals('Anonymous (You)', $this->get_name($question, $this->student->id));
        $this->assertEquals('Answerer #1', $this->get_name($first, $this->student->id));
        $this->assertEquals('Answerer #2', $this->get_name($second, $this->student->id));
        $this->assertEquals('Answerer #1', $this->get_name($again, $this->student->id));
    }

    /**
     * Test, if an author who posts with their name is shown by name in an anonymous moodleoverflow, while the numbers
     * of the hidden authors stay the same.
     */
    public function test_postwithname(): void {
        $modflow = $this->create_moodleoverflow(['anonymous' => anonymity::EVERYTHING->value]);
        $question = $this->generator->create_question($modflow, $this->student);
        $teacheranswer = $this->generator->create_reply($question, $this->teacher);
        $answer = $this->generator->create_reply($question, $this->otherstudent);

        $this->allow_capability('mod/moodleoverflow:postwithname', 'editingteacher', $modflow);
        $this->assertEquals(fullname($this->teacher), $this->get_name($teacheranswer, $this->student->id));
        $this->assertEquals(fullname($this->teacher), $this->get_name($teacheranswer, $this->teacher->id));
        $this->assertEquals('Answerer #2', $this->get_name($answer, $this->student->id));
    }

    /**
     * Returns the author name of a post as a user sees it.
     *
     * @param post $post
     * @param int $userid
     * @return string
     */
    private function get_name(post $post, int $userid): string {
        global $PAGE;
        return (new author($post, $userid))->export_for_template($PAGE->get_renderer('core'))['name'];
    }
}
