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

import {useState} from "react";
import {field, isInactive, matchesName, Metric, metrics, Scope, scopes, Sort, SortKey, sortStats, UserStats} from "./userstats";
import {str} from "./lang";

/** The language strings of the scopes. */
const scopestrings: Record<Scope, string> = {
  forum: "userstatsthisforum",
  course: "userstatswholecourse",
};

/** How the column of a metric is labelled: votes get an icon, the label is then read out to screen readers. */
const metriclabels: Record<Metric, {key: string, icon?: string}> = {
  activity: {key: "userstatsactivity"},
  reputation: {key: "userstatsreputation"},
  upvotes: {key: "userstatsupvotes", icon: "fa-chevron-up"},
  downvotes: {key: "userstatsdownvotes", icon: "fa-chevron-down"},
};

/**
 * React component for userstats.php: the statistics of every participant of a course in one table, which can be
 * searched by name and sorted by every column.
 *
 * On large screens the numbers of this forum and of the whole course are shown side by side. On small screens only
 * one of them fits, so a toggle chooses which one.
 *
 * @module     mod_moodleoverflow/UserStatsPage
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default function UserStatsPage({users}: {users: UserStats[]}) {
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState<Sort>({key: "coursereputation", descending: true});
  const [scope, setScope] = useState<Scope>("forum");

  const rows = sortStats(users.filter(matchesName(query)), sort);

  // A new column starts with the highest numbers (names from A); a second click turns the order around.
  const sortBy = (key: SortKey) => setSort({key, descending: sort.key === key ? !sort.descending : key !== "name"});

  // The cells of the scope that is not chosen are only shown on large screens.
  const visibility = (cellscope: Scope) => (cellscope === scope ? "" : "d-none d-lg-table-cell");

  // A column header that sorts the table by its column.
  const header = (key: SortKey, label: string, className: string, icon?: string, rowSpan?: number) => {
    const active = sort.key === key;
    const direction = sort.descending ? "descending" : "ascending";
    const sorticon = sort.descending ? "fa-sort-down" : "fa-sort-up";
    return (
      <th key={key} scope="col" className={className} rowSpan={rowSpan} aria-sort={active ? direction : undefined}>
        <button type="button" className="btn btn-link p-0 text-reset text-decoration-none fw-semibold text-nowrap"
          title={label} onClick={() => sortBy(key)}>
          {icon && <i className={`fa-solid ${icon}`} aria-hidden="true"/>}
          <span className={icon ? "visually-hidden" : ""}>{label}</span>
          <i className={`fa-solid fa-fw ms-1 ${active ? sorticon : "fa-sort text-body-tertiary"}`} aria-hidden="true"/>
        </button>
      </th>
    );
  };

  return (
    <>
      <div className="d-flex align-items-baseline gap-2 mb-3">
        <h2 className="h4 mb-0">{str("userstats")}</h2>
        <span className="badge text-bg-secondary rounded-pill">{users.length}</span>
      </div>

      <div className="row g-2 mb-3">
        <div className="col-sm input-group">
          <span className="input-group-text"><i className="fa-solid fa-magnifying-glass" aria-hidden="true"/></span>
          <input type="search" className="form-control" placeholder={str("userstatssearch")}
            aria-label={str("userstatssearch")} value={query} onChange={(e) => setQuery(e.target.value)}/>
        </div>
        <div className="col-sm-auto d-lg-none">
          <div className="btn-group w-100" role="group" aria-label={str("userstatsscope")}>
            {scopes.map((s) => (
              <button key={s} type="button" aria-pressed={scope === s} onClick={() => setScope(s)}
                className={`btn ${scope === s ? "btn-primary" : "btn-outline-primary"}`}>
                {str(scopestrings[s])}
              </button>
            ))}
          </div>
        </div>
      </div>

      {rows.length === 0 ? (
        <p className="text-muted"><em>{str(users.length === 0 ? "nothingtodisplay" : "noresults", true)}</em></p>
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  {header("name", str("fullnameuser", true), "align-bottom", undefined, 2)}
                  {scopes.map((s) => (
                    <th key={s} scope="colgroup" colSpan={metrics.length}
                      className={`text-center border-start ${visibility(s)}`}>
                      {str(scopestrings[s])}
                    </th>
                  ))}
                </tr>
                <tr>
                  {scopes.flatMap((s) => metrics.map((m, i) => header(field(s, m), str(metriclabels[m].key),
                    `text-end ${i === 0 ? "border-start" : ""} ${visibility(s)}`, metriclabels[m].icon)))}
                </tr>
              </thead>
              <tbody>
                {rows.map((user) => {
                  const muted = isInactive(user) ? "text-body-secondary" : "";
                  return (
                    <tr key={user.userid}>
                      <th scope="row" className={`fw-normal ${muted}`}>
                        <a href={user.profileurl} className="text-decoration-none">{user.name}</a>
                      </th>
                      {scopes.flatMap((s) => metrics.map((m, i) => (
                        <td key={field(s, m)} className={`text-end ${i === 0 ? "border-start" : ""} ${visibility(s)} ${muted}`}>
                          {user[field(s, m)]}
                        </td>
                      )))}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <p className="small text-muted mt-3 mb-1">{str("helpamountofactivity_help")}</p>
      <p className="small text-muted">{str("userstatscoursereputationinfo")}</p>
    </>
  );
}
