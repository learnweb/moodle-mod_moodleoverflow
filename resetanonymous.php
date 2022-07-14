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
 * Resets all forums anonymity level, if the admin really wants to.
 *
 * @package   mod_moodleoverflow
 * @copyright 2022 Justus Dieckmann WWU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\anonymous;

require_once('../../config.php');

global $DB, $PAGE, $OUTPUT;

$PAGE->set_url('/mod/moodleoverflow/resetanonymous.php');
$PAGE->set_context(context_system::instance());

require_admin();

$confirmed = optional_param('confirmed', false, PARAM_BOOL);

$returnurl = new moodle_url('/admin/settings.php?section=modsettingmoodleoverflow');

if ($confirmed !== 1) {
    $a = new stdClass();
    $a->fullanoncount = $DB->count_records('moodleoverflow',
        ['anonymous' => anonymous::EVERYTHING_ANONYMOUS]);
    $a->questionanoncount = $DB->count_records('moodleoverflow',
        ['anonymous' => anonymous::QUESTION_ANONYMOUS]);
    echo $OUTPUT->header();
    echo html_writer::div(
        $OUTPUT->confirm(get_string('resetanonymous_warning', 'moodleoverflow', $a),
            new moodle_url($PAGE->url, ['confirmed' => true, 'sesskey' => sesskey()]), $returnurl),
        'mod_moodleoverflow-hack-primary-to-danger-btn'
    );
    echo $OUTPUT->footer();
    die();
}

require_sesskey();

$DB->execute('UPDATE {moodleoverflow} SET anonymous = ?', [anonymous::NOT_ANONYMOUS]);

redirect($returnurl);
