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
 * Implements rating functionality
 *
 * @module     mod_moodleoverflow/rating
 * @copyright  2022 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
// Import Prefetch from 'core/prefetch';
// import Templates from 'core/templates';
// import {get_string as getString} from 'core/str';

const RATING_DOWNVOTE = 1;
const RATING_UPVOTE = 2;
const RATING_REMOVE_DOWNVOTE = 10;
const RATING_REMOVE_UPVOTE = 20;

const root = document.getElementById('moodleoverflow-root');

/**
 * Send a vote via AJAX
 * @param {int} postid
 * @param {int} rating
 * @param {int} userid
 * @returns {Promise<*>}
 */
async function sendVote(postid, rating, userid) {
    const response = await Ajax.call([{
        methodname: 'mod_moodleoverflow_record_vote',
        args: {
            postid: postid,
            ratingid: rating
        }
    }])[0];
    root.querySelectorAll(`[data-moodleoverflow-userreputation="${userid}"]`).forEach((i) => {
        i.textContent = response.raterrepuation;
    });
    root.querySelectorAll(`[data-moodleoverflow-userreputation="${response.ownerid}"]`).forEach((i) => {
        i.textContent = response.ownerreputation;
    });
    root.querySelectorAll(`[data-moodleoverflow-postreputation="${postid}"]`).forEach((i) => {
        i.textContent = response.postrating;
    });
    return response;
}


/**
 * Init function.
 *
 * @param {int} userid
 */
export function init(userid) {
    root.onclick = (event) => {
        const actionElement = event.target.closest('[data-moodleoverflow-action]');
        if (!actionElement) {
            return;
        }

        const action = actionElement.getAttribute('data-moodleoverflow-action');
        const postElement = actionElement.closest('[data-moodleoverflow-postid]');
        const postid = postElement?.getAttribute('data-moodleoverflow-postid');

        switch (action) {
            case 'upvote':
            case 'downvote': {
                const isupvote = action === 'upvote';
                if (actionElement.getAttribute('data-moodleoverflow-state') === 'clicked') {
                    sendVote(postid, isupvote ? RATING_REMOVE_UPVOTE : RATING_REMOVE_DOWNVOTE, userid);
                    actionElement.setAttribute('data-moodleoverflow-state', 'notclicked');
                } else {
                    sendVote(postid, isupvote ? RATING_UPVOTE : RATING_DOWNVOTE, userid);
                    actionElement.setAttribute('data-moodleoverflow-state', 'clicked');
                    const otherElement = postElement.querySelector(
                        `[data-moodleoverflow-action="${(isupvote ? 'downvote' : 'upvote')}"]`);
                    otherElement.setAttribute('data-moodleoverflow-state', 'notclicked');
                }
            }
        }
    };

}