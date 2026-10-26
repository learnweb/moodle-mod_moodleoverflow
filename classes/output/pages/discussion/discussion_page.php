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

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;
use mod_moodleoverflow\local\models\discussion;
use mod_moodleoverflow\local\models\post;
use mod_moodleoverflow\local\service;

/**
 * This class presents the output of the discussion page (discussion.php). The discussion page shows all discussion
 * post by rendering a "post_card" for each one.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_page implements named_templatable, renderable {
    /** @var discussion The discussion that gets printed */
    public discussion $discussion;

    /**
     * Constructor.
     * @param discussion $discussion
     */
    public function __construct(discussion $discussion) {
        $this->discussion = $discussion;
    }

    #[\Override]
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_moodleoverflow/pages/discussion/discussion_page';
    }

    #[\Override]
    public function export_for_template(renderer_base $output): object {
        global $USER;
        $ratings = service\rating::get_post_ratings($this->discussion);
        $tree = service\discussion::get_posts_tree($this->discussion, $USER->id, $ratings);
        $data = new discussion_page_data($this->discussion, $USER->id, $ratings);
        $render = fn(post $post) => $output->render(new post_card($post, $data));

        // The template lists the answers flat; a comment is indented by its post card.
        $answers = [];
        foreach ($tree['answers'] as $branch) {
            $answers[] = $render($branch['answer']);
            array_push($answers, ...array_map($render, $branch['comments']));
        }

        return (object) [
            'answeramount' => get_string(count($answers) > 0 ? 'answers' : 'answer', 'moodleoverflow', count($answers)),
            'firstpost' => $render($tree['question']),
            'answers' => $answers,
        ];
    }
}
