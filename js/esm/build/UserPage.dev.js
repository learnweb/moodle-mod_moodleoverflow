var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
import { useEffect, useState } from "react";
import { fetchUserPosts } from "./service";
import UserPost from "./UserPost";
import PostFilter from "./PostFilter";
import { approvedBy } from "./filters";
import { str } from "./lang";
/**
 * React component for the moodleoverflow user page that shows all the users posts,
 * grouped by the moodleoverflow they were written in.
 *
 * @module     mod_moodleoverflow/UserPage
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function UserPage({ userid, courseid = 0 }) {
  const [posts, setPosts] = useState(null);
  const [filters, setFilters] = useState({
    query: "",
    courses: courseid ? [courseid] : null,
    timespan: null
  });
  const [newestfirst, setNewestfirst] = useState(true);
  const [collapsed, setCollapsed] = useState([]);
  const toggle = /* @__PURE__ */ __name((modflowurl) => setCollapsed(collapsed.includes(modflowurl) ? collapsed.filter((url) => url !== modflowurl) : [...collapsed, modflowurl]), "toggle");
  useEffect(() => {
    fetchUserPosts(userid).then(setPosts);
  }, [userid]);
  if (posts === null) {
    return /* @__PURE__ */ jsxDEV("div", { className: "text-center py-5", children: /* @__PURE__ */ jsxDEV("div", { className: "spinner-border", role: "status", children: /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: str("loading", true) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 54,
      columnNumber: 11
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 53,
      columnNumber: 9
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 52,
      columnNumber: 7
    }, this);
  }
  const matches = posts.filter(approvedBy(filters, Date.now() / 1e3)).sort((a, b) => newestfirst ? b.created - a.created : a.created - b.created);
  const modflowurls = [...new Set(matches.map((post) => post.modflowurl))];
  return /* @__PURE__ */ jsxDEV(Fragment, { children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-baseline gap-2 mb-3", children: [
      /* @__PURE__ */ jsxDEV("h2", { className: "h4 mb-0", children: str("posts", true) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
        lineNumber: 71,
        columnNumber: 9
      }, this),
      /* @__PURE__ */ jsxDEV("span", { className: "text-muted", children: matches.length }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
        lineNumber: 72,
        columnNumber: 9
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 70,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV(
      PostFilter,
      {
        posts,
        filters,
        onChange: setFilters,
        newestfirst,
        onSortChange: setNewestfirst
      },
      void 0,
      false,
      {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
        lineNumber: 75,
        columnNumber: 7
      },
      this
    ),
    matches.length === 0 && /* @__PURE__ */ jsxDEV("p", { className: "text-muted", children: /* @__PURE__ */ jsxDEV("em", { children: str(posts.length === 0 ? "nothingtodisplay" : "noresults", true) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 79,
      columnNumber: 35
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
      lineNumber: 79,
      columnNumber: 9
    }, this),
    modflowurls.map((modflowurl) => {
      const grouped = matches.filter((post) => post.modflowurl === modflowurl);
      const { modflow, course } = grouped[0];
      const isopen = !collapsed.includes(modflowurl);
      return /* @__PURE__ */ jsxDEV("section", { className: "mb-4", children: [
        /* @__PURE__ */ jsxDEV("div", { className: "d-flex align-items-baseline gap-2 border-bottom pb-2 mb-3", children: [
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-link btn-sm text-body py-0 px-2",
              "aria-expanded": isopen,
              "aria-label": modflow,
              onClick: () => toggle(modflowurl),
              children: /* @__PURE__ */ jsxDEV("i", { className: `fa-solid fa-fw ${isopen ? "fa-chevron-down" : "fa-chevron-right"}` }, void 0, false, {
                fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
                lineNumber: 92,
                columnNumber: 17
              }, this)
            },
            void 0,
            false,
            {
              fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
              lineNumber: 90,
              columnNumber: 15
            },
            this
          ),
          /* @__PURE__ */ jsxDEV("h3", { className: "h6 mb-0", children: [
            /* @__PURE__ */ jsxDEV("a", { href: modflowurl, className: "text-decoration-none", children: modflow }, void 0, false, {
              fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
              lineNumber: 95,
              columnNumber: 17
            }, this),
            /* @__PURE__ */ jsxDEV("span", { className: "text-muted fw-normal ms-2", children: course }, void 0, false, {
              fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
              lineNumber: 96,
              columnNumber: 17
            }, this)
          ] }, void 0, true, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
            lineNumber: 94,
            columnNumber: 15
          }, this),
          /* @__PURE__ */ jsxDEV("span", { className: "badge text-bg-secondary ms-auto", children: grouped.length }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
            lineNumber: 98,
            columnNumber: 15
          }, this)
        ] }, void 0, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
          lineNumber: 89,
          columnNumber: 13
        }, this),
        isopen && /* @__PURE__ */ jsxDEV("div", { className: "ps-3", children: grouped.map((post) => /* @__PURE__ */ jsxDEV(UserPost, { post }, post.id, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
          lineNumber: 102,
          columnNumber: 40
        }, this)) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
          lineNumber: 101,
          columnNumber: 15
        }, this)
      ] }, modflowurl, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
        lineNumber: 88,
        columnNumber: 11
      }, this);
    })
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/UserPage.tsx",
    lineNumber: 69,
    columnNumber: 5
  }, this);
}
__name(UserPage, "UserPage");
export {
  UserPage as default
};
//# sourceMappingURL=UserPage.dev.js.map
