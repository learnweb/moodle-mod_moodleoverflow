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
 * External moodleoverflow API
 *
 * @package    mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_moodleoverflow_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function record_vote_paramenters() {
        return new external_function_parameters(
            array(
                'vote' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'discussionid' => new external_value(PARAM_INT, 'id of discussion'),
                            'postid' => new external_value(PARAM_INT, 'id of post'),
                            'ratingid' => new external_value(PARAM_INT, 'rating'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Returns the result of the vote (new rating and reputations).
     * @return external_multiple_structure
     */
    public static function record_vote_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'postrating' => new external_value(PARAM_INT, 'new post rating'),
                    'ownerreputation' => new external_value(PARAM_INT, 'new reputation of post owner'),
                    'raterreputation' => new external_value(PARAM_INT, 'new reputation of rater'),
                )
            )
        );
    }
}
