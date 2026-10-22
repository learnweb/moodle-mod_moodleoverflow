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
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service\posting;
use mod_moodleoverflow\route\schema\responses\delete_post_response;
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
        global $DB, $PAGE, $USER;
        require_login();
        if (!confirm_sesskey($request->getQueryParams()['sesskey'] ?? '')) {
            throw new access_denied_exception('invalidsesskey', 'error');
        }
        $record = $DB->get_record('moodleoverflow_posts', ['id' => $postid]);
        if (!$record) {
            throw new not_found_exception('post', (string) $postid);
        }
        $post = post::from_record($record);
        $modflow = $post->get_moodleoverflow();
        $discussionurl = $post->get_discussion()->get_link();
        $PAGE->set_context($modflow->get_context());

        $discussiondeleted = posting::delete($post, $USER->id);
        $url = $discussiondeleted ? $modflow->get_link() : $discussionurl;
        return new payload_response(['redirecturl' => $url->out(false)], $request, $response);
    }
}
