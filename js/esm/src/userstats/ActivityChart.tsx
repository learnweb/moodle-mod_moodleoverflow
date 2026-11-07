// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

import {useEffect, useMemo, useRef} from "react";
import Pending from "@moodle/lms/core/pending";
import {Scope, Timeline} from "./stats";
import {groupTimeline, mergeDays, Period, Unit} from "./timeline";
import {Chart, loadChart, ThemeColours, themeColours, useDarkMode} from "../chart";
import {str} from "../lang";

const lang = document.documentElement.lang || undefined;
const longdate = new Intl.DateTimeFormat(lang, {dateStyle: "long"});

/** How a unit is labelled: the heading and the column of the data table, the dates on the axis and in the tooltip. */
const units: Record<Unit, {heading: string, column: string, axis: Intl.DateTimeFormat, describe: (start: Date) => string}> = {
  day: {
    heading: "userstatsperday",
    column: "userstatsday",
    axis: new Intl.DateTimeFormat(lang, {day: "numeric", month: "short"}),
    describe: (start) => longdate.format(start),
  },
  week: {
    heading: "userstatsperweek",
    column: "userstatsweek",
    axis: new Intl.DateTimeFormat(lang, {day: "numeric", month: "short"}),
    describe: (start) => str("userstatsweekof", false, longdate.format(start)),
  },
};

/**
 * One line of the chart, with the area below it filled.
 *
 * @param label The name of the line
 * @param data One number per period
 * @param rgb The colour as "r, g, b"
 * @returns The Chart.js dataset
 */
const line = (label: string, data: number[], rgb: string) => ({
  label,
  data,
  borderColor: `rgb(${rgb})`,
  backgroundColor: `rgba(${rgb}, 0.15)`,
  // The points (also in the legend and the tooltip) are filled; backgroundColor only fills the area below the line.
  pointBackgroundColor: `rgb(${rgb})`,
  pointHoverBackgroundColor: `rgb(${rgb})`,
  borderWidth: 2,
  fill: true,
  tension: 0.3,
});

/**
 * The Chart.js configuration of the posts and votes per period.
 *
 * @param unit The unit of the periods
 * @param periods The periods to show
 * @param colours The colours of the theme
 * @returns The configuration
 */
const chartConfig = (unit: Unit, periods: Period[], colours: ThemeColours) => {
  const font = {family: colours.font};
  return {
    type: "line",
    data: {
      labels: periods.map((period) => units[unit].axis.format(period.start)),
      datasets: [
        line(str("posts", true), periods.map((period) => period.posts), colours.primary),
        line(str("userstatsvotes"), periods.map((period) => period.votes), colours.success),
      ],
    },
    options: {
      maintainAspectRatio: false,
      // A single period has no line, so its points are shown.
      elements: {point: {radius: periods.length === 1 ? 4 : 0, hoverRadius: 4}},
      interaction: {mode: "index", intersect: false},
      scales: {
        x: {ticks: {color: colours.text, font, maxRotation: 0}, grid: {color: colours.grid}, border: {color: colours.grid}},
        y: {
          beginAtZero: true,
          ticks: {color: colours.text, font, precision: 0},
          grid: {color: colours.grid},
          border: {display: false},
        },
      },
      plugins: {
        legend: {position: "bottom", labels: {color: colours.text, font, usePointStyle: true}},
        tooltip: {callbacks: {title: (items: {dataIndex: number}[]) => units[unit].describe(periods[items[0].dataIndex].start)}},
      },
    },
  };
};

/**
 * React component for the user statistics: how many posts were written and how many votes were given over time, in
 * this forum or in the whole course, which adds up every forum of the timeline. The numbers are also in a table for
 * screen readers, which cannot read a chart.
 *
 * @module     mod_moodleoverflow/userstats/ActivityChart
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default function ActivityChart({timeline, modflowid, firstweekday, scope}: {
  timeline: Timeline,
  modflowid: number,
  firstweekday: number,
  scope: Scope,
}) {
  const canvas = useRef<HTMLCanvasElement>(null);
  const dark = useDarkMode();
  const {unit, periods} = useMemo(() => {
    const days = scope === "forum" ? timeline[modflowid] ?? [] : mergeDays(Object.values(timeline));
    return groupTimeline(days, firstweekday);
  }, [timeline, modflowid, scope, firstweekday]);

  // The chart is drawn again when the periods or the colour mode change.
  useEffect(() => {
    let chart: Chart | undefined;
    let cancelled = false;
    const pending = new Pending("mod_moodleoverflow/ActivityChart");
    loadChart().then((ChartJs) => {
      const element = canvas.current;
      if (!cancelled && element) {
        chart = new ChartJs(element, chartConfig(unit, periods, themeColours(dark)));
      }
      return undefined;
    }).finally(() => pending.resolve());
    return () => {
      cancelled = true;
      chart?.destroy();
    };
  }, [unit, periods, dark]);

  return (
    <div className="card shadow-sm mb-4">
      <div className="card-header">
        <h3 className="h5 mb-0">{str(units[unit].heading)}</h3>
      </div>
      <div className="card-body">
        {periods.length === 0 ? (
          <p className="text-muted mb-0"><em>{str("nothingtodisplay", true)}</em></p>
        ) : (
          <>
            <div className="position-relative" style={{height: "300px"}}>
              <canvas ref={canvas} aria-hidden="true"/>
            </div>
            <table className="visually-hidden">
              <caption>{str(units[unit].heading)}</caption>
              <thead>
                <tr>
                  <th scope="col">{str(units[unit].column)}</th>
                  <th scope="col">{str("posts", true)}</th>
                  <th scope="col">{str("userstatsvotes")}</th>
                </tr>
              </thead>
              <tbody>
                {periods.map((period) => (
                  <tr key={period.start.getTime()}>
                    <th scope="row">{units[unit].describe(period.start)}</th>
                    <td>{period.posts}</td>
                    <td>{period.votes}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </div>
    </div>
  );
}
