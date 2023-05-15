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
import Prefetch from 'core/prefetch';
import {get_string as getString} from 'core/str';

const RATING_DOWNVOTE = 1;
const RATING_UPVOTE = 2;
const RATING_REMOVE_DOWNVOTE = 10;
const RATING_REMOVE_UPVOTE = 20;
const RATING_SOLVED = 3;
const RATING_HELPFUL = 4;

const root = document.getElementById('moodleoverflow-root');

/**
 * Send a vote via AJAX, then updates post and user ratings.
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
        i.textContent = response.raterreputation;
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
 * @param {boolean} allowmultiplemarks   // true means allowed, false means not allowed.
 *
 */
export function init(userid, allowmultiplemarks) {
    Prefetch.prefetchStrings('mod_moodleoverflow',
        ['marksolved', 'marknotsolved', 'markhelpful', 'marknothelpful',
            'action_remove_upvote', 'action_upvote', 'action_remove_downvote', 'action_downvote']);

    root.onclick = async(event) => {
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
                    await sendVote(postid, isupvote ? RATING_REMOVE_UPVOTE : RATING_REMOVE_DOWNVOTE, userid);
                    actionElement.setAttribute('data-moodleoverflow-state', 'notclicked');
                    actionElement.title = await getString('action_' + action, 'mod_moodleoverflow');
                } else {
                    const otherAction = isupvote ? 'downvote' : 'upvote';
                    await sendVote(postid, isupvote ? RATING_UPVOTE : RATING_DOWNVOTE, userid);
                    actionElement.setAttribute('data-moodleoverflow-state', 'clicked');
                    const otherElement = postElement.querySelector(
                        `[data-moodleoverflow-action="${otherAction}"]`);
                    otherElement.setAttribute('data-moodleoverflow-state', 'notclicked');
                    actionElement.title = await getString('action_remove_' + action, 'mod_moodleoverflow');
                    otherElement.title = await getString('action_' + otherAction, 'mod_moodleoverflow');
                }
            }
            break;
            case 'helpful':
            case 'solved': {
                const isHelpful = action === 'helpful';
                const htmlclass = isHelpful ? 'statusstarter' : 'statusteacher';
                const shouldRemove = postElement.classList.contains(htmlclass);
                const baseRating = isHelpful ? RATING_HELPFUL : RATING_SOLVED;
                const rating = shouldRemove ? baseRating * 10 : baseRating;
                await sendVote(postid, rating, userid);

                /* If       multiplemarks are not allowed (that is the default mode): delete all marks.
                   else:    only delete the mark if the post is being unmarked.

                   Add a mark, if the post is being marked.
                */
                if (!allowmultiplemarks) {
                    // Delete all marks in the discussion
                    for (const el of root.querySelectorAll('.moodleoverflowpost.' + htmlclass)) {
                        el.classList.remove(htmlclass);
                        el.querySelector(`[data-moodleoverflow-action="${action}"]`).textContent =
                            await getString(`mark${action}`, 'mod_moodleoverflow');
                    }
                } else {
                    // Remove only the mark of the unmarked post.
                    if (shouldRemove) {
                        postElement.classList.remove(htmlclass);
                        actionElement.textContent = await getString(`mark${action}`, 'mod_moodleoverflow');
                        changeStrings(htmlclass, action);
                    }
                }
                // If the post is being marked, mark it.
                if (!shouldRemove) {
                    postElement.classList.add(htmlclass);
                    actionElement.textContent = await getString(`marknot${action}`, 'mod_moodleoverflow');
                    if (allowmultiplemarks) {
                        changeStrings(htmlclass, action);
                    }
                }

            }
        }
    };

}

/**
 * Function to change the String of the post data-action button.
 * Only used if mulitplemarks are allowed.
 * @param {string} htmlclass the class where the String is being updated
 * @param {string} action    helpful or solved mark
 */
async function changeStrings(htmlclass, action) {
    Prefetch.prefetchStrings('mod_moodleoverflow',
        ['marksolved', 'alsomarksolved', 'markhelpful', 'alsomarkhelpful',]);

    // 1. Step: Are there other posts in the Discussion, that are solved/helpful?
    var othermarkedposts = false;
    for (const el of root.querySelectorAll('.moodleoverflowpost')) {
        if (el.classList.contains(htmlclass)) {
            othermarkedposts = true;
            break;
        }
    }
    // 2. Step: Change the strings of the action Button of the unmarked posts.
    for (const el of root.querySelectorAll('.moodleoverflowpost')) {
        if (!el.classList.contains(htmlclass) && el.querySelector(`[data-moodleoverflow-action="${action}"]`)) {
            if (othermarkedposts) {
                el.querySelector(`[data-moodleoverflow-action="${action}"]`).textContent =
                    await getString(`alsomark${action}`, 'mod_moodleoverflow');
            } else {
                el.querySelector(`[data-moodleoverflow-action="${action}"]`).textContent =
                    await getString(`mark${action}`, 'mod_moodleoverflow');
            }
        }
    }
}