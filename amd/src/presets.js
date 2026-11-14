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
 * Fills the settings form of a new moodleoverflow with the values of a preset and shows what the preset is for.
 *
 * @module     mod_moodleoverflow/presets
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Sets one form field and tells the form about it, so hideIf / disabledIf react as if the user changed it.
 *
 * @param {HTMLFormElement} form
 * @param {string} name Name of the form field.
 * @param {number} value
 */
const setField = (form, name, value) => {
    const radios = form.querySelectorAll(`input[type="radio"][name="${name}"]`);
    if (radios.length) {
        const radio = [...radios].find((option) => option.value === String(value));
        if (radio) {
            radio.checked = true;
            radio.dispatchEvent(new Event('change', {bubbles: true}));
        }
        return;
    }
    const checkbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
    const field = checkbox ?? form.querySelector(`select[name="${name}"], input[type="text"][name="${name}"]`);
    if (!field) {
        // The site settings removed this field from the form.
        return;
    }
    if (checkbox) {
        checkbox.checked = Boolean(value);
    } else if (field.tagName === 'SELECT' && !field.querySelector(`option[value="${value}"]`)) {
        // The value is not offered here (e.g. a lower anonymity than allowed).
        return;
    } else {
        field.value = String(value);
    }
    field.dispatchEvent(new Event('change', {bubbles: true}));
};

/**
 * Init function.
 *
 * @param {Object<string, Object<string, number>>} presets The settings of each preset, by preset key.
 */
export const init = (presets) => {
    const select = document.getElementById('id_preset');
    if (!select) {
        return;
    }
    const form = select.form;
    const descriptions = form.querySelectorAll('[data-region="mod_moodleoverflow-presets"] [data-preset]');

    select.addEventListener('change', () => {
        descriptions.forEach((description) => {
            description.classList.toggle('d-none', description.dataset.preset !== select.value);
        });
        Object.entries(presets[select.value] ?? {}).forEach(([name, value]) => setField(form, name, value));
    });
};
