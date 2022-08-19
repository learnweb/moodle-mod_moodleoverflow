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
 * Implements reviewing functionality
 *
 * @module     mod_moodleoverflow/reviewing
 * @copyright  2022 Justus Dieckmann WWU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import Prefetch from 'core/prefetch';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';

/**
 * Init function.
 */
export function init() {
    Prefetch.prefetchTemplates(['mod_moodleoverflow/reject_post_form', 'mod_moodleoverflow/review_buttons']);
    Prefetch.prefetchStrings('mod_moodleoverflow',
        ['post_was_approved', 'jump_to_next_post_needing_review', 'there_are_no_posts_needing_review', 'post_was_rejected']);

    const root = document.getElementById('moodleoverflow-posts');
    root.onclick = async(e) => {
        const action = e.target.getAttribute('data-moodleoverflow-action');

        if (!action) {
            return;
        }

        const post = e.target.closest('*[data-moodleoverflow-postid]');
        const reviewRow = e.target.closest('.reviewrow');
        const postID = post.getAttribute('data-moodleoverflow-postid');

        if (action === 'approve') {
            reviewRow.innerHTML = '.';
            const nextPostURL = await Ajax.call([{
                methodname: 'mod_moodleoverflow_review_approve_post',
                args: {
                    postid: postID,
                }
            }])[0];

            let message = await getString('post_was_approved', 'mod_moodleoverflow') + ' ';
            if (nextPostURL) {
                message += `<a href="${nextPostURL}">`
                    + await getString('jump_to_next_post_needing_review', 'mod_moodleoverflow')
                    + "</a>";
            } else {
                message += await getString('there_are_no_posts_needing_review', 'mod_moodleoverflow');
            }
            reviewRow.innerHTML = message;
            post.classList.remove("pendingreview");
        } else if (action === 'reject') {
            reviewRow.innerHTML = '.';
            reviewRow.innerHTML = await Templates.render('mod_moodleoverflow/reject_post_form', {});
        } else if (action === 'reject-submit') {
            const rejectMessage = post.querySelector('textarea.reject-reason').value.toString().trim();
            reviewRow.innerHTML = '.';
            const args = {
                postid: postID,
                reason: rejectMessage ? rejectMessage : null
            };
            const nextPostURL = await Ajax.call([{
                methodname: 'mod_moodleoverflow_review_reject_post',
                args: args
            }])[0];

            let message = await getString('post_was_rejected', 'mod_moodleoverflow') + ' ';
            if (nextPostURL) {
                message += `<a href="${nextPostURL}">`
                    + await getString('jump_to_next_post_needing_review', 'mod_moodleoverflow')
                    + "</a>";
            } else {
                message += await getString('there_are_no_posts_needing_review', 'mod_moodleoverflow');
            }
            reviewRow.innerHTML = message;
        } else if (action === 'reject-cancel') {
            reviewRow.innerHTML = '.';
            reviewRow.innerHTML = await Templates.render('mod_moodleoverflow/review_buttons', {});
        }
    };
}