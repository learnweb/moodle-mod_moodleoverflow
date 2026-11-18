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
 * Javascript for the readtracking templates. Marks all posts in a discussion or moodleoverflow as read.
 * The element names its target in data-domain ("discussion" or "moodleoverflow") and data-instanceid.
 * @module     mod_moodleoverflow/readtracking
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';
import {request} from 'mod_moodleoverflow/api';

/**
 * Removes the menus of discussion cards that have no item left, e.g. after "Mark as read" was their only item.
 */
const removeEmptyMenus = () => {
    document.querySelectorAll('.discussioncard .dropdown').forEach((menu) => {
        if (!menu.querySelector('.dropdown-item')) {
            menu.remove();
        }
    });
};

/**
 * Init function
 * @param {string} itemid
 */
export function init(itemid) {
    const element = document.getElementById(itemid);
    element.addEventListener('click', async function() {
        try {
            await request('PUT', `${element.dataset.domain}/${element.dataset.instanceid}/read`);
        } catch (error) {
            Notification.exception(error);
            return;
        }

        // "Mark all posts as read" on the view page: everything is read, remove all unread markers and the button.
        if (element.dataset.clearpage) {
            document.querySelectorAll('.unread-part').forEach(part => part.remove());
            // In the menu of the forum the divider after the command goes with it.
            const divider = element.nextElementSibling?.classList.contains('dropdown-divider') ? element.nextElementSibling : null;
            element.remove();
            divider?.remove();
            removeEmptyMenus();
            const message = await getString('markmoodleoverflowreadsuccessful', 'mod_moodleoverflow');
            Notification.addNotification({message: message, type: 'success'});
            return;
        }

        // A discussion or moodleoverflow is read now: remove its unread badges and the command.
        const {domain, instanceid} = element.dataset;
        document.querySelectorAll(
            `[data-region="mod_moodleoverflow/unread-badge"][data-domain="${domain}"][data-instanceid="${instanceid}"]`
        ).forEach(badge => badge.remove());
        // In the course overview the cell of the command shows "-" like a moodleoverflow without unread posts.
        const part = element.closest('[data-mdl-overview-cmid] .unread-part');
        element.remove();
        if (part) {
            part.textContent = '-';
        }
        removeEmptyMenus();
    });
}

/**
 * Switches the read tracking of a moodleoverflow from the menu of its page. The page is loaded again, because every
 * unread marker changes with it.
 * @param {string} itemid
 */
export function initTracking(itemid) {
    const element = document.getElementById(itemid);
    element.addEventListener('click', async function() {
        const pending = new Pending('mod_moodleoverflow/readtracking:tracking');
        try {
            const method = element.dataset.tracked === '1' ? 'DELETE' : 'PUT';
            await request(method, `moodleoverflow/${element.dataset.instanceid}/tracking`);
            window.location.reload();
        } catch (error) {
            Notification.exception(error);
        }
        pending.resolve();
    });
}
