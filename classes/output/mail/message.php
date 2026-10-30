<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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

namespace mod_moodleoverflow\output\mail;

use core\output\templatable;
use moodle_url;

/**
 * A mail of the plugin to one recipient: what service\mail::send() needs to send it through the message API.
 *
 * Every kind of mail (notification, digest, ...) implements this interface, so one function sends all of them without
 * knowing which kind of mail it exactly is. Each kind prepares its own data and renders its own template in templates/mail.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface message extends templatable {
    /**
     * Returns the subject of the mail.
     * @return string
     */
    public function get_subject(): string;

    /**
     * Returns the page the mail is about, linked from the notification in Moodle.
     * @return moodle_url
     */
    public function get_url(): moodle_url;

    /**
     * Returns the course the mail belongs to.
     * @return int
     */
    public function get_courseid(): int;

    /**
     * Renders the plain text of the mail.
     * @return string
     */
    public function render_text(): string;
}
