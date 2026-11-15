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
 * Calls the REST routes of the plugin.
 *
 * @module     mod_moodleoverflow/api
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/**
 * Sends a request to a route of the plugin. The sesskey goes into the query, the body is sent as JSON. The request
 * is pending JS while it runs, like a core/ajax call, so Behat waits for it.
 *
 * @param {string} method GET, POST, PUT or DELETE
 * @param {string} path The path below /mod_moodleoverflow/, e.g. "post/12/vote"
 * @param {object|null} body The data of the request
 * @returns {Promise<object>} The data of the response
 * @throws {Error} With the message of the server if the request failed
 */
export const request = async(method, path, body = null) => {
    const url = `${M.cfg.apibase}/rest/v2/mod_moodleoverflow/${path}?sesskey=${M.cfg.sesskey}`;
    const options = {method, headers: {Accept: 'application/json'}};
    if (body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    const pending = new Pending(`mod_moodleoverflow/api:${method} ${path}`);
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.message ?? response.statusText);
        }
        return result;
    } finally {
        pending.resolve();
    }
};
