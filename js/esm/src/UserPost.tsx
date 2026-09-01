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
 * A single post of a user, as it is listed on the user.php page.
 *
 * @module     mod_moodleoverflow/UserPost
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {UserPost as UserPostType} from "./service";
import {str} from "./lang";

/**
 * Formats a unix timestamp into a readable date.
 */
const formatCreated = (created: number): string =>
  new Date(created * 1000).toLocaleDateString("de-DE",
    {timeZone: "Europe/Berlin", day: "2-digit", month: "2-digit", year: "numeric"});

export default function UserPost({post}: {post: UserPostType}) {
  return (
    <article className="card mb-3">
      <div className="card-body">
        <div className="d-flex justify-content-between align-items-baseline gap-3 mb-2">
          <h4 className="h6 mb-0">
            <a href={post.discussionurl} className="text-decoration-none">{post.discussionsubject}</a>
          </h4>
          <small className="text-muted text-nowrap">{formatCreated(post.created)}</small>
        </div>
        {/* The message is already formatted by format_text() on the server. */}
        <div dangerouslySetInnerHTML={{__html: post.message}}/>
        <a href={post.posturl} className="small">{str("showpost")}</a>
      </div>
    </article>
  );
}
