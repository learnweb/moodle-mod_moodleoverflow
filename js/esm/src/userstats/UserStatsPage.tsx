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
import {field, isInactive, matchesName, Metric, metrics, Scope, scopes, Sort, SortKey, sortStats, Timeline, UserStats}
  from "./stats";
import ActivityChart from "./ActivityChart";
import {str} from "../lang";

/** How the column of a metric is labelled: votes get an icon, the label is then read out to screen readers. */
const metriclabels: Record<Metric, {key: string, icon?: string}> = {
  activity: {key: "userstatsactivity"},
  reputation: {key: "userstatsreputation"},
  upvotes: {key: "userstatsupvotes", icon: "fa-chevron-up"},
  downvotes: {key: "userstatsdownvotes", icon: "fa-chevron-down"},
};

/** The language strings of the scopes. */
const scopelabels: Record<Scope, string> = {
  forum: "userstatsthisforum",
  course: "userstatswholecourse",
};

/**
 * A column header that sorts the table by its column.
 *
 * @param props The props
 * @param props.column The column of the header
 * @param props.label The name of the column
 * @param props.sort How the table is sorted at the moment
 * @param props.onSort Sorts the table by a column
 * @param props.className Classes of the header cell
 * @param props.icon An icon shown instead of the label, which is then only read out to screen readers
 * @param props.rowSpan How many header rows the cell spans
 * @returns The header cell
 */
function SortHeader({column, label, sort, onSort, className, icon, rowSpan}: {
  column: SortKey,
  label: string,
  sort: Sort,
  onSort: (column: SortKey) => void,
  className?: string,
  icon?: string,
  rowSpan?: number,
}) {
  const active = sort.key === column;
  const direction = sort.descending ? "descending" : "ascending";
  const sorticon = sort.descending ? "fa-sort-down" : "fa-sort-up";
  return (
    <th scope="col" className={className} rowSpan={rowSpan} aria-sort={active ? direction : undefined}>
      <button type="button" title={label} onClick={() => onSort(column)}
        className={"btn btn-link d-inline-flex align-items-center gap-1 p-0 "
          + "text-reset text-decoration-none fw-semibold text-nowrap"}>
        {icon && <i className={`fa-solid ${icon}`} aria-hidden="true"/>}
        <span className={icon ? "visually-hidden" : ""}>{label}</span>
        <i className={`fa-solid fa-fw ${active ? sorticon : "fa-sort text-body-tertiary"}`} aria-hidden="true"/>
      </button>
    </th>
  );
}

/**
 * React component for userstats.php: the posts and votes per day or week as a chart, and the statistics of every
 * participant of a course in one table, which can be searched by name and sorted by every column.
 *
 * A toggle chooses whether the chart shows this forum or the whole course. On large screens the table shows the
 * numbers of both side by side; on small screens only one of them fits, so the toggle chooses it for the table too.
 *
 * @module     mod_moodleoverflow/userstats/UserStatsPage
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default function UserStatsPage({users, modflowid, timeline, firstweekday}: {
  users: UserStats[],
  modflowid: number,
  timeline: Timeline,
  firstweekday: number,
}) {
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState<Sort>({key: "coursereputation", descending: true});
  const [scope, setScope] = useState<Scope>("forum");

  const rows = sortStats(users.filter(matchesName(query)), sort);

  // A new column starts with the highest numbers (names from A); a second click turns the order around.
  const sortBy = (key: SortKey) => setSort({key, descending: sort.key === key ? !sort.descending : key !== "name"});

  // The cells of the scope that is not chosen are only shown on large screens.
  const visibility = (cellscope: Scope) => (cellscope === scope ? "" : "d-none d-lg-table-cell");

  return (
    <>
      <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div className="d-flex align-items-baseline gap-2">
          <h2 className="h4 mb-0">{str("userstats")}</h2>
          <span className="badge text-bg-secondary rounded-pill">{users.length}</span>
        </div>
        <div className="btn-group" role="group" aria-label={str("userstatsscope")}>
          {scopes.map((s) => (
            <button key={s} type="button" aria-pressed={scope === s} onClick={() => setScope(s)}
              className={`btn ${scope === s ? "btn-primary" : "btn-outline-primary"}`}>
              {str(scopelabels[s])}
            </button>
          ))}
        </div>
      </div>

      <ActivityChart timeline={timeline} modflowid={modflowid} firstweekday={firstweekday} scope={scope}/>

      <div className="input-group mb-3">
        <span className="input-group-text"><i className="fa-solid fa-magnifying-glass" aria-hidden="true"/></span>
        <input type="search" className="form-control" placeholder={str("userstatssearch")}
          aria-label={str("userstatssearch")} value={query} onChange={(e) => setQuery(e.target.value)}/>
      </div>

      {rows.length === 0 ? (
        <p className="text-muted"><em>{str(users.length === 0 ? "nothingtodisplay" : "noresults", true)}</em></p>
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <SortHeader column="name" label={str("fullnameuser", true)} sort={sort} onSort={sortBy}
                    className="align-bottom" rowSpan={2}/>
                  {scopes.map((s) => (
                    <th key={s} scope="colgroup" colSpan={metrics.length}
                      className={`text-center border-start ${visibility(s)}`}>
                      {str(scopelabels[s])}
                    </th>
                  ))}
                </tr>
                <tr>
                  {scopes.flatMap((s) => metrics.map((m, i) => (
                    <SortHeader key={field(s, m)} column={field(s, m)} label={str(metriclabels[m].key)}
                      icon={metriclabels[m].icon} sort={sort} onSort={sortBy}
                      className={`text-end ${i === 0 ? "border-start" : ""} ${visibility(s)}`}/>
                  )))}
                </tr>
              </thead>
              <tbody>
                {rows.map((user) => {
                  const muted = isInactive(user) ? "text-body-secondary" : "";
                  const postslabel = str("userstatsviewposts", false, user.name);
                  return (
                    <tr key={user.userid}>
                      <th scope="row" className={`fw-normal ${muted}`}>
                        <a href={user.profileurl} className="text-decoration-none">{user.name}</a>
                        <a href={user.postsurl} className="ms-2 text-body-tertiary" title={postslabel}>
                          <i className="fa-regular fa-comments" aria-hidden="true"/>
                          <span className="visually-hidden">{postslabel}</span>
                        </a>
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
