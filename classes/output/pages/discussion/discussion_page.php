<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_moodleoverflow\output\pages\discussion;

use context_module;
use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\models\discussion;
use mod_moodleoverflow\models\post;
use mod_moodleoverflow\readtracking;

/**
 * This class presents the output of the discussion page (discussion.php). The discussion page shows all discussion
 * post by rendering a "post_card" for each one.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_page implements named_templatable, renderable{
    /** @var discussion The discussion that gets printed */
    public discussion $discussion;


    /** @var post The first post of the discussion */
    public post $firstpost;

    /** @var post[] The answer posts of the discussion */
    public array $answers;


    /**
     * Constructor.
     * @param discussion $discussion
     */
    public function __construct(discussion $discussion) {
        $this->discussion = $discussion;
        $this->firstpost = $this->discussion->get_first_post();
        $this->answers = $this->discussion->get_answerposts();
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/discussion/discussion_page';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER, $OUTPUT;
        $context = context_module::instance($this->discussion->get_coursemodule()->id);
        $firstunreadpost = readtracking::get_first_unread_post_id($this->discussion->get_id(), $USER->id, $context);
        $answeramount = get_string(((count($this->answers) > 0) ? 'answers' : 'answer'), 'moodleoverflow', count($this->answers));

        // Get the rendered posts.
        $firstpost = $OUTPUT->render(new post_card($this->firstpost, $this->firstpost->get_id() == $firstunreadpost, $context));

        // Organize the answer posts as comment and answers are mixed and need to be categorized correctly.
        $answersorganized = $this->answers;
        $firstpostid = $this->firstpost->get_id();
        // Sort direct answers by date. Put comments of an answer after the answer.
        usort($answersorganized, function ($a, $b) use ($answersorganized, $firstpostid) {
            $aparent = $a->get_parentid() == $firstpostid ? $a : ($answersorganized[$a->get_parentid()] ?? $a);
            $bparent = $b->get_parentid() == $firstpostid ? $b : ($answersorganized[$b->get_parentid()] ?? $b);
            return $aparent->created !== $bparent->created ? $aparent->created <=> $bparent->created : $a->created <=> $b->created;
        });

        $answers = array_map(
            fn($post) => $OUTPUT->render(new post_card($post, $post->get_id() == $firstunreadpost, $context)),
            $answersorganized
        );

        return (object) [
            'answeramount' => $answeramount,
            'firstpost' => $firstpost,
            'answers' => $answers,
        ];
    }
}
