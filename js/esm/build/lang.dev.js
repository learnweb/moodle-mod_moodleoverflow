var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Language strings of this plugin, the way get_string() works in PHP.
 *
 * @module     mod_moodleoverflow/lang
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const str = /* @__PURE__ */ __name((key, fromCore, param) => {
  return fromCore ? M.util.get_string(key, "core", param) : M.util.get_string(key, "mod_moodleoverflow", param);
}, "str");
export {
  str
};
//# sourceMappingURL=lang.dev.js.map
