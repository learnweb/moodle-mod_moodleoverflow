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
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\service\review;
use mod_moodleoverflow\route\controller_trait;
use mod_moodleoverflow\route\schema\responses\value_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for reviewing posts. Each request returns the next post the reviewer can review.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_controller {
    use controller_trait;

    /**
     * Approves a post.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @return payload_response The URL of the next post to review.
     */
    #[route(
        title: 'Approve a post',
        description: 'Approves a post that waits for its review.',
        path: '/post/{postid}/approve',
        method: ['POST'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new value_response('nexturl', param::URL, nullable: true)],
    )]
    public function approve(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $post = self::load_post($postid);

        review::approve($post, $USER->id);
        return new payload_response(self::get_next($post->get_moodleoverflow(), $USER->id), $request, $response);
    }

    /**
     * Rejects a post: deletes it and sends the reason to the author.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @return payload_response The URL of the next post to review.
     */
    #[route(
        title: 'Reject a post',
        description: 'Rejects a post that waits for its review: deletes it and sends the reason to the author.',
        path: '/post/{postid}/reject',
        method: ['POST'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(content: [
                    'reason' => new scalar_type(param::RAW),
                ]),
            ),
        ),
        responses: [new value_response('nexturl', param::URL, nullable: true)],
    )]
    public function reject(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $post = self::load_post($postid);
        $modflow = $post->get_moodleoverflow();
        $reason = trim($request->getParsedBody()['reason'] ?? '');

        review::reject($post, $USER->id, $reason === '' ? null : $reason);
        return new payload_response(self::get_next($modflow, $USER->id), $request, $response);
    }

    /**
     * The URL of the next post the user can review.
     *
     * @param moodleoverflow $modflow
     * @param int $userid
     * @return array The URL, null if there is none.
     */
    private static function get_next(moodleoverflow $modflow, int $userid): array {
        $next = review::get_next_post_to_review($modflow, $userid);
        return ['nexturl' => $next?->get_link()->out(false)];
    }
}
