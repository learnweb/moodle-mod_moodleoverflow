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

import {getString} from "core/str";
import {prefetchStrings} from 'core/prefetch';
/**
 * JavaScript for
 *
 * @module     mod_moodleoverflow/show_post
 * @copyright  2025 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const showpostButton = document.getElementById('moodleoverflow_showpost');
const postElement = document.getElementById('moodleoverflow_original_post');

const Selectors = {
    actions: {
        showpostbutton: '[data-action="mod_moodleoverflow/showpost_button"]',
    },
};

/**
 * Init function.
 */
export function init() {
    prefetchStrings('moodleoverflow', ['showpost_expand', 'showpost_collapse',]);
    postElement.setAttribute('expanded', 'false');
    postElement.style.maxHeight = '0px';
    addEventListener();
}

/**
 * Event listener.
 */
const addEventListener = () => {
    document.addEventListener('click', async e => {
        if (e.target.closest(Selectors.actions.showpostbutton)) {
            if (postElement.getAttribute('expanded') === 'true') {
                showpostButton.textContent = await getString('showpost_expand', 'moodleoverflow');
                postElement.style.maxHeight = '0px';
                postElement.setAttribute('expanded', 'false');
            } else {
                showpostButton.textContent =  await getString('showpost_collapse', 'moodleoverflow');
                postElement.style.maxHeight = `${postElement.scrollHeight}px`;
                postElement.setAttribute('expanded', 'true');
            }
        }
    });
};
