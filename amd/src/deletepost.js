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

import Notification, {deleteCancelPromise} from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';

/**
 * Deletes a post after a confirmation dialog and continues at the returned page.
 *
 * @module     mod_moodleoverflow/deletepost
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Init function.
 */
export const init = () => {
    document.addEventListener('click', async(e) => {
        const button = e.target.closest('[data-action="mod_moodleoverflow/delete-post"]');
        if (!button) {
            return;
        }
        e.preventDefault();

        const replies = Number(button.dataset.replies);
        const question = replies > 0
            ? getString('deletesureplural', 'mod_moodleoverflow', replies + 1)
            : getString('deletesure', 'mod_moodleoverflow');
        try {
            await deleteCancelPromise(getString('delete', 'mod_moodleoverflow'), question, getString('delete', 'core'),
                {triggerElement: button});
        } catch {
            // Cancelled.
            return;
        }

        const pending = new Pending('mod_moodleoverflow/deletepost');
        try {
            const url = `${M.cfg.apibase}/rest/v2/mod_moodleoverflow/post/${button.dataset.postid}` +
                `?sesskey=${M.cfg.sesskey}`;
            const response = await fetch(url, {method: 'DELETE', headers: {Accept: 'application/json'}});
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.message ?? response.statusText);
            }
            window.location.href = result.redirecturl;
        } catch (error) {
            Notification.exception(error);
        }
        pending.resolve();
    });
};