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

import {useEffect, useState} from "react";
import {fetchUserPosts, UserPost as UserPostType} from "./service";
import UserPost from "./UserPost";
import PostFilter from "./PostFilter";
import {approvedBy, FilterState} from "./filters";
import {str} from "./lang";

/**
 * React component for the moodleoverflow user page that shows all the users posts,
 * grouped by the moodleoverflow they were written in.
 *
 * @module     mod_moodleoverflow/UserPage
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default function UserPage({userid, courseid = 0}: {userid: number, courseid?: number}) {
  const [posts, setPosts] = useState<UserPostType[] | null>(null);
  // When user.php was called from within a course, only that course's posts are shown to begin with.
  const [filters, setFilters] = useState<FilterState>({
    query: "",
    courses: courseid ? [courseid] : null,
    timespan: null,
  });
  const [newestfirst, setNewestfirst] = useState(true);
  const [collapsed, setCollapsed] = useState<string[]>([]);

  const toggle = (modflowurl: string) => setCollapsed(collapsed.includes(modflowurl)
    ? collapsed.filter((url) => url !== modflowurl)
    : [...collapsed, modflowurl]);

  useEffect(() => {
    fetchUserPosts(userid).then(setPosts);
  }, [userid]);

  if (posts === null) {
    return (
      <div className="text-center py-5">
        <div className="spinner-border" role="status">
          <span className="visually-hidden">{str("loading", true)}</span>
        </div>
      </div>
    );
  }

  // The posts that every filter approves, in the order the user asked for.
  const matches = posts
    .filter(approvedBy(filters, Date.now() / 1000))
    .sort((a, b) => (newestfirst ? b.created - a.created : a.created - b.created));

  // The moodleoverflows that are left, one group each. The name is not unique across courses, its url is.
  const modflowurls = [...new Set(matches.map((post) => post.modflowurl))];

  return (
    <>
      <div className="d-flex align-items-baseline gap-2 mb-3">
        <h2 className="h4 mb-0">{str("posts", true)}</h2>
        <span className="text-muted">{matches.length}</span>
      </div>

      <PostFilter posts={posts} filters={filters} onChange={setFilters}
        newestfirst={newestfirst} onSortChange={setNewestfirst}/>

      {matches.length === 0 && (
        <p className="text-muted"><em>{str(posts.length === 0 ? "nothingtodisplay" : "noresults", true)}</em></p>
      )}

      {modflowurls.map((modflowurl) => {
        const grouped = matches.filter((post) => post.modflowurl === modflowurl);
        const {modflow, course} = grouped[0];
        const isopen = !collapsed.includes(modflowurl);

        return (
          <section key={modflowurl} className="mb-4">
            <div className="d-flex align-items-baseline gap-2 border-bottom pb-2 mb-3">
              <button type="button" className="btn btn-link btn-sm text-body py-0 px-2"
                aria-expanded={isopen} aria-label={modflow} onClick={() => toggle(modflowurl)}>
                <i className={`fa-solid fa-fw ${isopen ? "fa-chevron-down" : "fa-chevron-right"}`}/>
              </button>
              <h3 className="h6 mb-0">
                <a href={modflowurl} className="text-decoration-none">{modflow}</a>
                <span className="text-muted fw-normal ms-2">{course}</span>
              </h3>
              <span className="badge text-bg-secondary ms-auto">{grouped.length}</span>
            </div>
            {isopen && (
              <div className="ps-3">
                {grouped.map((post) => <UserPost key={post.id} post={post}/>)}
              </div>
            )}
          </section>
        );
      })}
    </>
  );
}
