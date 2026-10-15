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

namespace mod_moodleoverflow\local\enum;

/**
 * The kind of rating of a post.
 *
 * The values are the ones stored in the column moodleoverflow_ratings.rating. A user has at most one vote per post;
 * marks are set by the question author (helpful) or a teacher (solved). The reputation a rating gives is not the value
 * but comes from the admin settings votescale*, so it can change without touching the stored ratings.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum rating_type: int {
    // A user voted the post down.
    case DOWNVOTE = 1;

    // A user voted the post up.
    case UPVOTE = 2;

    // A teacher marked the post as solution.
    case SOLVED = 3;

    // The question author marked the post as helpful.
    case HELPFUL = 4;

    /**
     * Whether this rating is a vote (one per user and post).
     *
     * @return bool
     */
    public function is_vote(): bool {
        return $this === self::DOWNVOTE || $this === self::UPVOTE;
    }

    /**
     * Whether this rating is a mark (solved or helpful).
     *
     * @return bool
     */
    public function is_mark(): bool {
        return $this === self::SOLVED || $this === self::HELPFUL;
    }

    /**
     * The number of reputation points the author of the rated post gets for this rating.
     * @return int
     */
    public function get_author_reputation(): int {
        $setting = match ($this) {
            self::DOWNVOTE => 'votescaledownvote',
            self::UPVOTE => 'votescaleupvote',
            self::SOLVED => 'votescalesolved',
            self::HELPFUL => 'votescalehelpful',
        };
        return (int) get_config('moodleoverflow', $setting);
    }

    /**
     * The number of reputation points the user who rates gets for this rating.
     * Only voting is rewarded, marking is not.
     * @return int
     */
    public function get_rater_reputation(): int {
        return $this->is_vote() ? (int) get_config('moodleoverflow', 'votescalevote') : 0;
    }
}
