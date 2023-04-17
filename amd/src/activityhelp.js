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
 * Show a help string for the amount of activity column in userstats_table.php
 *
 * @module     mod_moodleoverflow/activityhelp
 * @copyright  2023 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



const Selectors = {
    actions: {
        showHelpIcon: '[data-action="showhelpicon"]',
    },
};

/**
 * Function that shows the help string.
 */
export const init = () => {
    document.addEventListener('click', event => {
        if (event.target.closest(Selectors.actions.showHelpIcon)) {
            event.preventDefault();
        }
    });
};