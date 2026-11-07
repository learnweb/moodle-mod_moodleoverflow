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
 * Tests for the grouping of the activity timeline.
 *
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {chooseUnit, groupTimeline, mergeDays} from '@moodle/lms/mod_moodleoverflow/userstats/timeline';

const day = (date: string, posts = 1, votes = 0) => ({day: date, posts, votes});
const date = (year: number, month: number, dayofmonth: number) => new Date(year, month - 1, dayofmonth);
const starts = (periods: {start: Date}[]) => periods.map((period) => period.start.toDateString());

describe('mod_moodleoverflow/userstats/timeline', () => {
    it('chooses days up to 30 days and weeks after that', () => {
        const first = date(2026, 1, 1);
        expect(chooseUnit(first, date(2026, 1, 1))).toBe('day');
        expect(chooseUnit(first, date(2026, 1, 30))).toBe('day');
        expect(chooseUnit(first, date(2026, 1, 31))).toBe('week');
        expect(chooseUnit(first, date(2028, 1, 1))).toBe('week');
    });

    it('adds up the timelines of several forums', () => {
        const forum = [day('2026-10-02', 2, 1), day('2026-10-05', 1, 0)];
        const other = [day('2026-10-01', 1, 0), day('2026-10-05', 3, 4)];
        expect(mergeDays([forum, other, []])).toEqual([day('2026-10-01', 1, 0), day('2026-10-02', 2, 1), day('2026-10-05', 4, 4)]);
        expect(mergeDays([])).toEqual([]);
    });

    it('has no periods without activity', () => {
        expect(groupTimeline([], 1)).toEqual({unit: 'day', periods: []});
    });

    it('lists every day until today, also the empty ones', () => {
        const {unit, periods} = groupTimeline([day('2026-10-29', 12, 3), day('2026-10-31', 4)], 1, date(2026, 11, 2));
        expect(unit).toBe('day');
        expect(periods.map((period) => [period.posts, period.votes])).toEqual([[12, 3], [0, 0], [4, 0], [0, 0], [0, 0]]);
    });

    it('groups the days into calendar weeks that start with the first day of the week', () => {
        // The 1st of October 2026 is a Thursday.
        const days = [day('2026-10-01', 2, 1), day('2026-10-04', 3), day('2026-10-05', 1, 5)];
        const monday = groupTimeline(days, 1, date(2026, 11, 2));
        expect(monday.unit).toBe('week');
        expect(monday.periods[0].start).toEqual(date(2026, 9, 28));
        expect(monday.periods.slice(0, 2).map((period) => [period.posts, period.votes])).toEqual([[5, 1], [1, 5]]);
        expect(monday.periods.at(-1)?.start).toEqual(date(2026, 11, 2));

        const sunday = groupTimeline(days, 0, date(2026, 11, 2));
        expect(sunday.periods[0].start).toEqual(date(2026, 9, 27));
        expect(sunday.periods.slice(0, 2).map((period) => period.posts)).toEqual([2, 4]);
    });

    it('stays with weeks for a timeline of more than a year', () => {
        // The 15th of October 2025 is a Wednesday.
        const {unit, periods} = groupTimeline([day('2025-10-15', 2), day('2025-10-16', 1)], 1, date(2026, 11, 5));
        expect(unit).toBe('week');
        expect(periods).toHaveLength(56);
        expect(starts(periods.slice(0, 2))).toEqual([date(2025, 10, 13).toDateString(), date(2025, 10, 20).toDateString()]);
        expect(periods[0].posts).toBe(3);
    });
});
