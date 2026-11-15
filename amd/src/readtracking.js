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
import {getString} from 'core/str';
import {request} from 'mod_moodleoverflow/api';

/**
 * Init function
 * @param {string} itemid
 */
export function init(itemid) {
    const element = document.getElementById(itemid);
    element.addEventListener('click', async function() {
        let result;
        try {
            result = await request('PUT', `${element.dataset.domain}/${element.dataset.instanceid}/read`);
        } catch (error) {
            Notification.exception(error);
            return;
        }

        // "Mark all posts as read" on the view page: everything is read, remove all unread markers and the button.
        if (element.dataset.clearpage) {
            document.querySelectorAll('.unread-part').forEach(part => part.remove());
            element.remove();
            const message = await getString('markmoodleoverflowreadsuccessful', 'mod_moodleoverflow');
            Notification.addNotification({message: message, type: 'success'});
            return;
        }

        // Update the red bubble icon with the new amount of unread posts.
        const bubble = element.nextElementSibling?.querySelector('.unread-bubble');
        if (bubble) {
            bubble.textContent = String(result.unread);
        }
    });
}
