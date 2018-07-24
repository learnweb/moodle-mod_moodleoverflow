<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/report_form.php');
require_once(__DIR__ . '/locallib.php');

// get params
$cmid = required_param('id', PARAM_INT);

// require login
$cm = get_coursemodule_from_id('moodleoverflow', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

// require viewreports capability
$context = context_module::instance($cm->id);

require_capability('mod/moodleoverflow:viewreports', $context);

// set $PAGE
$title = get_string('gradesreport','moodleoverflow');

$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_title($title);
$PAGE->set_heading($context->get_context_name());
$PAGE->set_url('/mod/moodleoverflow/report.php', array('id'=>$cm->id));

// append the discussion name to the navigation.
$forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($forumnode)) {
    $forumnode = $PAGE->navbar;
} else {
    $forumnode->make_active();
}

// get renderer
$renderer = $PAGE->get_renderer('mod_moodleoverflow');

// get form
$form = new mod_moodleoverflow_report_form(null, array('id' => $cmid));

// process
if($form->get_data()->update_grades){

	moodleoverflow_update_all_grades($cm);

	$notify_update = true;
}

// print page
echo $OUTPUT->header();
echo $OUTPUT->heading($title,2);

$form->display();

if($notify_update){

	echo $renderer->notification(get_string('gradesupdated','mod_moodleoverflow'), 'notifysucces');
}

echo $OUTPUT->footer();

?>