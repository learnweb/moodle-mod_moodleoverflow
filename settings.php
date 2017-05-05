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
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    require_once($CFG->dirroot.'/mod/moodleoverflow/lib.php');

    // Number of discussions per page.
    $settings->add(new admin_setting_configtext('moodleoverflow_manydiscussions', get_string('manydiscussions', 'moodleoverflow'),
        get_string('configmanydiscussions', 'moodleoverflow'), 25, PARAM_INT));

    // Default read tracking settings.
    $options = array();
    $options[MOODLEOVERFLOW_TRACKING_OPTIONAL] = get_string('trackingoptional', 'moodleoverflow');
    $options[MOODLEOVERFLOW_TRACKING_OFF] = get_string('trackingoff', 'moodleoverflow');
    $options[MOODLEOVERFLOW_TRACKING_FORCED] = get_string('trackingon', 'moodleoverflow');
    $settings->add(new admin_setting_configselect('moodleoverflow_trackingtype', get_string('trackingtype', 'moodleoverflow'),
        get_string('configtrackingtype', 'moodleoverflow'), MOODLEOVERFLOW_TRACKING_OPTIONAL, $options));

    // Should unread posts be tracked for each user?
    $settings->add(new admin_setting_configcheckbox('moodleoverflow_trackreadposts',
        get_string('trackmoodleoverflow', 'moodleoverflow'), get_string('configtrackmoodleoverflow', 'moodleoverflow'), 1));

    // Allow moodleoverflows to be set to forced read tracking.
    $settings->add(new admin_setting_configcheckbox('moodleoverflow_allowforcedreadtracking',
        get_string('forcedreadtracking', 'moodleoverflow'), get_string('configforcedreadtracking', 'moodleoverflow'), 0));

    // Default number of days that a post is considered old.
    $settings->add(new admin_setting_configtext('moodleoverflow_oldpostdays', get_string('oldpostdays', 'moodleoverflow'),
        get_string('configoldpostdays', 'moodleoverflow'), 14, PARAM_INT));

    // Default time (hour) to execute 'clean_read_records' cron.
    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d", $i);
    }
    $settings->add(new admin_setting_configselect('moodleoverflow_cleanreadtime', get_string('cleanreadtime', 'moodleoverflow'),
        get_string('configcleanreadtime', 'moodleoverflow'), 2, $options));
}