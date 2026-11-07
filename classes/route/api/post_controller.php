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
use core\exception\moodle_exception;
use core\exception\not_found_exception;
use core\notification;
use core\param;
use core\router\route;
use core\router\schema\objects\scalar_type;
use core\router\schema\objects\schema_object;
use core\router\schema\parameters\path_parameter;
use core\router\schema\parameters\query_parameter;
use core\router\schema\request_body;
use core\router\schema\response\content\payload_response_type;
use core\router\schema\response\payload_response;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\models\post_content;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\route\schema\responses\delete_post_response;
use mod_moodleoverflow\route\schema\responses\reply_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for writing posts.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_controller {
    /**
     * Deletes a post with its replies; deleting the question deletes the discussion.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid
     * @return payload_response The URL to continue at: the discussion, or the instance if the discussion is gone.
     */
    #[route(
        title: 'Delete a post',
        description: 'Deletes a post with its replies; deleting the question deletes the discussion.',
        path: '/post/{postid}',
        method: ['DELETE'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        responses: [new delete_post_response()],
    )]
    public function delete_post(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        $post = $this->load_post($request, $postid);
        $modflow = $post->get_moodleoverflow();
        $discussionurl = $post->get_discussion()->get_link();

        $discussiondeleted = posting::delete($post, $USER->id);
        $url = $discussiondeleted ? $modflow->get_link() : $discussionurl;
        return new payload_response(['redirecturl' => $url->out(false)], $request, $response);
    }

    /**
     * Replies to a post with a plain text message (the small reply form of the discussion page). The success message
     * is shown on the next page.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $postid The post to reply to
     * @return payload_response The URL of the new post.
     */
    #[route(
        title: 'Reply to a post',
        description: 'Adds a reply with a plain text message to a post: an answer to the question or a comment to an answer.',
        path: '/post/{postid}/reply',
        method: ['POST'],
        pathtypes: [new path_parameter(name: 'postid', type: param::INT)],
        queryparams: [new query_parameter(name: 'sesskey', type: param::ALPHANUM, required: true)],
        requestbody: new request_body(
            content: new payload_response_type(
                schema: new schema_object(content: [
                    'message' => new scalar_type(param::RAW, required: true),
                ]),
            ),
            required: true,
        ),
        responses: [new reply_response()],
    )]
    public function reply(ServerRequestInterface $request, ResponseInterface $response, int $postid): payload_response {
        global $USER;
        $parent = $this->load_post($request, $postid);
        $message = trim($request->getParsedBody()['message'] ?? '');
        if ($message === '') {
            throw new moodle_exception('erroremptymessage', 'moodleoverflow');
        }

        $content = new post_content(post_content::plain_text_to_html($message), FORMAT_HTML);
        $newpost = posting::reply($parent, $USER->id, $content);
        $edittime = format_time(get_config('moodleoverflow', 'maxeditingtime'));
        notification::success(get_string('postaddedsuccess', 'moodleoverflow') . ' ' .
            get_string('postaddedtimeleft', 'moodleoverflow', $edittime));
        return new payload_response(['url' => $newpost->get_link()->out(false)], $request, $response);
    }

    /**
     * Checks the login and the sesskey and loads the post of a request. The service checks the permissions.
     *
     * @param ServerRequestInterface $request
     * @param int $postid
     * @return post
     * @throws moodle_exception
     */
    private function load_post(ServerRequestInterface $request, int $postid): post {
        global $DB, $PAGE;
        require_login();
        if (!confirm_sesskey($request->getQueryParams()['sesskey'] ?? '')) {
            throw new access_denied_exception('invalidsesskey', 'error');
        }
        $record = $DB->get_record('moodleoverflow_posts', ['id' => $postid]);
        if (!$record) {
            throw new not_found_exception('post', (string) $postid);
        }
        $post = post::from_record($record);
        $PAGE->set_context($post->get_moodleoverflow()->get_context());
        return $post;
    }
}
