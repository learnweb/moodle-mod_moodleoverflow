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
 * The content of a post as the user wrote it: the message, the draft areas of its files and the subject.
 *
 * Built by the post form and passed to the posting service and the models when a post is added or edited.
 * @package     mod_moodleoverflow
 * @copyright   2026 Tamaro Walter
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class post_content {
    /**
     * Constructor.
     *
     * @param string $message The message as entered in the editor (raw, not formatted)
     * @param int $messageformat The format of the message, one of the FORMAT_* constants
     * @param int|null $messagedraftid Draft item id of the editor with the files embedded in the message, null: no draft area
     * @param int|null $attachmentdraftid Draft item id of the attachment file manager, null: no file manager (nothing is saved)
     * @param string|null $subject The subject (the name of the discussion), only for the question post; null: no subject
     */
    public function __construct(
        /** @var string The message as entered in the editor (raw, not formatted) */
        public readonly string $message,
        /** @var int The format of the message, one of the FORMAT_* constants */
        public readonly int $messageformat,
        /** @var ?int Draft item id of the editor with the files embedded in the message, null: no draft area */
        public readonly ?int $messagedraftid = null,
        /** @var ?int Draft item id of the attachment file manager, null: no file manager (nothing is saved) */
        public readonly ?int $attachmentdraftid = null,
        /** @var ?string The subject (the name of the discussion), only for the question post; null: no subject */
        public readonly ?string $subject = null,
    ) {
    }
}
