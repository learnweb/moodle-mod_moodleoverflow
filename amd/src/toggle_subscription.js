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
 * JavaScript to change the subscription status of a user.
 * This module is used in the activity overview page where a user can change the subscription status of a moodleoverflow with
 * a toggle button.
 * @module     mod_moodleoverflow/toggle_subscription
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';


/**
 * Init function
 * @param {string} itemid
 */
export function init(itemid) {
    // Get the right subscription toggle element.
    const element = document.getElementById(itemid);
    element.addEventListener('change', function() {
            const userid = parseInt(element.dataset.userid);
            const cmid = parseInt(element.dataset.cmid);
            const subscribed = element.dataset.subscribed === 'true';
            const data = {
                methodname: 'mod_moodleoverflow_change_subscription_mode',
                args: {
                    userid: userid,
                    subscribed: subscribed,
                    cmid: cmid
                },
            };
            element.dataset.subscribed = Boolean(!subscribed);
            // Call the AJAX function.
            return Ajax.call([data]);
        }
    );
}