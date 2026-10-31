var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The user statistics: their data type, and how they are searched and sorted.
 *
 * @module     mod_moodleoverflow/userstats
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const scopes = ["forum", "course"];
const metrics = ["activity", "reputation", "upvotes", "downvotes"];
const field = /* @__PURE__ */ __name((scope, metric) => `${scope}${metric}`, "field");
const matchesName = /* @__PURE__ */ __name((query) => (user) => user.name.toLowerCase().includes(query.trim().toLowerCase()), "matchesName");
const isInactive = /* @__PURE__ */ __name((user) => user.courseactivity === 0, "isInactive");
const sortStats = /* @__PURE__ */ __name((users, { key, descending }) => [...users].sort((a, b) => {
  const order = key === "name" ? a.name.localeCompare(b.name) : a[key] - b[key];
  return (descending ? -order : order) || a.name.localeCompare(b.name);
}), "sortStats");
export {
  field,
  isInactive,
  matchesName,
  metrics,
  scopes,
  sortStats
};
//# sourceMappingURL=userstats.dev.js.map
