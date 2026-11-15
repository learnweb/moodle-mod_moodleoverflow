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
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\route\controller_trait;
use mod_moodleoverflow\route\schema\responses\value_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for subscriptions. The HTTP method is the target state: PUT subscribes, DELETE unsubscribes, so
 * repeating a request changes nothing.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_controller {
    use controller_trait;

    /**
     * Subscribes the current user to a moodleoverflow (PUT) or unsubscribes them (DELETE).
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $moodleoverflowid
     * @return payload_response Whether the user is subscribed now.
     */
    #[route(
        title: 'Change the subscription to a moodleoverflow',
        description: 'Subscribes the current user to a moodleoverflow (PUT) or unsubscribes them (DELETE).',
        path: '/moodleoverflow/{moodleoverflowid}/subscription',
        method: ['PUT', 'DELETE'],
        pathtypes: [new path_parameter(name: 'moodleoverflowid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('subscribed', param::BOOL)],
    )]
    public function change_moodleoverflow_subscription(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $moodleoverflowid
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $modflow = self::load_moodleoverflow($moodleoverflowid);

        if ($request->getMethod() === 'PUT') {
            subscription::subscribe($modflow, $USER->id);
        } else {
            subscription::unsubscribe($modflow, $USER->id);
        }
        return new payload_response(['subscribed' => subscription::is_subscribed($modflow, $USER->id)], $request, $response);
    }

    /**
     * Subscribes the current user to a single discussion (PUT) or unsubscribes them (DELETE).
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $discussionid
     * @return payload_response Whether the user is subscribed to the discussion now.
     */
    #[route(
        title: 'Change the subscription to a discussion',
        description: 'Subscribes the current user to a single discussion (PUT) or unsubscribes them (DELETE).',
        path: '/discussion/{discussionid}/subscription',
        method: ['PUT', 'DELETE'],
        pathtypes: [new path_parameter(name: 'discussionid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('subscribed', param::BOOL)],
    )]
    public function change_discussion_subscription(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $discussionid
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $discussion = self::load_discussion($discussionid);

        if ($request->getMethod() === 'PUT') {
            subscription::subscribe_discussion($discussion, $USER->id);
        } else {
            subscription::unsubscribe_discussion($discussion, $USER->id);
        }
        $subscribed = subscription::is_subscribed_to_discussion($discussion, $USER->id);
        return new payload_response(['subscribed' => $subscribed], $request, $response);
    }
}
