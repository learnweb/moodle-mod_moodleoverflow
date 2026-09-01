<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\route\api;

use coding_exception;
use core\context\system;
use core\context\user;
use core\exception\moodle_exception;
use core\param;
use core\router\require_login;
use core\router\route;
use core\router\schema\response\payload_response;
use core\router\schema\parameters\path_parameter;
use dml_exception;
use mod_moodleoverflow\local\service\user_service;
use mod_moodleoverflow\route\schema\responses\user_posts_response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * REST API controller for the moodleoverflow user posts site.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_controller {
    /**
     * Return all post of a specific user.
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param int $userid The user from which posts will be queried.
     * @return payload_response
     * @throws coding_exception|dml_exception|moodle_exception
     */
    #[route(
        title: 'Get posts for a user',
        description: "Returns a given user's moodleoverflow posts.",
        path: '/user/posts/{userid}',
        method: ['GET'],
        pathtypes: [new path_parameter(name: 'userid', type: param::INT)],
        responses: [new user_posts_response()],
        requirelogin: new require_login(),
    )]
    public function get_user_posts(ServerRequestInterface $request, ResponseInterface $response, int $userid): payload_response {
        global $PAGE, $USER, $CFG;
        $payload = [];

        if (isguestuser()) {
            $PAGE->set_context(system::instance());
            return new payload_response([], $request, $response);
        }
        if ($USER->id == $userid) {
            $PAGE->set_context(user::instance($userid));
            $payload = user_service::get_all_user_posts($userid);
        } else if ($user = \core\user::get_user($userid)) {
            $usercontext = user::instance($userid);
            $PAGE->set_context($usercontext);

            if ($CFG->branch >= 503) {
                $canviewprofile = \core\user::can_view_profile($user, null, $usercontext);
            } else {
                require_once($CFG->dirroot . '/user/lib.php');
                $canviewprofile = user_can_view_profile($user, null, $usercontext);
            }
            if ($canviewprofile) {
                $payload = user_service::get_all_user_posts($userid);
            }
        } else {
            $PAGE->set_context(system::instance());
        }

        return new payload_response($payload, $request, $response);
    }
}
