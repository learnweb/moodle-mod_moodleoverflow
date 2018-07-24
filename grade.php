<?php

require_once("../../config.php");

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('moodleoverflow', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

$PAGE->set_url('/mod/moodleoverflow/grade.php', array('id'=>$cm->id));

if (has_capability('mod/moodleoverflow:viewreports', context_module::instance($cm->id))) {
    redirect('report.php?id='.$cm->id);
} else {
    redirect('view.php?id='.$cm->id);
}
