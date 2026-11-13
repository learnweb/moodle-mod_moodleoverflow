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
 * Exports the posts of a moodleoverflow that the user can see as a file (CSV, Excel, …), one row per post.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_moodleoverflow\local\models\moodleoverflow;
use mod_moodleoverflow\local\permissions;
use mod_moodleoverflow\local\service;

require_once(__DIR__ . '/../../config.php');
global $PAGE, $OUTPUT, $USER;

$id = required_param('id', PARAM_INT); // Course module id.
$dataformat = optional_param('dataformat', '', PARAM_ALPHA); // The file format; empty: show the format selection.

$PAGE->set_url('/mod/moodleoverflow/export.php', ['id' => $id]);
$modflow = moodleoverflow::from_cmid($id);
require_login($modflow->get_course(), false, $modflow->get_cm());
permissions::ensure(permissions::can_export_forum($modflow, $USER->id), 'nopermissiontoexport');

if ($dataformat !== '') {
    $columns = [];
    foreach (
        ['discussionid', 'postid', 'parentid', 'subject', 'posttype', 'authorname', 'created', 'modified', 'message',
            'wordcount', 'upvotescount', 'downvotescount', 'helpful', 'solved'] as $column
    ) {
        $columns[$column] = get_string('export:' . $column, 'mod_moodleoverflow');
    }
    $name = format_string($modflow->name, true, ['context' => $modflow->get_context(), 'escape' => false]);
    $filename = clean_filename($name . '-' . userdate(time(), '%Y-%m-%d'));

    $rows = service\discussion::export($modflow, $USER->id);
    \core\dataformat::download_data($filename, $dataformat, $columns, $rows, function (array $row) use ($columns): array {
        $row['posttype'] = get_string('export:' . $row['posttype'], 'mod_moodleoverflow');
        $row['created'] = userdate($row['created'], '%Y-%m-%d %H:%M');
        $row['modified'] = userdate($row['modified'], '%Y-%m-%d %H:%M');
        // The values in the order of the columns.
        return array_map(fn(string $column) => $row[$column], array_keys($columns));
    });
    die;
}

$PAGE->set_title(format_string($modflow->name) . ': ' . get_string('exportforum', 'mod_moodleoverflow'));
$PAGE->set_heading(format_string($modflow->get_course()->fullname));
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportforum', 'mod_moodleoverflow'), 3);
echo html_writer::tag('p', get_string('exportforumdesc', 'mod_moodleoverflow'));
// The enabled file formats (dataformat plugins), like core's download_dataformat_selector().
$formats = array_filter(
    core_plugin_manager::instance()->get_plugins_of_type('dataformat'),
    fn($format) => $format->is_enabled()
);
echo $OUTPUT->render_from_template('mod_moodleoverflow/export_form', [
    'action' => (new moodle_url('/mod/moodleoverflow/export.php'))->out(false),
    'cmid' => $id,
    'formats' => array_values(array_map(
        fn($format) => ['value' => $format->name, 'label' => get_string('dataformat', $format->component)],
        $formats
    )),
]);
echo $OUTPUT->footer();
