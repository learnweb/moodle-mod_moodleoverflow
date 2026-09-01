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
 * Every control of the user posts page: the search box, the sort button and the filter panel.
 *
 * This component only renders controls and reports the new view upwards. Which posts a view lets
 * through is decided in filters.ts.
 *
 * @module     mod_moodleoverflow/PostFilter
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useState} from "react";
import {UserPost as UserPostType} from "./service";
import {FilterState} from "./filters";
import {str} from "./lang";

/** The time spans the time filter offers. The keys are also the language string identifiers. */
const timespans: {key: string, seconds: number | null}[] = [
  {key: "alltime", seconds: null},
  {key: "lastweek", seconds: 604800},
  {key: "lastmonth", seconds: 2592000},
  {key: "lastyear", seconds: 31536000},
];

/** The courses the given posts were written in, sorted by name. */
const courseoptions = (posts: UserPostType[]) =>
  [...new Map(posts.map(({courseid, course}): [number, string] => [courseid, course]))]
    .sort(([, a], [, b]) => a.localeCompare(b));

export default function PostFilter({posts, filters, onChange, newestfirst, onSortChange}: {
  posts: UserPostType[],
  filters: FilterState,
  onChange: (filters: FilterState) => void,
  newestfirst: boolean,
  onSortChange: (newestfirst: boolean) => void,
}) {
  const [open, setOpen] = useState(false);
  const {query, courses: selected, timespan} = filters;

  // The courses to offer a checkbox for, and how many filters are narrowing the posts down.
  const courses = courseoptions(posts);
  const narrowed = selected !== null && selected.length < courses.length;
  const active = (narrowed ? 1 : 0) + (timespan === null ? 0 : 1);

  // Unchecking the first box turns "every course" into a list of all courses but that one.
  const togglecourse = (courseid: number) => {
    const checked = selected ?? courses.map(([id]) => id);
    onChange({
      ...filters,
      courses: checked.includes(courseid)
        ? checked.filter((id) => id !== courseid)
        : [...checked, courseid],
    });
  };

  return (
    <>
      <div className="row g-2 mb-3">
        <div className="col-sm input-group">
          <span className="input-group-text"><i className="fa-solid fa-magnifying-glass"/></span>
          <input type="search" className="form-control" placeholder={str("searchuserposts")}
            value={query} onChange={(e) => onChange({...filters, query: e.target.value})}/>
        </div>
        <div className="col-sm-auto">
          <button type="button" className="btn btn-outline-secondary w-100"
            aria-expanded={open} aria-controls="moodleoverflow-postfilter" onClick={() => setOpen(!open)}>
            <i className="fa-solid fa-filter me-2"/>
            {str("filterposts")}
            {active > 0 && <span className="badge text-bg-primary ms-2">{active}</span>}
          </button>
        </div>
        <div className="col-sm-auto">
          <button type="button" className="btn btn-outline-secondary w-100"
            onClick={() => onSortChange(!newestfirst)}>
            <i className={`fa-solid me-2 ${newestfirst ? "fa-arrow-down-wide-short" : "fa-arrow-up-short-wide"}`}/>
            {str(newestfirst ? "firstnewest" : "firstoldest")}
          </button>
        </div>
      </div>

      {open && (
        <div id="moodleoverflow-postfilter" className="card mb-4">
          <div className="card-body">
            <fieldset className="mb-3">
              <legend className="h6">{str("coursefilter")}</legend>
              <div className="d-flex flex-wrap column-gap-4 row-gap-1">
                {courses.map(([courseid, course]) => (
                  <div className="form-check" key={courseid}>
                    <input className="form-check-input" type="checkbox" id={`moodleoverflow-course-${courseid}`}
                      checked={selected === null || selected.includes(courseid)}
                      onChange={() => togglecourse(courseid)}/>
                    <label className="form-check-label" htmlFor={`moodleoverflow-course-${courseid}`}>
                      {course}
                    </label>
                  </div>
                ))}
              </div>
            </fieldset>

            <fieldset>
              <legend className="h6">{str("timefilter")}</legend>
              <div className="d-flex flex-wrap column-gap-4 row-gap-1">
                {timespans.map(({key, seconds}) => (
                  <div className="form-check" key={key}>
                    <input className="form-check-input" type="radio" name="moodleoverflow-timespan"
                      id={`moodleoverflow-timespan-${key}`} checked={timespan === seconds}
                      onChange={() => onChange({...filters, timespan: seconds})}/>
                    <label className="form-check-label" htmlFor={`moodleoverflow-timespan-${key}`}>
                      {str(key)}
                    </label>
                  </div>
                ))}
              </div>
            </fieldset>

            {active > 0 && (
              <button type="button" className="btn btn-link btn-sm px-0 mt-3"
                onClick={() => onChange({query, courses: null, timespan: null})}>
                {str("resetfilters")}
              </button>
            )}
          </div>
        </div>
      )}
    </>
  );
}
