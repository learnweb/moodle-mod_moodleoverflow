var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
import { useState } from "react";
import { field, isInactive, matchesName, metrics, scopes, sortStats } from "./stats";
import ActivityChart from "./ActivityChart";
import { str } from "../lang";
const metriclabels = {
  activity: { key: "userstatsactivity" },
  reputation: { key: "userstatsreputation" },
  upvotes: { key: "userstatsupvotes", icon: "fa-chevron-up" },
  downvotes: { key: "userstatsdownvotes", icon: "fa-chevron-down" }
};
const scopelabels = {
  forum: "userstatsthisforum",
  course: "userstatswholecourse"
};
function SortHeader({ column, label, sort, onSort, className, icon, rowSpan }) {
  const active = sort.key === column;
  const direction = sort.descending ? "descending" : "ascending";
  const sorticon = sort.descending ? "fa-sort-down" : "fa-sort-up";
  return /* @__PURE__ */ jsxDEV("th", { scope: "col", className, rowSpan, "aria-sort": active ? direction : void 0, children: /* @__PURE__ */ jsxDEV(
    "button",
    {
      type: "button",
      title: label,
      onClick: () => onSort(column),
      className: "btn btn-link d-inline-flex align-items-center gap-1 p-0 text-reset text-decoration-none fw-semibold text-nowrap",
      children: [
        icon && /* @__PURE__ */ jsxDEV("i", { className: `fa-solid ${icon}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 66,
          columnNumber: 18
        }, this),
        /* @__PURE__ */ jsxDEV("span", { className: icon ? "visually-hidden" : "", children: label }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 67,
          columnNumber: 9
        }, this),
        /* @__PURE__ */ jsxDEV("i", { className: `fa-solid fa-fw ${active ? sorticon : "fa-sort text-body-tertiary"}`, "aria-hidden": "true" }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 68,
          columnNumber: 9
        }, this)
      ]
    },
    void 0,
    true,
    {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 63,
      columnNumber: 7
    },
    this
  ) }, void 0, false, {
    fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
    lineNumber: 62,
    columnNumber: 5
  }, this);
}
__name(SortHeader, "SortHeader");
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
function UserStatsPage({ users, modflowid, timeline, firstweekday }) {
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState({ key: "coursereputation", descending: true });
  const [scope, setScope] = useState("forum");
  const rows = sortStats(users.filter(matchesName(query)), sort);
  const sortBy = /* @__PURE__ */ __name((key) => setSort({ key, descending: sort.key === key ? !sort.descending : key !== "name" }), "sortBy");
  const visibility = /* @__PURE__ */ __name((cellscope) => cellscope === scope ? "" : "d-none d-lg-table-cell", "visibility");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-baseline gap-2", children: [
        /* @__PURE__ */ jsxDEV("h2", { className: "h4 mb-0", children: str("userstats") }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 107,
          columnNumber: 11
        }, this),
        /* @__PURE__ */ jsxDEV("span", { className: "badge text-bg-secondary rounded-pill", children: users.length }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 108,
          columnNumber: 11
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 106,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("div", { className: "btn-group", role: "group", "aria-label": str("userstatsscope"), children: scopes.map((s) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          "aria-pressed": scope === s,
          onClick: () => setScope(s),
          className: `btn ${scope === s ? "btn-primary" : "btn-outline-primary"}`,
          children: str(scopelabels[s])
        },
        s,
        false,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 112,
          columnNumber: 13
        },
        this
      )) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 110,
        columnNumber: 9
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 105,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV(ActivityChart, { timeline, modflowid, firstweekday, scope }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 120,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "input-group mb-3", children: [
      /* @__PURE__ */ jsxDEV("span", { className: "input-group-text", children: /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-magnifying-glass", "aria-hidden": "true" }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 123,
        columnNumber: 44
      }, this) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 123,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          type: "search",
          className: "form-control",
          placeholder: str("userstatssearch"),
          "aria-label": str("userstatssearch"),
          value: query,
          onChange: (e) => setQuery(e.target.value)
        },
        void 0,
        false,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 124,
          columnNumber: 9
        },
        this
      )
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 122,
      columnNumber: 7
    }, this),
    rows.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted", children: /* @__PURE__ */ jsxDEV("em", { children: str(users.length === 0 ? "nothingtodisplay" : "noresults", true) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 129,
      columnNumber: 35
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 129,
      columnNumber: 9
    }, this) : /* @__PURE__ */ jsxDEV("div", { className: "card shadow-sm", children: /* @__PURE__ */ jsxDEV("div", { className: "table-responsive", children: /* @__PURE__ */ jsxDEV("table", { className: "table table-hover align-middle mb-0", children: [
      /* @__PURE__ */ jsxDEV("thead", { children: [
        /* @__PURE__ */ jsxDEV("tr", { children: [
          /* @__PURE__ */ jsxDEV(
            SortHeader,
            {
              column: "name",
              label: str("fullnameuser", true),
              sort,
              onSort: sortBy,
              className: "align-bottom",
              rowSpan: 2
            },
            void 0,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
              lineNumber: 136,
              columnNumber: 19
            },
            this
          ),
          scopes.map((s) => /* @__PURE__ */ jsxDEV(
            "th",
            {
              scope: "colgroup",
              colSpan: metrics.length,
              className: `text-center border-start ${visibility(s)}`,
              children: str(scopelabels[s])
            },
            s,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
              lineNumber: 139,
              columnNumber: 21
            },
            this
          ))
        ] }, void 0, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 135,
          columnNumber: 17
        }, this),
        /* @__PURE__ */ jsxDEV("tr", { children: scopes.flatMap((s) => metrics.map((m, i) => /* @__PURE__ */ jsxDEV(
          SortHeader,
          {
            column: field(s, m),
            label: str(metriclabels[m].key),
            icon: metriclabels[m].icon,
            sort,
            onSort: sortBy,
            className: `text-end ${i === 0 ? "border-start" : ""} ${visibility(s)}`
          },
          field(s, m),
          false,
          {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
            lineNumber: 147,
            columnNumber: 21
          },
          this
        ))) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 145,
          columnNumber: 17
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 134,
        columnNumber: 15
      }, this),
      /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((user) => {
        const muted = isInactive(user) ? "text-body-secondary" : "";
        return /* @__PURE__ */ jsxDEV("tr", { children: [
          /* @__PURE__ */ jsxDEV("th", { scope: "row", className: `fw-normal ${muted}`, children: /* @__PURE__ */ jsxDEV("a", { href: user.profileurl, className: "text-decoration-none", children: user.name }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
            lineNumber: 159,
            columnNumber: 25
          }, this) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
            lineNumber: 158,
            columnNumber: 23
          }, this),
          scopes.flatMap((s) => metrics.map((m, i) => /* @__PURE__ */ jsxDEV("td", { className: `text-end ${i === 0 ? "border-start" : ""} ${visibility(s)} ${muted}`, children: user[field(s, m)] }, field(s, m), false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
            lineNumber: 162,
            columnNumber: 25
          }, this)))
        ] }, user.userid, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
          lineNumber: 157,
          columnNumber: 21
        }, this);
      }) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
        lineNumber: 153,
        columnNumber: 15
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 133,
      columnNumber: 13
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 132,
      columnNumber: 11
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 131,
      columnNumber: 9
    }, this),
    /* @__PURE__ */ jsxDEV("p", { className: "small text-muted mt-3 mb-1", children: str("helpamountofactivity_help") }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 175,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV("p", { className: "small text-muted", children: str("userstatscoursereputationinfo") }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
      lineNumber: 176,
      columnNumber: 7
    }, this)
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/userstats/UserStatsPage.tsx",
    lineNumber: 104,
    columnNumber: 5
  }, this);
}
__name(UserStatsPage, "UserStatsPage");
export {
  UserStatsPage as default
};
//# sourceMappingURL=UserStatsPage.dev.js.map
