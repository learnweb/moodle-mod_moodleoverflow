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
 * Helpers to draw charts with Chart.js, which Moodle core ships as the AMD module core/chartjs.
 *
 * A canvas does not know the CSS of the page, so the colours of the theme are read from its custom properties and
 * read again when the colour mode (light or dark) changes.
 *
 * @module     mod_moodleoverflow/chart
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {requireAsync} from "@moodle/lms/core/amd";

/** The part of a Chart.js chart this plugin uses. */
export interface Chart {
    destroy(): void;
}

/** The Chart.js class. Its configuration is passed as it is, see https://www.chartjs.org/docs/latest/. */
export type ChartClass = new (canvas: HTMLCanvasElement, config: object) => Chart;

/** The colours of the theme, as Chart.js understands them. */
export type ThemeColours = {
    /** The colours of the data series, as "r, g, b", so that they can be made transparent. */
    primary: string;
    success: string;
    /** Labels and grid lines. */
    text: string;
    grid: string;
    font: string;
};

/**
 * Loads Chart.js from Moodle core.
 *
 * @returns The Chart.js class
 */
export const loadChart = (): Promise<ChartClass> => requireAsync<ChartClass>("core/chartjs");

/**
 * Returns the colour of a CSS custom property as the browser computes it, e.g. "rgb(15, 108, 191)".
 *
 * @param property The custom property, e.g. "--bs-primary"
 * @returns The computed colour
 */
const computeColour = (property: string): string => {
    const probe = document.createElement("span");
    probe.style.color = `var(${property})`;
    document.body.append(probe);
    const colour = getComputedStyle(probe).color;
    probe.remove();
    return colour;
};

/**
 * Returns the red, green and blue part of the colour of a CSS custom property, e.g. "15, 108, 191".
 *
 * @param property The custom property
 * @returns The colour without alpha
 */
const computeRgb = (property: string): string => (computeColour(property).match(/[\d.]+/g) ?? []).slice(0, 3).join(", ");

/**
 * Reads the colours of the theme in the current colour mode. In the dark mode the base colours of Bootstrap stay as
 * dark as in the light mode, so the lighter emphasis colours are used for the data instead.
 *
 * @param dark Whether the dark colour mode is used
 * @returns The colours
 */
export const themeColours = (dark: boolean): ThemeColours => {
    const data = (name: string) => computeRgb(dark ? `--bs-${name}-text-emphasis` : `--bs-${name}`);
    return {
        primary: data("primary"),
        success: data("success"),
        text: computeColour("--bs-secondary-color"),
        grid: computeColour("--bs-border-color-translucent"),
        font: getComputedStyle(document.body).fontFamily,
    };
};

/**
 * React hook that returns whether the page is shown in the dark colour mode, and updates when the mode changes.
 * Boost writes the mode to the data-bs-theme attribute of the html tag.
 *
 * @returns Whether the dark colour mode is used
 */
export const useDarkMode = (): boolean => {
    const isDark = () => document.documentElement.dataset.bsTheme === "dark";
    const [dark, setDark] = useState(isDark);
    useEffect(() => {
        const observer = new MutationObserver(() => setDark(isDark()));
        observer.observe(document.documentElement, {attributes: true, attributeFilter: ["data-bs-theme"]});
        return () => observer.disconnect();
    }, []);
    return dark;
};
