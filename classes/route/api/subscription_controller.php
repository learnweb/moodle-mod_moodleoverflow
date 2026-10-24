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

use core\exception\access_denied_exception;
use core\exception\not_found_exception;
use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\router\schema\response\payload_response;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service\subscription;
use mod_moodleoverflow\route\schema\responses\subscription_response;
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
        responses: [new subscription_response()],
    )]
    public function change_moodleoverflow_subscription(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $moodleoverflowid
    ): payload_response {
        global $DB, $PAGE, $USER;
        self::require_login_and_sesskey($request);

        $record = $DB->get_record('moodleoverflow', ['id' => $moodleoverflowid]);
        if (!$record) {
            throw new not_found_exception('moodleoverflow', (string) $moodleoverflowid);
        }

        $modflow = moodleoverflow::from_record($record);
        $PAGE->set_context($modflow->get_context());

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
        responses: [new subscription_response()],
    )]
    public function change_discussion_subscription(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $discussionid
    ): payload_response {
        global $DB, $PAGE, $USER;
        self::require_login_and_sesskey($request);

        $record = $DB->get_record('moodleoverflow_discussions', ['id' => $discussionid]);
        if (!$record) {
            throw new not_found_exception('discussion', (string) $discussionid);
        }

        $discussion = discussion::from_record($record);
        $PAGE->set_context($discussion->get_moodleoverflow()->get_context());

        if ($request->getMethod() === 'PUT') {
            subscription::subscribe_discussion($discussion, $USER->id);
        } else {
            subscription::unsubscribe_discussion($discussion, $USER->id);
        }
        $subscribed = subscription::is_subscribed_to_discussion($discussion, $USER->id);
        return new payload_response(['subscribed' => $subscribed], $request, $response);
    }

    /**
     * Requires a logged in user and a valid sesskey in the query.
     *
     * @param ServerRequestInterface $request
     * @return void
     * @throws access_denied_exception
     */
    private static function require_login_and_sesskey(ServerRequestInterface $request): void {
        require_login();
        if (!confirm_sesskey($request->getQueryParams()['sesskey'] ?? '')) {
            throw new access_denied_exception('invalidsesskey', 'error');
        }
    }
}
