// This file is part of Moodle - http://moodle.org/
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

/**
 * Service class that communicates with moodleoverflows REST API
 *
 * @module     mod_moodleoverflow/service
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fetch from "@moodle/lms/core/fetch";

export type UserPost = {
    id: number;
    message: string;
    discussionsubject: string;
    modflow: string;
    course: string;
    courseid: number;
    created: number;
    posturl: string;
    discussionurl: string;
    modflowurl: string;
}

export type User = {
    id: number;
    name: string;
}

export const fetchUserPosts = async(userid: number): Promise<UserPost[]> => {
  const response = await Fetch.performGet("mod_moodleoverflow", `user/posts/${userid}`);
  return await response.json() as Promise<UserPost[]>;
};
