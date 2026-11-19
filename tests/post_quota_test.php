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

use mod_moodleoverflow\local\models\post_quota;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the rule of the post limits: whether a user has posts left, and from when on the next post is possible.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_moodleoverflow\local\models\post_quota
 */
#[CoversClass(post_quota::class)]
final class post_quota_test extends \basic_testcase {
    /**
     * Test, if questions and answers are limited separately, and 0 means no limit.
     */
    public function test_has_left(): void {
        $quota = new post_quota(1, 2, 1, 3, DAYSECS, [100], [100, 200]);
        $this->assertFalse($quota->has_left(true));
        $this->assertTrue($quota->has_left(false));

        $nolimit = new post_quota(1, 2, 0, 0, DAYSECS, [100, 200], [100, 200]);
        $this->assertTrue($nolimit->has_left(true));
        $this->assertTrue($nolimit->has_left(false));
    }

    /**
     * Test, if the next post is possible once enough posts have left the period, also after the limit was lowered,
     * and never again when the limit counts in total.
     */
    public function test_available_at(): void {
        $quota = new post_quota(1, 2, 0, 2, DAYSECS, [], [100, 200]);
        $this->assertEquals(100 + DAYSECS, $quota->available_at(false));

        // Five posts and a limit of two: four have to leave the period, so the fourth oldest decides.
        $lowered = new post_quota(1, 2, 0, 2, DAYSECS, [], [100, 200, 300, 400, 500]);
        $this->assertEquals(400 + DAYSECS, $lowered->available_at(false));

        $postsleft = new post_quota(1, 2, 0, 3, DAYSECS, [], [100, 200]);
        $this->assertNull($postsleft->available_at(false));
        $intotal = new post_quota(1, 2, 0, 2, 0, [], [100, 200]);
        $this->assertNull($intotal->available_at(false));
    }
}
