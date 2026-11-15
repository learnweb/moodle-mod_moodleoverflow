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
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\router\schema\response\payload_response;
use mod_moodleoverflow\local\service\readtracking;
use mod_moodleoverflow\route\controller_trait;
use mod_moodleoverflow\route\schema\responses\value_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for read tracking: marking posts as read and switching the tracking of a moodleoverflow on or
 * off (PUT starts tracking, DELETE stops it).
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class readtracking_controller {
    use controller_trait;

    /**
     * Marks all posts of a moodleoverflow as read.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $moodleoverflowid
     * @return payload_response The number of unread posts left.
     */
    #[route(
        title: 'Mark a moodleoverflow as read',
        description: 'Marks all posts of a moodleoverflow as read for the current user.',
        path: '/moodleoverflow/{moodleoverflowid}/read',
        method: ['PUT'],
        pathtypes: [new path_parameter(name: 'moodleoverflowid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('unread', param::INT)],
    )]
    public function mark_moodleoverflow_read(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $moodleoverflowid
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $modflow = self::load_moodleoverflow($moodleoverflowid);

        readtracking::mark_moodleoverflow_read($modflow, $USER->id);
        $unread = readtracking::count_unread_posts_moodleoverflow($modflow, $USER->id);
        return new payload_response(['unread' => $unread], $request, $response);
    }

    /**
     * Marks all posts of a discussion as read.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $discussionid
     * @return payload_response The number of unread posts left.
     */
    #[route(
        title: 'Mark a discussion as read',
        description: 'Marks all posts of a discussion as read for the current user.',
        path: '/discussion/{discussionid}/read',
        method: ['PUT'],
        pathtypes: [new path_parameter(name: 'discussionid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('unread', param::INT)],
    )]
    public function mark_discussion_read(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $discussionid
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $discussion = self::load_discussion($discussionid);

        readtracking::mark_discussion_read($discussion, $USER->id);
        $unread = readtracking::count_unread_posts_discussion($discussion, $USER->id);
        return new payload_response(['unread' => $unread], $request, $response);
    }

    /**
     * Starts (PUT) or stops (DELETE) the read tracking of a moodleoverflow for the current user.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $moodleoverflowid
     * @return payload_response Whether the user tracks the moodleoverflow now.
     */
    #[route(
        title: 'Change the read tracking of a moodleoverflow',
        description: 'Starts (PUT) or stops (DELETE) the read tracking of a moodleoverflow for the current user.',
        path: '/moodleoverflow/{moodleoverflowid}/tracking',
        method: ['PUT', 'DELETE'],
        pathtypes: [new path_parameter(name: 'moodleoverflowid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('tracked', param::BOOL)],
    )]
    public function change_tracking(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $moodleoverflowid
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $modflow = self::load_moodleoverflow($moodleoverflowid);

        if ($request->getMethod() === 'PUT') {
            readtracking::start_tracking($modflow, $USER->id);
        } else {
            readtracking::stop_tracking($modflow, $USER->id);
        }
        return new payload_response(['tracked' => readtracking::is_tracked($modflow, $USER->id)], $request, $response);
    }
}
