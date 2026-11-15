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
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\response\content\json_media_type;
use core\router\schema\response\response;

/**
 * Response with a single value, e.g. {"subscribed": true}. The route names the value.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class value_response extends response {
    /**
     * Constructor.
     *
     * @param string $name The name of the value
     * @param param $type The type of the value
     * @param bool $nullable Whether the value can be null
     * @throws \core\exception\coding_exception
     */
    public function __construct(string $name, param $type, bool $nullable = false) {
        parent::__construct(
            statuscode: 200,
            description: 'OK',
            content: [
                new json_media_type(
                    schema: new schema_object(content: [
                        $name => new scalar_type($type, required: !$nullable),
                    ]),
                ),
            ],
        );
    }
}
