var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
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
import { useState } from "react";
import { str } from "./lang";
const timespans = [
  { key: "alltime", seconds: null },
  { key: "lastweek", seconds: 604800 },
  { key: "lastmonth", seconds: 2592e3 },
  { key: "lastyear", seconds: 31536e3 }
];
const courseoptions = /* @__PURE__ */ __name((posts) => [...new Map(posts.map(({ courseid, course }) => [courseid, course]))].sort(([, a], [, b]) => a.localeCompare(b)), "courseoptions");
function PostFilter({ posts, filters, onChange, newestfirst, onSortChange }) {
  const [open, setOpen] = useState(false);
  const { query, courses: selected, timespan } = filters;
  const courses = courseoptions(posts);
  const narrowed = selected !== null && selected.length < courses.length;
  const active = (narrowed ? 1 : 0) + (timespan === null ? 0 : 1);
  const togglecourse = /* @__PURE__ */ __name((courseid) => {
    const checked = selected ?? courses.map(([id]) => id);
    onChange({
      ...filters,
      courses: checked.includes(courseid) ? checked.filter((id) => id !== courseid) : [...checked, courseid]
    });
  }, "togglecourse");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "row g-2 mb-3", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "col-sm input-group", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "input-group-text", children: /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-magnifying-glass" }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 75,
          columnNumber: 46
        }, this) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 75,
          columnNumber: 11
        }, this),
        /* @__PURE__ */ jsxDEV(
          "input",
          {
            type: "search",
            className: "form-control",
            placeholder: str("searchuserposts"),
            value: query,
            onChange: (e) => onChange({ ...filters, query: e.target.value })
          },
          void 0,
          false,
          {
            fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
            lineNumber: 76,
            columnNumber: 11
          },
          this
        )
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
        lineNumber: 74,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("div", { className: "col-sm-auto", children: /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-outline-secondary w-100",
          "aria-expanded": open,
          "aria-controls": "moodleoverflow-postfilter",
          onClick: () => setOpen(!open),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-filter me-2" }, void 0, false, {
              fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
              lineNumber: 82,
              columnNumber: 13
            }, this),
            str("filterposts"),
            active > 0 && /* @__PURE__ */ jsxDEV("span", { className: "badge text-bg-primary ms-2", children: active }, void 0, false, {
              fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
              lineNumber: 84,
              columnNumber: 28
            }, this)
          ]
        },
        void 0,
        true,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 80,
          columnNumber: 11
        },
        this
      ) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
        lineNumber: 79,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("div", { className: "col-sm-auto", children: /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-outline-secondary w-100",
          onClick: () => onSortChange(!newestfirst),
          children: [
            /* @__PURE__ */ jsxDEV("i", { className: `fa-solid me-2 ${newestfirst ? "fa-arrow-down-wide-short" : "fa-arrow-up-short-wide"}` }, void 0, false, {
              fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
              lineNumber: 90,
              columnNumber: 13
            }, this),
            str(newestfirst ? "firstnewest" : "firstoldest")
          ]
        },
        void 0,
        true,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 88,
          columnNumber: 11
        },
        this
      ) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
        lineNumber: 87,
        columnNumber: 9
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
      lineNumber: 73,
      columnNumber: 7
    }, this),
    open && /* @__PURE__ */ jsxDEV("div", { id: "moodleoverflow-postfilter", className: "card mb-4", children: /* @__PURE__ */ jsxDEV("div", { className: "card-body", children: [
      /* @__PURE__ */ jsxDEV("fieldset", { className: "mb-3", children: [
        /* @__PURE__ */ jsxDEV("legend", { className: "h6", children: str("coursefilter") }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 100,
          columnNumber: 15
        }, this),
        /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap column-gap-4 row-gap-1", children: courses.map(([courseid, course]) => /* @__PURE__ */ jsxDEV("div", { className: "form-check", children: [
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              className: "form-check-input",
              type: "checkbox",
              id: `moodleoverflow-course-${courseid}`,
              checked: selected === null || selected.includes(courseid),
              onChange: () => togglecourse(courseid)
            },
            void 0,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
              lineNumber: 104,
              columnNumber: 21
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("label", { className: "form-check-label", htmlFor: `moodleoverflow-course-${courseid}`, children: course }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
            lineNumber: 107,
            columnNumber: 21
          }, this)
        ] }, courseid, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 103,
          columnNumber: 19
        }, this)) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 101,
          columnNumber: 15
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
        lineNumber: 99,
        columnNumber: 13
      }, this),
      /* @__PURE__ */ jsxDEV("fieldset", { children: [
        /* @__PURE__ */ jsxDEV("legend", { className: "h6", children: str("timefilter") }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 116,
          columnNumber: 15
        }, this),
        /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap column-gap-4 row-gap-1", children: timespans.map(({ key, seconds }) => /* @__PURE__ */ jsxDEV("div", { className: "form-check", children: [
          /* @__PURE__ */ jsxDEV(
            "input",
            {
              className: "form-check-input",
              type: "radio",
              name: "moodleoverflow-timespan",
              id: `moodleoverflow-timespan-${key}`,
              checked: timespan === seconds,
              onChange: () => onChange({ ...filters, timespan: seconds })
            },
            void 0,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
              lineNumber: 120,
              columnNumber: 21
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("label", { className: "form-check-label", htmlFor: `moodleoverflow-timespan-${key}`, children: str(key) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
            lineNumber: 123,
            columnNumber: 21
          }, this)
        ] }, key, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 119,
          columnNumber: 19
        }, this)) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 117,
          columnNumber: 15
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
        lineNumber: 115,
        columnNumber: 13
      }, this),
      active > 0 && /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          className: "btn btn-link btn-sm px-0 mt-3",
          onClick: () => onChange({ query, courses: null, timespan: null }),
          children: str("resetfilters")
        },
        void 0,
        false,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
          lineNumber: 132,
          columnNumber: 15
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
      lineNumber: 98,
      columnNumber: 11
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
      lineNumber: 97,
      columnNumber: 9
    }, this)
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/PostFilter.tsx",
    lineNumber: 72,
    columnNumber: 5
  }, this);
}
__name(PostFilter, "PostFilter");
export {
  PostFilter as default
};
//# sourceMappingURL=PostFilter.dev.js.map
