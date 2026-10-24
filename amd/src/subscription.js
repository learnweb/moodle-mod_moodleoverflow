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

import Notification, {saveCancelPromise} from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';
import {add as addToast} from 'core/toast';

/**
 * Subscribes to and unsubscribes from moodleoverflows and discussions through the REST routes.
 *
 * A toggle button (template mod_moodleoverflow/subscription_toggle) names its target in data-type ("moodleoverflow" or
 * "discussion") and data-id, and shows the current state in data-subscribed.
 *
 * @module     mod_moodleoverflow/subscription
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTOR = '[data-action="mod_moodleoverflow/toggle-subscription"]';

/**
 * Sends the target state to the route. PUT subscribes, DELETE unsubscribes.
 *
 * @param {string} type "moodleoverflow" or "discussion"
 * @param {number|string} id The id of the moodleoverflow or discussion
 * @param {boolean} subscribe The target state
 * @returns {Promise<boolean>} Whether the user is subscribed now
 */
export const changeSubscription = async(type, id, subscribe) => {
    const url = `${M.cfg.apibase}/rest/v2/mod_moodleoverflow/${type}/${id}/subscription?sesskey=${M.cfg.sesskey}`;
    const response = await fetch(url, {method: subscribe ? 'PUT' : 'DELETE', headers: {Accept: 'application/json'}});
    const result = await response.json();
    if (!response.ok) {
        throw new Error(result.message ?? response.statusText);
    }
    return result.subscribed;
};

/**
 * Shows the state on a toggle button.
 *
 * @param {HTMLElement} button
 * @param {boolean} subscribed
 */
const showState = (button, subscribed) => {
    button.dataset.subscribed = subscribed ? '1' : '0';
    button.querySelectorAll('[data-state]').forEach((element) => {
        element.classList.toggle('d-none', (element.dataset.state === 'subscribed') !== subscribed);
    });
};

/**
 * Shows a changed subscription on every toggle of the page. A choice for the whole moodleoverflow replaces the
 * choices for its discussions, so their toggles follow it (a page only shows discussions of one moodleoverflow).
 *
 * @param {string} type "moodleoverflow" or "discussion"
 * @param {number|string} id
 * @param {boolean} subscribed
 */
const showNewState = (type, id, subscribed) => {
    document.querySelectorAll(`${SELECTOR}[data-type="${type}"][data-id="${id}"]`).forEach((button) => {
        showState(button, subscribed);
    });
    if (type === 'moodleoverflow') {
        document.querySelectorAll(`${SELECTOR}[data-type="discussion"]`).forEach((button) => {
            showState(button, subscribed);
        });
    }
};

/** @var {boolean} initialised Whether the click listener is registered. Every rendered toggle calls init(). */
let initialised = false;

/**
 * Init function.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('click', async(e) => {
        const button = e.target.closest(SELECTOR);
        if (!button || button.disabled) {
            return;
        }
        e.preventDefault();

        const pending = new Pending('mod_moodleoverflow/subscription');
        button.disabled = true;
        try {
            const {type, id} = button.dataset;
            showNewState(type, id, await changeSubscription(type, id, button.dataset.subscribed !== '1'));
        } catch (error) {
            Notification.exception(error);
        }
        button.disabled = false;
        pending.resolve();
    });
};

/**
 * Asks to unsubscribe after the user followed the unsubscribe link of a mail. The link itself changes nothing.
 *
 * @param {string} type "moodleoverflow" or "discussion"
 * @param {number} id The id of the moodleoverflow or discussion
 * @param {string} question The confirmation question
 */
export const confirmUnsubscribe = async(type, id, question) => {
    // Reloading the page must not ask again.
    const url = new URL(window.location.href);
    url.searchParams.delete('unsubscribe');
    window.history.replaceState(null, '', url);

    const title = getString(type === 'discussion' ? 'unsubscribediscussion' : 'unsubscribe', 'mod_moodleoverflow');
    try {
        await saveCancelPromise(title, question, getString('confirm', 'core'));
    } catch {
        // Cancelled.
        return;
    }

    const pending = new Pending('mod_moodleoverflow/subscription');
    try {
        showNewState(type, id, await changeSubscription(type, id, false));
        addToast(await getString('unsubscribed', 'mod_moodleoverflow'));
    } catch (error) {
        Notification.exception(error);
    }
    pending.resolve();
};
