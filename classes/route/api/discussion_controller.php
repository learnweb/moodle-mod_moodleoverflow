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

namespace mod_moodleoverflow\route\api;

use core\param;
use core\router\route;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\router\schema\request_body;
use core\router\schema\response\content\payload_response_type;
use core\router\schema\response\payload_response;
use mod_moodleoverflow\local\service\discussion;
use mod_moodleoverflow\route\controller_trait;
use mod_moodleoverflow\route\schema\responses\value_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for discussions.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_controller {
    use controller_trait;

    /**
     * Moves a discussion to another moodleoverflow of the course.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $discussionid
     * @return payload_response The URL of the discussion in its new moodleoverflow.
     */
    #[route(
        title: 'Move a discussion',
        description: 'Moves a discussion to another moodleoverflow of the course.',
        path: '/discussion/{discussionid}/move',
        method: ['POST'],
        pathtypes: [new path_parameter(name: 'discussionid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(content: [
                    'moodleoverflowid' => new scalar_type(param::INT, required: true),
                ]),
            ),
            required: true,
        ),
        responses: [new value_response('url', param::URL)],
    )]
    public function move(ServerRequestInterface $request, ResponseInterface $response, int $discussionid): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $discussion = self::load_discussion($discussionid);
        $destination = self::load_moodleoverflow((int) ($request->getParsedBody()['moodleoverflowid'] ?? 0));

        discussion::move($discussion, $destination, $USER->id);
        return new payload_response(['url' => $discussion->get_link()->out(false)], $request, $response);
    }
}
