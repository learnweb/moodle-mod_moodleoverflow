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

namespace mod_moodleoverflow\local\dto;

/**
 * DTO for the state of a post after a vote or a mark, so the discussion page can show it.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_dto {
    use dto;

    /**
     * Constructor.
     *
     * @param int $postrating upvotes minus downvotes of the post
     * @param int|null $ownerreputation reputation of the author, null if the user may not see it
     * @param int $raterreputation reputation of the user who rated
     * @param int|null $ownerid id of the author, null if the user may not see the author
     * @param string|null $uservote vote of the user on the post: "up", "down" or null
     * @param bool $canchange whether the user can still change the vote
     * @param int[] $markedposts posts of the discussion with the mark that was changed, empty for votes
     */
    public function __construct(
        /** @var int $postrating upvotes minus downvotes of the post */
        public readonly int $postrating,
        /** @var int|null $ownerreputation reputation of the author, null if the user may not see it */
        public readonly ?int $ownerreputation,
        /** @var int $raterreputation reputation of the user who rated */
        public readonly int $raterreputation,
        /** @var int|null $ownerid id of the author, null if the user may not see the author */
        public readonly ?int $ownerid,
        /** @var string|null $uservote vote of the user on the post: "up", "down" or null */
        public readonly ?string $uservote,
        /** @var bool $canchange whether the user can still change the vote */
        public readonly bool $canchange,
        /** @var int[] $markedposts posts of the discussion with the mark that was changed, empty for votes */
        public readonly array $markedposts
    ) {
    }
}
