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

namespace mod_moodleoverflow\route\schema\responses;

use core\router\schema\objects\array_of_things;
use core\router\schema\response\content\json_media_type;
use core\router\schema\response\response;
use mod_moodleoverflow\route\schema\userpost_schema;

/**
 * Response specification of the array of posts.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_posts_response extends response {
    /**
     * Constructor.
     * @throws \core\exception\coding_exception
     */
    public function __construct() {
        parent::__construct(
            statuscode: 200,
            description: 'OK',
            content: [
                new json_media_type(
                    schema: new array_of_things(
                        thingtype: new userpost_schema(true),
                    ),
                ),
            ],
        );
    }
}
