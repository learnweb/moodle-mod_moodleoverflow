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

use core\exception\moodle_exception;
use core\param;
use core\router\route;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\router\schema\request_body;
use core\router\schema\response\content\payload_response_type;
use core\router\schema\response\payload_response;
use mod_moodleoverflow\local\dto\rating_dto;
use mod_moodleoverflow\local\enum\rating_type;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service\rating;
use mod_moodleoverflow\route\controller_trait;
use mod_moodleoverflow\route\schema\responses\dto_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for votes and marks. Each request returns the new state of the post, so the page can show it.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_controller {
    use controller_trait;

    /** @var rating_type[] The votes by their name in the API. */
    private const VOTES = ['up' => rating_type::UPVOTE, 'down' => rating_type::DOWNVOTE];

    /** @var rating_type[] The marks by their name in the API. */
    private const MARKS = ['solved' => rating_type::SOLVED, 'helpful' => rating_type::HELPFUL];

    /**
     * Votes for a post or changes the vote of the current user.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @return payload_response The new state of the post.
     */
    #[route(
        title: 'Vote for a post',
        description: 'Votes a post up or down, or changes the vote of the current user.',
        path: '/post/{postid}/vote',
        method: ['PUT'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(content: [
                    'vote' => new scalar_type(param::ALPHA, required: true),
                ]),
            ),
            required: true,
        ),
        responses: [new dto_response(rating_dto::class)],
    )]
    public function vote(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $post = self::load_post($postid);
        $vote = self::VOTES[$request->getParsedBody()['vote'] ?? '']
            ?? throw new moodle_exception('invalidratingid', 'moodleoverflow');

        rating::vote($post, $USER->id, $vote);
        return new payload_response(self::get_state($post, $USER->id)->to_array(), $request, $response);
    }

    /**
     * Removes the vote of the current user from a post.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @return payload_response The new state of the post.
     */
    #[route(
        title: 'Remove a vote',
        description: 'Removes the vote of the current user from a post.',
        path: '/post/{postid}/vote',
        method: ['DELETE'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new dto_response(rating_dto::class)],
    )]
    public function remove_vote(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $post = self::load_post($postid);

        rating::remove_vote($post, $USER->id);
        return new payload_response(self::get_state($post, $USER->id)->to_array(), $request, $response);
    }

    /**
     * Marks a post as solved or helpful (PUT) or removes the mark (DELETE).
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @param string $type solved or helpful
     * @return payload_response The new state of the post with all posts of the discussion that have the mark.
     */
    #[route(
        title: 'Change a mark',
        description: 'Marks a post as solved or helpful (PUT) or removes the mark (DELETE).',
        path: '/post/{postid}/mark/{type}',
        method: ['PUT', 'DELETE'],
        pathtypes: [
            new path_parameter(name: 'postid', type: param::INT),
            new path_parameter(name: 'type', type: param::ALPHA),
        ],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new dto_response(rating_dto::class)],
    )]
    public function change_mark(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $postid,
        string $type
    ): payload_response {
        global $USER;
        self::require_login_and_sesskey($request);
        $post = self::load_post($postid);
        $mark = self::MARKS[$type] ?? throw new moodle_exception('invalidratingid', 'moodleoverflow');

        if ($request->getMethod() === 'PUT') {
            rating::mark($post, $USER->id, $mark);
        } else {
            rating::unmark($post, $USER->id, $mark);
        }
        return new payload_response(self::get_state($post, $USER->id, $mark)->to_array(), $request, $response);
    }

    /**
     * The state of a post after a rating: votes, reputations and the vote of the user. The author and their reputation
     * are only returned if the user may see them.
     *
     * @param post $post
     * @param int $userid
     * @param rating_type|null $mark The mark that was changed: its posts are returned, so the page can show where it is.
     * @return rating_dto
     */
    private static function get_state(post $post, int $userid, ?rating_type $mark = null): rating_dto {
        $modflow = $post->get_moodleoverflow();
        $ratings = rating::get_post_ratings($post->get_discussion())[$post->get_id()];
        $vote = rating::get_user_vote($post, $userid);
        return new rating_dto(
            postrating: $ratings->upvotes - $ratings->downvotes,
            ownerreputation: permissions::can_view_reputation($post, $userid)
                ? rating::get_reputation($modflow, $post->get_userid()) : null,
            raterreputation: rating::get_reputation($modflow, $userid),
            ownerid: permissions::can_view_author($post, $userid) ? $post->get_userid() : null,
            uservote: $vote ? array_search($vote, self::VOTES, true) : null,
            canchange: rating::can_change_vote($post, $userid, $vote),
            markedposts: $mark ? rating::get_marked_post_ids($post->get_discussion(), $mark) : [],
        );
    }
}
