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
 * The user statistics: their data type, and how they are searched and sorted.
 *
 * @module     mod_moodleoverflow/userstats
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Where the numbers are counted: in the moodleoverflow the page was opened from, or in the whole course. */
export type Scope = "forum" | "course";

/** What is counted for each scope. */
export type Metric = "activity" | "reputation" | "upvotes" | "downvotes";

/** The statistics of one user, as userstats.php passes them (userstats_dto). */
export type UserStats = {
    userid: number;
    name: string;
    profileurl: string;
} & Record<`${Scope}${Metric}`, number>;

/** The columns the table can be sorted by. */
export type SortKey = "name" | `${Scope}${Metric}`;

/** How the table is sorted. */
export type Sort = {
    key: SortKey;
    descending: boolean;
};

/** The scopes and metrics in the order of the table columns. */
export const scopes: Scope[] = ["forum", "course"];
export const metrics: Metric[] = ["activity", "reputation", "upvotes", "downvotes"];

/** The field of a user's statistics that holds a metric of a scope. */
export const field = (scope: Scope, metric: Metric): `${Scope}${Metric}` => `${scope}${metric}`;

/** Whether the name of a user contains what was typed into the search box. */
export const matchesName = (query: string) => (user: UserStats): boolean =>
    user.name.toLowerCase().includes(query.trim().toLowerCase());

/** Whether a user has not done anything in the course yet. */
export const isInactive = (user: UserStats): boolean => user.courseactivity === 0;

/**
 * Sorts the statistics by a column. Users with the same value are sorted by name.
 *
 * @param users The statistics to sort; the array is not changed.
 * @param sort The column and the direction.
 * @returns A new, sorted array.
 */
export const sortStats = (users: UserStats[], {key, descending}: Sort): UserStats[] =>
    [...users].sort((a, b) => {
        const order = key === "name" ? a.name.localeCompare(b.name) : a[key] - b[key];
        return (descending ? -order : order) || a.name.localeCompare(b.name);
    });
