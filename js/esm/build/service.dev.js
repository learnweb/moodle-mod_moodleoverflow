var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Service class that communicates with moodleoverflows REST API
 *
 * @module     mod_moodleoverflow/service
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Fetch from "@moodle/lms/core/fetch";
const fetchUserPosts = /* @__PURE__ */ __name(async (userid) => {
  const response = await Fetch.performGet("mod_moodleoverflow", `user/posts/${userid}`);
  return await response.json();
}, "fetchUserPosts");
export {
  fetchUserPosts
};
//# sourceMappingURL=service.dev.js.map
