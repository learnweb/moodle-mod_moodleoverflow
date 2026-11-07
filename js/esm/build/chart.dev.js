var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Helpers to draw charts with Chart.js, which Moodle core ships as the AMD module core/chartjs.
 *
 * A canvas does not know the CSS of the page, so the colours of the theme are read from its custom properties and
 * read again when the colour mode (light or dark) changes.
 *
 * @module     mod_moodleoverflow/chart
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useEffect, useState } from "react";
import { requireAsync } from "@moodle/lms/core/amd";
const loadChart = /* @__PURE__ */ __name(() => requireAsync("core/chartjs"), "loadChart");
const computeColour = /* @__PURE__ */ __name((property) => {
  const probe = document.createElement("span");
  probe.style.color = `var(${property})`;
  document.body.append(probe);
  const colour = getComputedStyle(probe).color;
  probe.remove();
  return colour;
}, "computeColour");
const computeRgb = /* @__PURE__ */ __name((property) => (computeColour(property).match(/[\d.]+/g) ?? []).slice(0, 3).join(", "), "computeRgb");
const themeColours = /* @__PURE__ */ __name((dark) => {
  const data = /* @__PURE__ */ __name((name) => computeRgb(dark ? `--bs-${name}-text-emphasis` : `--bs-${name}`), "data");
  return {
    primary: data("primary"),
    success: data("success"),
    text: computeColour("--bs-secondary-color"),
    grid: computeColour("--bs-border-color-translucent"),
    font: getComputedStyle(document.body).fontFamily
  };
}, "themeColours");
const useDarkMode = /* @__PURE__ */ __name(() => {
  const isDark = /* @__PURE__ */ __name(() => document.documentElement.dataset.bsTheme === "dark", "isDark");
  const [dark, setDark] = useState(isDark);
  useEffect(() => {
    const observer = new MutationObserver(() => setDark(isDark()));
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ["data-bs-theme"] });
    return () => observer.disconnect();
  }, []);
  return dark;
}, "useDarkMode");
export {
  loadChart,
  themeColours,
  useDarkMode
};
//# sourceMappingURL=chart.dev.js.map
