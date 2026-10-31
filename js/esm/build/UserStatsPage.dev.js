var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
import { useState } from "react";
import { field, isInactive, matchesName, metrics, scopes, sortStats } from "./userstats";
import { str } from "./lang";
const scopestrings = {
  forum: "userstatsthisforum",
  course: "userstatswholecourse"
};
const metriclabels = {
  activity: { key: "userstatsactivity" },
  reputation: { key: "userstatsreputation" },
  upvotes: { key: "userstatsupvotes", icon: "fa-chevron-up" },
  downvotes: { key: "userstatsdownvotes", icon: "fa-chevron-down" }
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
function UserStatsPage({ users }) {
  const [query, setQuery] = useState("");
  const [sort, setSort] = useState({ key: "coursereputation", descending: true });
  const [scope, setScope] = useState("forum");
  const rows = sortStats(users.filter(matchesName(query)), sort);
  const sortBy = /* @__PURE__ */ __name((key) => setSort({ key, descending: sort.key === key ? !sort.descending : key !== "name" }), "sortBy");
  const visibility = /* @__PURE__ */ __name((cellscope) => cellscope === scope ? "" : "d-none d-lg-table-cell", "visibility");
  const header = /* @__PURE__ */ __name((key, label, className, icon, rowSpan) => {
    const active = sort.key === key;
    const direction = sort.descending ? "descending" : "ascending";
    const sorticon = sort.descending ? "fa-sort-down" : "fa-sort-up";
    return /* @__PURE__ */ jsxDEV("th", { scope: "col", className, rowSpan, "aria-sort": active ? direction : void 0, children: /* @__PURE__ */ jsxDEV(
      "button",
      {
        type: "button",
        className: "btn btn-link p-0 text-reset text-decoration-none fw-semibold text-nowrap",
        title: label,
        onClick: () => sortBy(key),
        children: [
          icon && /* @__PURE__ */ jsxDEV("i", { className: `fa-solid ${icon}`, "aria-hidden": "true" }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 67,
            columnNumber: 20
          }, this),
          /* @__PURE__ */ jsxDEV("span", { className: icon ? "visually-hidden" : "", children: label }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 68,
            columnNumber: 11
          }, this),
          /* @__PURE__ */ jsxDEV("i", { className: `fa-solid fa-fw ms-1 ${active ? sorticon : "fa-sort text-body-tertiary"}`, "aria-hidden": "true" }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 69,
            columnNumber: 11
          }, this)
        ]
      },
      void 0,
      true,
      {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 65,
        columnNumber: 9
      },
      this
    ) }, key, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 64,
      columnNumber: 7
    }, this);
  }, "header");
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-baseline gap-2 mb-3", children: [
      /* @__PURE__ */ jsxDEV("h2", { className: "h4 mb-0", children: str("userstats") }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 78,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "badge text-bg-secondary rounded-pill", children: users.length }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 79,
        columnNumber: 9
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 77,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "row g-2 mb-3", children: [
      /* @__PURE__ */ jsxDEV("div", { className: "col-sm input-group", children: [
        /* @__PURE__ */ jsxDEV("span", { className: "input-group-text", children: /* @__PURE__ */ jsxDEV("i", { className: "fa-solid fa-magnifying-glass", "aria-hidden": "true" }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 84,
          columnNumber: 46
        }, this) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 84,
          columnNumber: 11
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
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 85,
            columnNumber: 11
          },
          this
        )
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 83,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("div", { className: "col-sm-auto d-lg-none", children: /* @__PURE__ */ jsxDEV("div", { className: "btn-group w-100", role: "group", "aria-label": str("userstatsscope"), children: scopes.map((s) => /* @__PURE__ */ jsxDEV(
        "button",
        {
          type: "button",
          "aria-pressed": scope === s,
          onClick: () => setScope(s),
          className: `btn ${scope === s ? "btn-primary" : "btn-outline-primary"}`,
          children: str(scopestrings[s])
        },
        s,
        false,
        {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 91,
          columnNumber: 15
        },
        this
      )) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 89,
        columnNumber: 11
      }, this) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 88,
        columnNumber: 9
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 82,
      columnNumber: 7
    }, this),
    rows.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted", children: /* @__PURE__ */ jsxDEV("em", { children: str(users.length === 0 ? "nothingtodisplay" : "noresults", true) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 101,
      columnNumber: 35
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 101,
      columnNumber: 9
    }, this) : /* @__PURE__ */ jsxDEV("div", { className: "card shadow-sm", children: /* @__PURE__ */ jsxDEV("div", { className: "table-responsive", children: /* @__PURE__ */ jsxDEV("table", { className: "table table-hover align-middle mb-0", children: [
      /* @__PURE__ */ jsxDEV("thead", { children: [
        /* @__PURE__ */ jsxDEV("tr", { children: [
          header("name", str("fullnameuser", true), "align-bottom", void 0, 2),
          scopes.map((s) => /* @__PURE__ */ jsxDEV(
            "th",
            {
              scope: "colgroup",
              colSpan: metrics.length,
              className: `text-center border-start ${visibility(s)}`,
              children: str(scopestrings[s])
            },
            s,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
              lineNumber: 110,
              columnNumber: 21
            },
            this
          ))
        ] }, void 0, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 107,
          columnNumber: 17
        }, this),
        /* @__PURE__ */ jsxDEV("tr", { children: scopes.flatMap((s) => metrics.map((m, i) => header(
          field(s, m),
          str(metriclabels[m].key),
          `text-end ${i === 0 ? "border-start" : ""} ${visibility(s)}`,
          metriclabels[m].icon
        ))) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 116,
          columnNumber: 17
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 106,
        columnNumber: 15
      }, this),
      /* @__PURE__ */ jsxDEV("tbody", { children: rows.map((user) => {
        const muted = isInactive(user) ? "text-body-secondary" : "";
        return /* @__PURE__ */ jsxDEV("tr", { children: [
          /* @__PURE__ */ jsxDEV("th", { scope: "row", className: `fw-normal ${muted}`, children: /* @__PURE__ */ jsxDEV("a", { href: user.profileurl, className: "text-decoration-none", children: user.name }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 127,
            columnNumber: 25
          }, this) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 126,
            columnNumber: 23
          }, this),
          scopes.flatMap((s) => metrics.map((m, i) => /* @__PURE__ */ jsxDEV("td", { className: `text-end ${i === 0 ? "border-start" : ""} ${visibility(s)} ${muted}`, children: user[field(s, m)] }, field(s, m), false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
            lineNumber: 130,
            columnNumber: 25
          }, this)))
        ] }, user.userid, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
          lineNumber: 125,
          columnNumber: 21
        }, this);
      }) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
        lineNumber: 121,
        columnNumber: 15
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 105,
      columnNumber: 13
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 104,
      columnNumber: 11
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 103,
      columnNumber: 9
    }, this),
    /* @__PURE__ */ jsxDEV("p", { className: "small text-muted mt-3 mb-1", children: str("helpamountofactivity_help") }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 143,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV("p", { className: "small text-muted", children: str("userstatscoursereputationinfo") }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
      lineNumber: 144,
      columnNumber: 7
    }, this)
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/UserStatsPage.tsx",
    lineNumber: 76,
    columnNumber: 5
  }, this);
}
__name(UserStatsPage, "UserStatsPage");
export {
  UserStatsPage as default
};
//# sourceMappingURL=UserStatsPage.dev.js.map
