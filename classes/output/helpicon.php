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
 * Use of the Helpicon from Moodle core.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow\output;

/**
 * Builds a Helpicon, that shows a String when hovering over it.
 * @package   mod_moodleoverflow
 * @copyright 2023 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helpicon {

    /** @var object The Helpicon*/
    private $helpobject;

    /**
     * Builds a Helpicon and stores it in helpobject.
     *
     * @param string $htmlclass     The classname in which the icon will be.
     * @param string $content       A string that shows the information that the icon has.
     */
    public function __construct($htmlclass, $content) {
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

    /**
     * Returns the Helpicon, so that it can be used.
     *
     * @return object The Helpicon
     */
    public function get_helpicon() {
        return $this->helpobject;
    }
}
