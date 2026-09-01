var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Filter for the User posts.
 *
 * @module     mod_moodleoverflow/filters
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const matchesquery = /* @__PURE__ */ __name((post, query) => {
  const needle = query.trim().toLowerCase();
  return [post.discussionsubject, post.message, post.modflow, post.course].some((field) => field.toLowerCase().includes(needle));
}, "matchesquery");
const approvedBy = /* @__PURE__ */ __name(({ query, courses, timespan }, now) => (post) => matchesquery(post, query) && (courses === null || courses.includes(post.courseid)) && (timespan === null || post.created >= now - timespan), "approvedBy");
export {
  approvedBy
};
//# sourceMappingURL=filters.dev.js.map
