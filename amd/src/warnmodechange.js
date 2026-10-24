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
 * Warns on changing the subscription mode.
 *
 * @module     mod_moodleoverflow/warnmodechange
 * @copyright  2022 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import Prefetch from 'core/prefetch';

/**
 * Init function.
 * @param {string} previousSetting
 */
export function init(previousSetting) {
    Prefetch.prefetchStrings('mod_moodleoverflow', ['changesubscriptionmode']);
    Prefetch.prefetchStrings('moodle', ['confirm', 'cancel']);
    const form = document.querySelector('form.mform');
    const select = document.getElementById('id_forcesubscribe');
    form.onsubmit = async(e) => {
        // Every change of the mode resets the subscription choices of all users.
        if (select.selectedOptions[0].value == previousSetting) {
            return;
        }

        e.preventDefault();
        await Notification.confirm(
            await getString('confirm'),
            await getString('changesubscriptionmode', 'mod_moodleoverflow'),
            await getString('confirm'),
            await getString('cancel'),
            () => {
                // Prevent this listener from preventing the event again.
                form.onsubmit = undefined;
                form.requestSubmit(e.submitter);
            }, undefined);
    };
}