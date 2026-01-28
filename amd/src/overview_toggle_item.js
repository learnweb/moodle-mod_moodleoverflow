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

import Ajax from 'core/ajax';

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
    element.addEventListener('change', function() {
            const userid = parseInt(element.dataset.userid);
            const cmid = parseInt(element.dataset.cmid);
            const subscribed = element.dataset.setting === 'true';
            const data = {
                methodname: 'mod_moodleoverflow_change_subscription_mode',
                args: {
                    userid: userid,
                    subscribed: subscribed,
                    cmid: cmid
                },
            };
            element.dataset.setting = Boolean(!subscribed);
            // Call the AJAX function.
            return Ajax.call([data]);
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
    element.addEventListener('change', function() {
            const userid = parseInt(element.dataset.userid);
            const moodleoverflowid = parseInt(element.dataset.moodleoverflowid);
            const tracked = element.dataset.setting === 'true';
            const data = {
                methodname: 'mod_moodleoverflow_change_readtracking_mode',
                args: {
                    userid: userid,
                    tracked: tracked,
                    moodleoverflowid: moodleoverflowid,
                },
            };
            element.dataset.setting = Boolean(!tracked);
            // Call the AJAX function.
            return Ajax.call([data]);
        }
    );
};
