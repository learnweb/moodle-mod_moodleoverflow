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

namespace mod_moodleoverflow\local\enum;

/**
 * Typical uses of a moodleoverflow. A preset only fills the settings form of an instance without discussions; it is not
 * stored, so a teacher can change every setting afterwards and a later change of a preset does not touch instances.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
enum preset: string {
    // Questions about a lecture: names visible, votes and course-wide reputation.
    case LECTURE = 'lecture';

    // Anonymous questions, e.g. before an exam: whoever asks stays hidden in the whole discussion, the others are visible.
    case ANONYMOUS = 'anonymous';

    // Anonymous feedback on the course: everybody is hidden, votes show agreement, no reputation.
    case FEEDBACK = 'feedback';

    // Moderated forum: every post is approved before the other participants see it.
    case MODERATED = 'moderated';

    /**
     * The values this preset sets, by the name of the form field. Fields that the site settings remove from the form
     * (e.g. anonymity when it is not allowed) are skipped by the form.
     * @return array<string, int|string>
     */
    public function get_settings(): array {
        return match ($this) {
            self::LECTURE => [
                'anonymous' => anonymity::NONE->value,
                'needsreview' => review_level::NONE->value,
                'allowrating' => 1,
                'allowreputation' => 1,
                'coursewidereputation' => 1,
            ],
            self::ANONYMOUS => [
                'anonymous' => anonymity::QUESTIONS->value,
                'needsreview' => review_level::NONE->value,
                'allowrating' => 1,
                'allowreputation' => 1,
            ],
            self::FEEDBACK => [
                'anonymous' => anonymity::EVERYTHING->value,
                'needsreview' => review_level::NONE->value,
                'allowrating' => 1,
                'allowreputation' => 0,
            ],
            self::MODERATED => [
                'anonymous' => anonymity::NONE->value,
                'needsreview' => review_level::EVERYTHING->value,
                'allowrating' => 1,
                'allowreputation' => 1,
                'coursewidereputation' => 1,
            ],
        };
    }

    /**
     * The name shown in the settings form.
     * @return string
     */
    public function get_name(): string {
        return get_string('preset:' . $this->value, 'moodleoverflow');
    }

    /**
     * What the preset is for and what it sets, shown below the choice.
     * @return string
     */
    public function get_description(): string {
        return get_string('preset:' . $this->value . '_desc', 'moodleoverflow');
    }
}
