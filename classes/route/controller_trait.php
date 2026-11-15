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

namespace mod_moodleoverflow\route;

use core\exception\access_denied_exception;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\models\post;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Checks and loaders shared by the REST controllers. The trait lives outside route\api, because the router takes every
 * class of that namespace for a controller.
 *
 * The login is checked inside the controller method: Moodle 4.5 does not know the requirelogin attribute of a route.
 * The loaders set the context of the page; the services check the permissions. A missing record throws a
 * dml_missing_record_exception, which the router answers with 404.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait controller_trait {
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

    /**
     * Loads a moodleoverflow and sets the context of the page.
     *
     * @param int $moodleoverflowid
     * @return moodleoverflow
     * @throws \dml_missing_record_exception
     */
    private static function load_moodleoverflow(int $moodleoverflowid): moodleoverflow {
        global $PAGE;
        $modflow = moodleoverflow::from_id($moodleoverflowid);
        $PAGE->set_context($modflow->get_context());
        return $modflow;
    }

    /**
     * Loads a discussion and sets the context of the page.
     *
     * @param int $discussionid
     * @return discussion
     * @throws \dml_missing_record_exception
     */
    private static function load_discussion(int $discussionid): discussion {
        global $PAGE;
        $discussion = discussion::from_id($discussionid);
        $PAGE->set_context($discussion->get_moodleoverflow()->get_context());
        return $discussion;
    }

    /**
     * Loads a post and sets the context of the page.
     *
     * @param int $postid
     * @return post
     * @throws \dml_missing_record_exception
     */
    private static function load_post(int $postid): post {
        global $PAGE;
        $post = post::from_id($postid);
        $PAGE->set_context($post->get_moodleoverflow()->get_context());
        return $post;
    }
}
