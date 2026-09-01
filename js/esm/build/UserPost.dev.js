var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * A single post of a user, as it is listed on the user.php page.
 *
 * @module     mod_moodleoverflow/UserPost
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { str } from "./lang";
const formatCreated = /* @__PURE__ */ __name((created) => new Date(created * 1e3).toLocaleDateString(
  "de-DE",
  { timeZone: "Europe/Berlin", day: "2-digit", month: "2-digit", year: "numeric" }
), "formatCreated");
function UserPost({ post }) {
  return /* @__PURE__ */ jsxDEV("article", { className: "card mb-3", children: /* @__PURE__ */ jsxDEV("div", { className: "card-body", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "d-flex justify-content-between align-items-baseline gap-3 mb-2", children: [
      /* @__PURE__ */ jsxDEV("h4", { className: "h6 mb-0", children: /* @__PURE__ */ jsxDEV("a", { href: post.discussionurl, className: "text-decoration-none", children: post.discussionsubject }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
        lineNumber: 40,
        columnNumber: 13
      }, this) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
        lineNumber: 39,
        columnNumber: 11
      }, this),
      /* @__PURE__ */ jsxDEV("small", { className: "text-muted text-nowrap", children: formatCreated(post.created) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
        lineNumber: 42,
        columnNumber: 11
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
      lineNumber: 38,
      columnNumber: 9
    }, this),
    /* @__PURE__ */ jsxDEV("div", { dangerouslySetInnerHTML: { __html: post.message } }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
      lineNumber: 45,
      columnNumber: 9
    }, this),
    /* @__PURE__ */ jsxDEV("a", { href: post.posturl, className: "small", children: str("showpost") }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
      lineNumber: 46,
      columnNumber: 9
    }, this)
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
    lineNumber: 37,
    columnNumber: 7
  }, this) }, void 0, false, {
    fileName: "public/mod/moodleoverflow/js/esm/src/UserPost.tsx",
    lineNumber: 36,
    columnNumber: 5
  }, this);
}
__name(UserPost, "UserPost");
export {
  UserPost as default
};
//# sourceMappingURL=UserPost.dev.js.map
