var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * Groups the activity per day into the periods the chart shows: days for a short timeline, weeks for a longer one.
 * Every period from the first activity until today is listed, also the empty ones.
 *
 * The days are calendar dates without a time, so they are handled as local dates, in which no timezone can move them.
 *
 * @module     mod_moodleoverflow/userstats/timeline
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
const maxdays = 30;
const parseDay = /* @__PURE__ */ __name((day) => {
  const [year, month, date] = day.split("-").map(Number);
  return new Date(year, month - 1, date);
}, "parseDay");
const addDays = /* @__PURE__ */ __name((date, days) => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days), "addDays");
const daysBetween = /* @__PURE__ */ __name((from, to) => Math.round((to.getTime() - from.getTime()) / 864e5), "daysBetween");
const chooseUnit = /* @__PURE__ */ __name((first, last) => daysBetween(first, last) + 1 <= maxdays ? "day" : "week", "chooseUnit");
const periodStart = /* @__PURE__ */ __name((date, unit, firstweekday) => unit === "week" ? addDays(date, -((date.getDay() - firstweekday + 7) % 7)) : date, "periodStart");
const periodLength = /* @__PURE__ */ __name((unit) => unit === "week" ? 7 : 1, "periodLength");
const mergeDays = /* @__PURE__ */ __name((timelines) => {
  const days = /* @__PURE__ */ new Map();
  for (const day of timelines.flat()) {
    const merged = days.get(day.day) ?? { day: day.day, posts: 0, votes: 0 };
    days.set(day.day, { ...merged, posts: merged.posts + day.posts, votes: merged.votes + day.votes });
  }
  return [...days.values()].sort((a, b) => a.day.localeCompare(b.day));
}, "mergeDays");
const groupTimeline = /* @__PURE__ */ __name((days, firstweekday, now = /* @__PURE__ */ new Date()) => {
  if (days.length === 0) {
    return { unit: "day", periods: [] };
  }
  const first = parseDay(days[0].day);
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const lastday = parseDay(days[days.length - 1].day);
  const last = lastday > today ? lastday : today;
  const unit = chooseUnit(first, last);
  const periods = /* @__PURE__ */ new Map();
  for (let start = periodStart(first, unit, firstweekday); start <= last; start = addDays(start, periodLength(unit))) {
    periods.set(start.getTime(), { start, posts: 0, votes: 0 });
  }
  for (const day of days) {
    const period = periods.get(periodStart(parseDay(day.day), unit, firstweekday).getTime());
    if (period) {
      period.posts += day.posts;
      period.votes += day.votes;
    }
  }
  return { unit, periods: [...periods.values()] };
}, "groupTimeline");
export {
  chooseUnit,
  groupTimeline,
  mergeDays
};
//# sourceMappingURL=timeline.dev.js.map
