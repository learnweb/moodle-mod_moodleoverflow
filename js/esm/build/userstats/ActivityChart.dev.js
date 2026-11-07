var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { Fragment, jsxDEV } from "react/jsx-dev-runtime";
import { useEffect, useMemo, useRef } from "react";
import Pending from "@moodle/lms/core/pending";
import { groupTimeline, mergeDays } from "./timeline";
import { loadChart, themeColours, useDarkMode } from "../chart";
import { str } from "../lang";
const lang = document.documentElement.lang || void 0;
const longdate = new Intl.DateTimeFormat(lang, { dateStyle: "long" });
const units = {
  day: {
    heading: "userstatsperday",
    column: "userstatsday",
    axis: new Intl.DateTimeFormat(lang, { day: "numeric", month: "short" }),
    describe: /* @__PURE__ */ __name((start) => longdate.format(start), "describe")
  },
  week: {
    heading: "userstatsperweek",
    column: "userstatsweek",
    axis: new Intl.DateTimeFormat(lang, { day: "numeric", month: "short" }),
    describe: /* @__PURE__ */ __name((start) => str("userstatsweekof", false, longdate.format(start)), "describe")
  }
};
const line = /* @__PURE__ */ __name((label, data, rgb) => ({
  label,
  data,
  borderColor: `rgb(${rgb})`,
  backgroundColor: `rgba(${rgb}, 0.15)`,
  // The points (also in the legend and the tooltip) are filled; backgroundColor only fills the area below the line.
  pointBackgroundColor: `rgb(${rgb})`,
  pointHoverBackgroundColor: `rgb(${rgb})`,
  borderWidth: 2,
  fill: true,
  tension: 0.3
}), "line");
const chartConfig = /* @__PURE__ */ __name((unit, periods, colours) => {
  const font = { family: colours.font };
  return {
    type: "line",
    data: {
      labels: periods.map((period) => units[unit].axis.format(period.start)),
      datasets: [
        line(str("posts", true), periods.map((period) => period.posts), colours.primary),
        line(str("userstatsvotes"), periods.map((period) => period.votes), colours.success)
      ]
    },
    options: {
      maintainAspectRatio: false,
      // A single period has no line, so its points are shown.
      elements: { point: { radius: periods.length === 1 ? 4 : 0, hoverRadius: 4 } },
      interaction: { mode: "index", intersect: false },
      scales: {
        x: { ticks: { color: colours.text, font, maxRotation: 0 }, grid: { color: colours.grid }, border: { color: colours.grid } },
        y: {
          beginAtZero: true,
          ticks: { color: colours.text, font, precision: 0 },
          grid: { color: colours.grid },
          border: { display: false }
        }
      },
      plugins: {
        legend: { position: "bottom", labels: { color: colours.text, font, usePointStyle: true } },
        tooltip: { callbacks: { title: /* @__PURE__ */ __name((items) => units[unit].describe(periods[items[0].dataIndex].start), "title") } }
      }
    }
  };
}, "chartConfig");
/**
 * React component for the user statistics: how many posts were written and how many votes were given over time, in
 * this forum or in the whole course, which adds up every forum of the timeline. The numbers are also in a table for
 * screen readers, which cannot read a chart.
 *
 * @module     mod_moodleoverflow/userstats/ActivityChart
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function ActivityChart({ timeline, modflowid, firstweekday, scope }) {
  const canvas = useRef(null);
  const dark = useDarkMode();
  const { unit, periods } = useMemo(() => {
    const days = scope === "forum" ? timeline[modflowid] ?? [] : mergeDays(Object.values(timeline));
    return groupTimeline(days, firstweekday);
  }, [timeline, modflowid, scope, firstweekday]);
  useEffect(() => {
    let chart;
    let cancelled = false;
    const pending = new Pending("mod_moodleoverflow/ActivityChart");
    loadChart().then((ChartJs) => {
      const element = canvas.current;
      if (!cancelled && element) {
        chart = new ChartJs(element, chartConfig(unit, periods, themeColours(dark)));
      }
      return void 0;
    }).finally(() => pending.resolve());
    return () => {
      cancelled = true;
      chart?.destroy();
    };
  }, [unit, periods, dark]);
  return /* @__PURE__ */ jsxDEV("div", { className: "card shadow-sm mb-4", children: [
    /* @__PURE__ */ jsxDEV("div", { className: "card-header", children: /* @__PURE__ */ jsxDEV("h3", { className: "h5 mb-0", children: str(units[unit].heading) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 147,
      columnNumber: 9
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 146,
      columnNumber: 7
    }, this),
    /* @__PURE__ */ jsxDEV("div", { className: "card-body", children: periods.length === 0 ? /* @__PURE__ */ jsxDEV("p", { className: "text-muted mb-0", children: /* @__PURE__ */ jsxDEV("em", { children: str("nothingtodisplay", true) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 151,
      columnNumber: 42
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 151,
      columnNumber: 11
    }, this) : /* @__PURE__ */ jsxDEV(Fragment, { children: [
      /* @__PURE__ */ jsxDEV("div", { className: "position-relative", style: { height: "300px" }, children: /* @__PURE__ */ jsxDEV("canvas", { ref: canvas, "aria-hidden": "true" }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
        lineNumber: 155,
        columnNumber: 15
      }, this) }, void 0, false, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
        lineNumber: 154,
        columnNumber: 13
      }, this),
      /* @__PURE__ */ jsxDEV("table", { className: "visually-hidden", children: [
        /* @__PURE__ */ jsxDEV("caption", { children: str(units[unit].heading) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
          lineNumber: 158,
          columnNumber: 15
        }, this),
        /* @__PURE__ */ jsxDEV("thead", { children: /* @__PURE__ */ jsxDEV("tr", { children: [
          /* @__PURE__ */ jsxDEV("th", { scope: "col", children: str(units[unit].column) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 161,
            columnNumber: 19
          }, this),
          /* @__PURE__ */ jsxDEV("th", { scope: "col", children: str("posts", true) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 162,
            columnNumber: 19
          }, this),
          /* @__PURE__ */ jsxDEV("th", { scope: "col", children: str("userstatsvotes") }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 163,
            columnNumber: 19
          }, this)
        ] }, void 0, true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
          lineNumber: 160,
          columnNumber: 17
        }, this) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
          lineNumber: 159,
          columnNumber: 15
        }, this),
        /* @__PURE__ */ jsxDEV("tbody", { children: periods.map((period) => /* @__PURE__ */ jsxDEV("tr", { children: [
          /* @__PURE__ */ jsxDEV("th", { scope: "row", children: units[unit].describe(period.start) }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 169,
            columnNumber: 21
          }, this),
          /* @__PURE__ */ jsxDEV("td", { children: period.posts }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 170,
            columnNumber: 21
          }, this),
          /* @__PURE__ */ jsxDEV("td", { children: period.votes }, void 0, false, {
            fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
            lineNumber: 171,
            columnNumber: 21
          }, this)
        ] }, period.start.getTime(), true, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
          lineNumber: 168,
          columnNumber: 19
        }, this)) }, void 0, false, {
          fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
          lineNumber: 166,
          columnNumber: 15
        }, this)
      ] }, void 0, true, {
        fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
        lineNumber: 157,
        columnNumber: 13
      }, this)
    ] }, void 0, true, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 153,
      columnNumber: 11
    }, this) }, void 0, false, {
      fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
      lineNumber: 149,
      columnNumber: 7
    }, this)
  ] }, void 0, true, {
    fileName: "public/mod/moodleoverflow/js/esm/src/userstats/ActivityChart.tsx",
    lineNumber: 145,
    columnNumber: 5
  }, this);
}
__name(ActivityChart, "ActivityChart");
export {
  ActivityChart as default
};
//# sourceMappingURL=ActivityChart.dev.js.map
