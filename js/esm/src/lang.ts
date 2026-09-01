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
 * Language strings of this plugin, the way get_string() works in PHP.
 *
 * @module     mod_moodleoverflow/lang
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Moodle's global string helper. */
declare const M: {
    util: {
        get_string: (identifier: string, component: string, param?: StringParams) => string;
    };
};

/** What can be substituted into a string's placeholders, the equivalent of PHP's $a. */
type StringParams = Record<string, string | number> | string | number;

/**
 * A language string of this plugin.
 *
 * @param key The string identifier, as in the lang file.
 * @param fromCore if the string is from the lsf language file or the moodle core
 * @param param Fills the string's placeholders, like the $a argument in PHP.
 * @returns The translated string, or "[[key,local_lsf_unification]]" if it is not defined.
 */
export const str = (key: string, fromCore?: boolean, param?: StringParams): string => {
    return fromCore ? M.util.get_string(key, "core", param) : M.util.get_string(key, "mod_moodleoverflow", param);
};
