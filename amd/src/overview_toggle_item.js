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
 * Javascript that manages toggle items in the activity overview of moodleoverflow
 * Can manage the toggle item for subscription and readtracking setting changes.
 * @module     mod_moodleoverflow/overview_toggle_item
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {request} from 'mod_moodleoverflow/api';
import {changeSubscription} from 'mod_moodleoverflow/subscription';

/**
 * Init function
 * @param {string} itemid
 * @param {string} toggleitem
 */
export function init(itemid, toggleitem) {
    switch (toggleitem) {
        case "subscription":
            addSubscriptionListener(itemid);
            break;
        case "readtracking":
            addReadtrackingListener(itemid);
            break;
        default:
            break;
    }
}

/**
 *
 * @param {string} itemid
 */
const addSubscriptionListener = (itemid) => {
    // Get the right subscription toggle element.
    const element = document.getElementById(itemid);
    element.addEventListener('change', async function() {
            // The toggle already shows the target state.
            const subscribe = element.checked;
            try {
                element.checked = await changeSubscription('moodleoverflow', element.dataset.moodleoverflowid, subscribe);
            } catch (error) {
                element.checked = !subscribe;
                Notification.exception(error);
            }
        }
    );
};

/**
 *
 * @param {string} itemid
 */
const addReadtrackingListener = (itemid) => {
    // Get the right readtracking toggle element.
    const element = document.getElementById(itemid);
    element.addEventListener('change', async function() {
            // The toggle already shows the target state.
            const track = element.checked;
            try {
                const path = `moodleoverflow/${element.dataset.moodleoverflowid}/tracking`;
                const result = await request(track ? 'PUT' : 'DELETE', path);
                element.checked = result.tracked;
            } catch (error) {
                element.checked = !track;
                Notification.exception(error);
            }
        }
    );
};
