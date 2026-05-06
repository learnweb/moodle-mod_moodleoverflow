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

import Ajax from 'core/ajax';
import {getString} from "core/str";
import Notification from 'core/notification';

/**
 * JavaScript to move discussion to another moodleoverflow.
 *
 * @module     block_townsquare/topicmove
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let selectedDiscussionId = null;
let selectedDiscussionName = null;

/**
 * Init function
 */
export async function init() {
    document.addEventListener('click', async e => {
        const topicmoveList = document.querySelector('.topicmove-root');

        // Open list.
        const discussion = e.target.closest('[data-action="moodleoverflow/movetopic-select"]');
        if (discussion) {
            // Speichere die ID und den Namen
            selectedDiscussionId = Number(discussion.dataset.discussionid);
            selectedDiscussionName = discussion.dataset.discussionname;
            const content = await getString('wheremovetopic', 'moodleoverflow', selectedDiscussionName);
            topicmoveList.querySelector('.topicmove-origin').textContent = content;
            topicmoveList.classList.remove('d-none');
        }

        // Close list.
        const closeBtn = e.target.closest('[data-action="moodleoverflow/movetopic-close"]');
        if (closeBtn) {
            topicmoveList.classList.add('d-none');
            selectedDiscussionId = null;
            selectedDiscussionName = null;
        }

        // Execute movement.
        const execute = e.target.closest('[data-action="moodleoverflow/movetopic-execute"]');
        if (execute) {
            const data = {
                methodname: 'mod_moodleoverflow_move_discussion',
                args: {
                    discussionid: selectedDiscussionId,
                    moodleoverflowid: Number(execute.dataset.destination),
                },
            };
            const result = await Ajax.call([data])[0];
            if (result) {
                const message = await getString('topicmove_success', 'mod_moodleoverflow', execute.dataset.destinationname);
                Notification.addNotification({message: message, type: 'success'});
                topicmoveList.classList.add('d-none');
                document.querySelector(`[data-discussionid="${selectedDiscussionId}"]`).closest('.discussioncard').remove();
                selectedDiscussionId = null;
                selectedDiscussionName = null;
            }
        }
    });
}