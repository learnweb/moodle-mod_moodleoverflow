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
 * This file keeps track of upgrades to the moodleoverflow module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute moodleoverflow upgrade from the given old version
 *
 * @param int $oldversion
 *
 * @return bool
 */
function xmldb_moodleoverflow_upgrade($oldversion) {
    global $CFG;

    if ($oldversion < 2017110713) {
        // Migrate config.
        set_config('manydiscussions', $CFG->moodleoverflow_manydiscussions, 'moodleoverflow');
        set_config('maxbytes', $CFG->moodleoverflow_maxbytes, 'moodleoverflow');
        set_config('maxattachments', $CFG->moodleoverflow_maxattachments, 'moodleoverflow');
        set_config('maxeditingtime', $CFG->moodleoverflow_maxeditingtime, 'moodleoverflow');
        set_config('trackingtype', $CFG->moodleoverflow_trackingtype, 'moodleoverflow');
        set_config('trackreadposts', $CFG->moodleoverflow_trackreadposts, 'moodleoverflow');
        set_config('allowforcedreadtracking', $CFG->moodleoverflow_allowforcedreadtracking, 'moodleoverflow');
        set_config('oldpostdays', $CFG->moodleoverflow_oldpostdays, 'moodleoverflow');
        set_config('cleanreadtime', $CFG->moodleoverflow_cleanreadtime, 'moodleoverflow');
        set_config('allowratingchange', $CFG->moodleoverflow_allowratingchange, 'moodleoverflow');
        set_config('votescalevote', $CFG->moodleoverflow_votescalevote, 'moodleoverflow');
        set_config('votescaledownvote', $CFG->moodleoverflow_votescaledownvote, 'moodleoverflow');
        set_config('votescaleupvote', $CFG->moodleoverflow_votescaleupvote, 'moodleoverflow');
        set_config('votescalesolved', $CFG->moodleoverflow_votescalesolved, 'moodleoverflow');
        set_config('votescalehelpful', $CFG->moodleoverflow_votescalehelpful, 'moodleoverflow');
        set_config('maxmailingtime', $CFG->moodleoverflow_maxmailingtime, 'moodleoverflow');

        // Delete old config.
        set_config('moodleoverflow_manydiscussions', null, 'moodleoverflow');
        set_config('moodleoverflow_maxbytes', null, 'moodleoverflow');
        set_config('moodleoverflow_maxattachments', null, 'moodleoverflow');
        set_config('moodleoverflow_maxeditingtime', null, 'moodleoverflow');
        set_config('moodleoverflow_trackingtype', null, 'moodleoverflow');
        set_config('moodleoverflow_trackreadposts', null, 'moodleoverflow');
        set_config('moodleoverflow_allowforcedreadtracking', null, 'moodleoverflow');
        set_config('moodleoverflow_oldpostdays', null, 'moodleoverflow');
        set_config('moodleoverflow_cleanreadtime', null, 'moodleoverflow');
        set_config('moodleoverflow_allowratingchange', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalevote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescaledownvote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescaleupvote', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalesolved', null, 'moodleoverflow');
        set_config('moodleoverflow_votescalehelpful', null, 'moodleoverflow');
        set_config('moodleoverflow_maxmailingtime', null, 'moodleoverflow');

        // Opencast savepoint reached.
        upgrade_mod_savepoint(true, 2017110713, 'moodleoverflow');
    }
    return true;
}
