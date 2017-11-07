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
 * Ajax functions for moodleoverflow
 *
 * @module     mod/moodleoverflow
 * @package    mod_moodleoverflow
 * @copyright  2017 Tamara Gunkel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax'], function ($, ajax) {

    var t = {
        /**
         * Records a upvote / downvote.
         * @param int discussionid
         * @param int postid
         * @param int ratingid
         * @param int userid
         * @returns {string}
         */
        recordvote: function (discussionid, postid, ratingid, userid) {

            var vote = ajax.call([{
                methodname: 'mod_moodleoverflow_record_vote',
                args: {discussionid: discussionid, postid: postid, ratingid: ratingid, userid: userid}
            }
            ]);



            vote[0].done(function (response) {
                // eslint-disable-next-line no-console
                console.log(response);

            }.fail(function (ex) {
                // eslint-disable-next-line no-console
                console.log(ex);
            }));

            var out = '';
            for (var i in vote) {
                out += i + ": " + vote[i] + "\n";
            }
            alert("ahlo " +out);

            return vote;
        }
    };

    return t;
});
