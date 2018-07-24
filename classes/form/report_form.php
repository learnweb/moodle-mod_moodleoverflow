<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class mod_moodleoverflow_report_form extends moodleform {

	public function definition(){

		global $CFG;

		$mform = $this->_form;

		// Hidden
		$mform->addElement('hidden', 'id', $this->_customdata['id']);

        // Buttons.
		$mform->addElement('submit', 'update_grades', get_string('updategrades','moodleoverflow'));
	}
}