<?php
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
 *
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds a Helpicon, that shows a String when hovering over it.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helpicon {

    /** @var object The Helpicon*/
    private $helpobject;

    function __construct($htmlclass, $content) {
        global $CFG;
        $iconurl = $CFG->wwwroot . '/pix/a/help.png';
        $icon = \html_writer::img($iconurl, $content);
        $class = $htmlclass;
        $iconattributes = array('role' => 'button',
                                    'data-container' => 'body',
                                    'data-toggle' => 'popover',
                                    'data-placement' => 'right',
                                    'data-action' => 'showhelpicon',
                                    'data-html' => 'true',
                                    'data-trigger' => 'focus',
                                    'tabindex' => '0',
                                    'data-content' => '<div class=&quot;no-overflow&quot;><p>' . $content . '</p> </div>');
        $this->helpobject = \html_writer::span($icon, $class, $iconattributes);
    }

    function get_helpicon() {
        return $this->helpobject;
    }
}