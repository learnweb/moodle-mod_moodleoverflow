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
 * Show a help string for the amount of activity column in userstats_table.php
 *
 * @module     mod_moodleoverflow/activityhelp
 * @copyright  2023 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function that shows the help string.
 */
export function init () {
    const icon = document.getElementById('helpactivityclass');
    icon.onclick = async(e) => {
        alert('clicked');
        window.console.log(e.target);
    };
}

/**
 * doc
 */
/*define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/config', 'core/url', 'core/str'],
    function($) {
        var t = {
            /**
             * doc

            clickevent: function() {
                $("helpactivityclass").on("click", function(event) {
                    alert('clicked');
                    window.console.log(event.target);
                    t.nothingevent();
                });
            },

            nothingevent: function() {

            }
        };
    }
)*/