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

import {ActivityDay} from "./stats";

export type Unit = "day" | "week";

export type Period = {
    start: Date;
    posts: number;
    votes: number;
};

/** A timeline of up to this many days is shown per day, a longer one per week. */
const maxdays = 30;

const parseDay = (day: string): Date => {
    const [year, month, date] = day.split("-").map(Number);
    return new Date(year, month - 1, date);
};

const addDays = (date: Date, days: number): Date => new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

/** Whole days between two dates. Rounded, because a day with a daylight saving change is not 24 hours long. */
const daysBetween = (from: Date, to: Date): number => Math.round((to.getTime() - from.getTime()) / 86400000);

/**
 * Chooses the unit of the chart, so that it has enough points to draw a line but not too many to read it.
 *
 * @param first The first day of the timeline
 * @param last The last day of the timeline
 * @returns The unit
 */
export const chooseUnit = (first: Date, last: Date): Unit => (daysBetween(first, last) + 1 <= maxdays ? "day" : "week");

/**
 * Returns the first day of the period a date belongs to.
 *
 * @param date The date
 * @param unit The unit of the periods
 * @param firstweekday The day a week starts with, 0 for Sunday
 * @returns The first day of the period
 */
const periodStart = (date: Date, unit: Unit, firstweekday: number): Date =>
    (unit === "week" ? addDays(date, -((date.getDay() - firstweekday + 7) % 7)) : date);

const periodLength = (unit: Unit): number => (unit === "week" ? 7 : 1);

/**
 * Adds up the timelines of several forums.
 *
 * @param timelines The days with activity of each forum, ordered by date
 * @returns The days with activity of all of them, ordered by date
 */
export const mergeDays = (timelines: ActivityDay[][]): ActivityDay[] => {
    const days = new Map<string, ActivityDay>();
    for (const day of timelines.flat()) {
        const merged = days.get(day.day) ?? {day: day.day, posts: 0, votes: 0};
        days.set(day.day, {...merged, posts: merged.posts + day.posts, votes: merged.votes + day.votes});
    }
    // Dates as YYYY-MM-DD are ordered like strings.
    return [...days.values()].sort((a, b) => a.day.localeCompare(b.day));
};

/**
 * Groups the days of a timeline into periods.
 *
 * @param days The days with activity, ordered by date
 * @param firstweekday The day a week starts with, 0 for Sunday
 * @param now The current time, which ends the timeline
 * @returns The chosen unit and every period from the first activity until today
 */
export const groupTimeline = (days: ActivityDay[], firstweekday: number, now = new Date()): {unit: Unit, periods: Period[]} => {
    if (days.length === 0) {
        return {unit: "day", periods: []};
    }
    const first = parseDay(days[0].day);
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const lastday = parseDay(days[days.length - 1].day);
    const last = lastday > today ? lastday : today;
    const unit = chooseUnit(first, last);

    const periods = new Map<number, Period>();
    for (let start = periodStart(first, unit, firstweekday); start <= last; start = addDays(start, periodLength(unit))) {
        periods.set(start.getTime(), {start, posts: 0, votes: 0});
    }
    for (const day of days) {
        const period = periods.get(periodStart(parseDay(day.day), unit, firstweekday).getTime());
        if (period) {
            period.posts += day.posts;
            period.votes += day.votes;
        }
    }
    return {unit, periods: [...periods.values()]};
};
