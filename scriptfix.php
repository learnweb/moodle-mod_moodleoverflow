<?php

define('CLI_SCRIPT', true);
require_once('../../config.php');

global $CFG, $DB, $USER;

require_once($CFG->dirroot . '/mod/moodleoverflow/db/upgradelib.php');

$postsupdated = mod_moodleoverflow_move_draftfiles_to_permanent_filearea();

echo "Updated $postsupdated posts!\n";
