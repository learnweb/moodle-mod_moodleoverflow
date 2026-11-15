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

namespace mod_moodleoverflow\route\schema\responses;

use core\param;
use core\router\schema\objects\array_of_things;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\response\content\json_media_type;
use core\router\schema\response\response;

/**
 * Response of a vote or a mark: the new state of the post. The author and their reputation are null if the user may
 * not see them; the vote of the user is "up", "down" or null; the marked posts are empty for votes.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_response extends response {
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
                    schema: new schema_object(content: [
                        'postrating' => new scalar_type(param::INT, required: true),
                        'ownerreputation' => new scalar_type(param::INT),
                        'raterreputation' => new scalar_type(param::INT, required: true),
                        'ownerid' => new scalar_type(param::INT),
                        'uservote' => new scalar_type(param::ALPHA),
                        'canchange' => new scalar_type(param::BOOL, required: true),
                        'markedposts' => new array_of_things(new scalar_type(param::INT)),
                    ]),
                ),
            ],
        );
    }
}
