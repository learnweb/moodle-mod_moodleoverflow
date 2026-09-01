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
 * Filter for the User posts.
 *
 * @module     mod_moodleoverflow/filters
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {UserPost} from "./service";

/** The state of every filter on the user posts page. */
export type FilterState = {
    /** What the user typed into the search box. */
    query: string;
    /** The courses whose posts are shown. Null means that every course is shown. */
    courses: number[] | null;
    /** How far back a post may have been written, in seconds. Null means that there is no limit. */
    timespan: number | null;
};

/** Whether a post mentions the search term anywhere. */
const matchesquery = (post: UserPost, query: string): boolean => {
    const needle = query.trim().toLowerCase();
    return [post.discussionsubject, post.message, post.modflow, post.course]
        .some((field) => field.toLowerCase().includes(needle));
};

/**
 * A post is only shown if every filter approves it, so one line per filter.
 *
 * @param filters The current state of all filters.
 * @param now The current time as a unix timestamp, for the time filter to measure against.
 * @returns A predicate that can be handed to Array.filter().
 */
export const approvedBy = ({query, courses, timespan}: FilterState, now: number) =>
    (post: UserPost): boolean =>
        matchesquery(post, query)
        && (courses === null || courses.includes(post.courseid))
        && (timespan === null || post.created >= now - timespan);
