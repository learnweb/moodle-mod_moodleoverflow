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
 * Defines the version and other meta-info about the plugin
 *
 * See https://docs.moodle.org/dev/version.php for more info.
 *
 * @package   mod_moodleoverflow
 * @copyright 2025 Thomas Niedermaier, University Münster
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025112702;
$plugin->requires = 2024100700.00; // Require Moodle 4.5.
$plugin->supported = [405, 501];
$plugin->component = 'mod_moodleoverflow';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = 'v5.1-r2';
