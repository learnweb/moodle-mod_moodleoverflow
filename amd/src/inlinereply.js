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

import AutoRows from 'core/auto_rows';
import Notification from 'core/notification';
import Pending from 'core/pending';
import Templates from 'core/templates';

/**
 * The small reply form of the discussion page: "Answer" and "Comment" open a plain text form below their post, which
 * posts through the REST route post/{postid}/reply. "Advanced" is a normal submit to post.php with the text.
 * Ctrl/Cmd+Enter posts, Escape cancels.
 *
 * @module     mod_moodleoverflow/inlinereply
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const Selectors = {
    reply: '[data-action="mod_moodleoverflow/reply"]',
    form: '[data-region="mod_moodleoverflow/inline-reply"]',
    submit: '[data-action="mod_moodleoverflow/inline-reply-submit"]',
    cancel: '[data-action="mod_moodleoverflow/inline-reply-cancel"]',
};

/**
 * Opens the page of a post. A url that differs from the current page only by its hash does not load the page, so the
 * page is loaded again; without scroll restoration the browser then jumps to the post.
 * @param {String} url
 */
const openPost = (url) => {
    const target = new URL(url);
    const samepage = target.pathname === window.location.pathname && target.search === window.location.search;
    window.location.href = target.href;
    if (samepage) {
        window.history.scrollRestoration = 'manual';
        window.location.reload();
    }
};

/**
 * Opens the form below the post of a reply command; a form that is open there already gets the focus.
 * @param {HTMLElement} command The "Answer" or "Comment" link
 */
const openForm = async(command) => {
    const card = command.closest('.moodleoverflowpost');
    let form = card.nextElementSibling;
    if (!form?.matches(Selectors.form)) {
        const pending = new Pending('mod_moodleoverflow/inlinereply:open');
        const isquestion = card.classList.contains('firstpost');
        const {html, js} = await Templates.renderForPromise('mod_moodleoverflow/inline_reply', {
            postid: command.dataset.postid,
            posturl: `${M.cfg.wwwroot}/mod/moodleoverflow/post.php`,
            isanswer: isquestion,
            // Below an answer the form stands where the comment will be; a comment card is indented already.
            indent: !isquestion && !card.classList.contains('moodleoverflowcomment'),
        });
        card.insertAdjacentHTML('afterend', html);
        Templates.runTemplateJS(js);
        form = card.nextElementSibling;
        // Fade in: Bootstrap's "fade" needs one layout before "show".
        form.getBoundingClientRect();
        form.classList.add('show');
        pending.resolve();
    }
    form.querySelector('textarea').focus();
};

/**
 * Closes a form and gives the focus back to the reply command of its post.
 * @param {HTMLFormElement} form
 */
const closeForm = (form) => {
    const command = form.previousElementSibling?.querySelector(Selectors.reply);
    form.remove();
    command?.focus();
};

/**
 * Posts the message of a form and opens the new post.
 * @param {HTMLFormElement} form
 */
const submitForm = async(form) => {
    const message = form.querySelector('textarea').value.trim();
    if (!message) {
        return;
    }
    const buttons = form.querySelectorAll('button');
    buttons.forEach((button) => {
        button.disabled = true;
    });
    const pending = new Pending('mod_moodleoverflow/inlinereply:submit');
    try {
        const url = `${M.cfg.apibase}/rest/v2/mod_moodleoverflow/post/${form.dataset.postid}/reply` +
            `?sesskey=${M.cfg.sesskey}`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
            body: JSON.stringify({message}),
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.message ?? response.statusText);
        }
        openPost(result.url);
    } catch (error) {
        buttons.forEach((button) => {
            button.disabled = false;
        });
        Notification.exception(error);
    }
    pending.resolve();
};

/**
 * Init function.
 */
export const init = () => {
    // The textareas grow with their text (data-auto-rows).
    AutoRows.init(document.body);

    document.addEventListener('click', (e) => {
        const command = e.target.closest(Selectors.reply);
        if (command) {
            e.preventDefault();
            openForm(command).catch(Notification.exception);
            return;
        }
        const form = e.target.closest(Selectors.form);
        if (form && e.target.closest(Selectors.submit)) {
            submitForm(form);
        } else if (form && e.target.closest(Selectors.cancel)) {
            closeForm(form);
        }
    });

    // "Post" can only be pressed with a message.
    document.addEventListener('input', (e) => {
        const form = e.target.closest(Selectors.form);
        if (form) {
            form.querySelector(Selectors.submit).disabled = form.querySelector('textarea').value.trim() === '';
        }
    });

    document.addEventListener('keydown', (e) => {
        const form = e.target.closest(Selectors.form);
        if (!form) {
            return;
        }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            submitForm(form);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeForm(form);
        }
    });
};
