<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\route\schema;

use core\exception\coding_exception;
use core\param;
use core\router\schema\objects\schema_object;
use core\router\schema\objects\scalar_type;

/**
 * Schema of a single user post. Must be equal to the userpost_dto.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userpost_schema extends schema_object {
    /**
     * Constructor.
     *
     * @param bool $required Whether every field must be non-null.
     * @throws coding_exception
     */
    public function __construct(bool $required = false) {
        parent::__construct(
            content: [
                'id' => new scalar_type(param::INT, required: $required),
                'message' => new scalar_type(param::RAW, required: $required),
                'discussionsubject' => new scalar_type(param::TEXT, required: $required),
                'modflow' => new scalar_type(param::TEXT, required: $required),
                'course' => new scalar_type(param::TEXT, required: $required),
                'courseid' => new scalar_type(param::INT, required: $required),
                'created' => new scalar_type(param::INT, required: $required),
                'posturl' => new scalar_type(param::LOCALURL, required: $required),
                'discussionurl' => new scalar_type(param::LOCALURL, required: $required),
                'modflowurl' => new scalar_type(param::LOCALURL, required: $required),
            ],
        );
    }
}
