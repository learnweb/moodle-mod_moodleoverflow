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
 * Implements rating functionality: votes and solved / helpful marks. The server decides the new state, this module
 * only shows it.
 *
 * @module     mod_moodleoverflow/rating
 * @copyright  2022 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Prefetch from 'core/prefetch';
import {get_string as getString} from 'core/str';

/** The rating of each action, the values of the enum rating_type. */
const RATINGS = {downvote: 1, upvote: 2, solved: 3, helpful: 4};

/** The CSS class of a post that has a mark. */
const MARKCLASSES = {solved: 'markedsolution', helpful: 'markedhelpful'};

const root = document.getElementById('moodleoverflow-root');

/**
 * Shows the new vote count of the post and the new reputations of the rater and the author.
 * @param {string} postid
 * @param {int} userid The rater.
 * @param {object} response The result of mod_moodleoverflow_record_vote.
 */
function showReputations(postid, userid, response) {
    const show = (selector, value) => root.querySelectorAll(selector).forEach((element) => {
        element.textContent = value;
    });
    show(`[data-moodleoverflow-postreputation="${postid}"]`, response.postrating);
    show(`[data-moodleoverflow-userreputation="${userid}"]`, response.raterreputation);
    if (response.ownerid !== null && response.ownerreputation !== null) {
        show(`[data-moodleoverflow-userreputation="${response.ownerid}"]`, response.ownerreputation);
    }
}

/**
 * Shows the vote of the user on both vote buttons of a post, like the template postvoting does.
 * @param {HTMLElement} postElement
 * @param {int} uservote The rating of the vote, 0 if the user has not voted.
 * @param {boolean} canchange Whether the user can still change the vote. If not, the buttons stay visible but
 *                            cannot be clicked any more.
 */
async function showVote(postElement, uservote, canchange) {
    for (const action of ['upvote', 'downvote']) {
        const button = postElement.querySelector(`[data-moodleoverflow-action="${action}"]`);
        const clicked = uservote === RATINGS[action];
        if (canchange) {
            button.setAttribute('data-moodleoverflow-state', clicked ? 'clicked' : 'notclicked');
            button.title = await getString(clicked ? `action_remove_${action}` : `action_${action}`, 'mod_moodleoverflow');
        } else {
            button.removeAttribute('data-moodleoverflow-action');
            button.setAttribute('data-moodleoverflow-state', clicked ? 'clicked' : 'disabled');
            button.title = await getString(`${action}notchangeable`, 'mod_moodleoverflow');
        }
    }
}

/**
 * Shows which posts have a mark: the CSS class of every post and the label of its mark button.
 * @param {string} action solved or helpful
 * @param {int[]} markedposts The ids of the posts with the mark.
 * @param {boolean} allowmultiplemarks Whether several posts can have the mark.
 */
async function showMarks(action, markedposts, allowmultiplemarks) {
    for (const postElement of root.querySelectorAll('[data-moodleoverflow-postid]')) {
        const marked = markedposts.includes(Number(postElement.getAttribute('data-moodleoverflow-postid')));
        postElement.classList.toggle(MARKCLASSES[action], marked);
        const button = postElement.querySelector(`[data-moodleoverflow-action="${action}"]`);
        if (button) {
            // Without multiple marks, marking another post moves the mark, so there is no "also mark".
            const other = allowmultiplemarks && markedposts.length ? 'alsomark' : 'mark';
            button.textContent = await getString(marked ? `marknot${action}` : `${other}${action}`, 'mod_moodleoverflow');
        }
    }
}

/**
 * Init function.
 *
 * @param {int} userid
 * @param {boolean} allowmultiplemarks Whether several posts of a discussion can have the same mark.
 */
export function init(userid, allowmultiplemarks) {
    Prefetch.prefetchStrings('mod_moodleoverflow', [
        'action_upvote', 'action_remove_upvote', 'action_downvote', 'action_remove_downvote',
        'upvotenotchangeable', 'downvotenotchangeable',
        'marksolved', 'marknotsolved', 'alsomarksolved', 'markhelpful', 'marknothelpful', 'alsomarkhelpful',
    ]);

    root.addEventListener('click', async(event) => {
        const button = event.target.closest('[data-moodleoverflow-action]');
        const action = button?.getAttribute('data-moodleoverflow-action');
        if (!RATINGS[action]) {
            return;
        }
        const postElement = button.closest('[data-moodleoverflow-postid]');
        const postid = postElement.getAttribute('data-moodleoverflow-postid');
        const ismark = action in MARKCLASSES;
        const remove = ismark ? postElement.classList.contains(MARKCLASSES[action])
            : button.getAttribute('data-moodleoverflow-state') === 'clicked';

        try {
            const response = await Ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {postid: postid, rating: RATINGS[action], remove: remove},
            }])[0];
            showReputations(postid, userid, response);
            if (ismark) {
                await showMarks(action, response.markedposts, allowmultiplemarks);
            } else {
                await showVote(postElement, response.uservote, response.canchange);
            }
        } catch (error) {
            Notification.exception(error);
        }
    });
}
