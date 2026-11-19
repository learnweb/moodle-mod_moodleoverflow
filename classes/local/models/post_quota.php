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

namespace mod_moodleoverflow\local\models;

/**
 * The posts a user has written within the period of the post limits of a moodleoverflow, and what follows from them:
 * whether the user can write another question or answer, and from when on.
 * Built by moodleoverflow::get_post_quota(). A page that checks many posts loads it once and passes it on.
 *
 * @package     mod_moodleoverflow
 * @copyright   2026 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class post_quota {
    /**
     * Constructor.
     *
     * @param int $moodleoverflowid Id of the moodleoverflow the quota belongs to
     * @param int $userid Id of the user the quota belongs to
     * @param int $questionlimit Number of questions per period, 0: no limit
     * @param int $answerlimit Number of answers and comments per period, 0: no limit
     * @param int $period Length of the period in seconds, 0: in total
     * @param int[] $questiontimes Creation times of the user's questions within the period, oldest first
     * @param int[] $answertimes Creation times of the user's answers and comments within the period, oldest first
     */
    public function __construct(
        /** @var int Id of the moodleoverflow the quota belongs to */
        public readonly int $moodleoverflowid,
        /** @var int Id of the user the quota belongs to */
        public readonly int $userid,
        /** @var int Number of questions per period, 0: no limit */
        public readonly int $questionlimit,
        /** @var int Number of answers and comments per period, 0: no limit */
        public readonly int $answerlimit,
        /** @var int Length of the period in seconds, 0: in total */
        public readonly int $period,
        /** @var int[] Creation times of the user's questions within the period, oldest first */
        public readonly array $questiontimes,
        /** @var int[] Creation times of the user's answers and comments within the period, oldest first */
        public readonly array $answertimes,
    ) {
    }

    /**
     * Whether the user can write another question or answer.
     * @param bool $question True for a question, false for an answer or a comment
     * @return bool
     */
    public function has_left(bool $question): bool {
        $limit = $question ? $this->questionlimit : $this->answerlimit;
        return $limit === 0 || count($question ? $this->questiontimes : $this->answertimes) < $limit;
    }

    /**
     * When the user can write the next question or answer, once the limit is reached: as soon as enough posts have
     * left the period that fewer than the limit remain.
     * @param bool $question True for a question, false for an answer or a comment
     * @return int|null Timestamp; null if the user has posts left, or never again because the limit counts in total
     */
    public function available_at(bool $question): ?int {
        if ($this->has_left($question) || $this->period === 0) {
            return null;
        }
        $times = $question ? $this->questiontimes : $this->answertimes;
        $limit = $question ? $this->questionlimit : $this->answerlimit;
        // The limit may have been lowered, so more posts than the limit can be in the period.
        return $times[count($times) - $limit] + $this->period;
    }
}
