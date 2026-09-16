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

namespace mod_moodleoverflow\local\models;

use cm_info;
use coding_exception;
use context_module;
use dml_exception;
use stdClass;
use mod_moodleoverflow\local\enum\rating_preference;
use mod_moodleoverflow\local\enum\review_level;
use mod_moodleoverflow\local\enum\anonymity;
use mod_moodleoverflow\local\enum\subscription_mode;
use mod_moodleoverflow\local\enum\tracking_type;

/**
 * Class that represents a moodleoverflow instance.
 *
 * @package   mod_moodleoverflow
 * @copyright 2026 Tamaro Walter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodleoverflow {
    /** @var ?cm_info Lazy cache for the course module. */
    private ?cm_info $cm = null;

    /** @var string[] Properties that are int(1) db columns, booleans in the model. */
    private const FLAGS = [
        'coursewidereputation', 'allowrating', 'allowreputation', 'allownegativereputation', 'allowmultiplemarks',
    ];

    /**
     * Constructor. The parameters are the columns of the moodleoverflow table, see db/install.xml.
     *
     * @param int $id
     * @param int $course
     * @param string $name
     * @param string $intro
     * @param int $introformat
     * @param int $maxbytes
     * @param int $maxattachments
     * @param int $forcesubscribe
     * @param int $trackingtype
     * @param int $ratingpreference
     * @param bool $coursewidereputation
     * @param bool $allowrating
     * @param bool $allowreputation
     * @param bool $allownegativereputation
     * @param bool $allowmultiplemarks
     * @param int $anonymous
     * @param int $needsreview
     * @param int $la_starttime
     * @param int $la_endtime
     * @param ?int $grademaxgrade
     * @param ?int $gradescalefactor
     * @param ?int $gradepass
     * @param ?int $gradecat
     * @param int $timecreated
     * @param int $timemodified
     */
    public function __construct(
        /** @var int Id of the moodleoverflow instance */
        public readonly int $id,
        /** @var int Id of the course the instance belongs to */
        public readonly int $course,
        /** @var string Name of the instance */
        public readonly string $name,
        /** @var string Description of the instance */
        public readonly string $intro,
        /** @var int Format of the description */
        public readonly int $introformat,
        /** @var int Maximum size of one attachment, 0 means the course or site limit */
        public readonly int $maxbytes,
        /** @var int Maximum number of attachments per post */
        public readonly int $maxattachments,
        /** @var int Subscription mode, see subscription_mode */
        public readonly int $forcesubscribe,
        /** @var int Read tracking type, see tracking_type */
        public readonly int $trackingtype,
        /** @var int Which mark is pinned first, see rating_preference */
        public readonly int $ratingpreference,
        /** @var bool Whether the reputation counts over all instances of the course */
        public readonly bool $coursewidereputation,
        /** @var bool Whether posts can be rated */
        public readonly bool $allowrating,
        /** @var bool Whether the reputation is shown */
        public readonly bool $allowreputation,
        /** @var bool Whether the reputation can become negative */
        public readonly bool $allownegativereputation,
        /** @var bool Whether several answers can be marked as solved or helpful */
        public readonly bool $allowmultiplemarks,
        /** @var int Anonymity setting, see anonymity */
        public readonly int $anonymous,
        /** @var int Review level, see review_level */
        public readonly int $needsreview,
        /** @var int Start of the limited answer window, 0 means not set */
        public readonly int $la_starttime, // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        /** @var int End of the limited answer window, 0 means not set */
        public readonly int $la_endtime, // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUnderscore
        /** @var ?int Maximum grade, null when grading is not configured */
        public readonly ?int $grademaxgrade,
        /** @var ?int Divisor that turns the reputation into a grade */
        public readonly ?int $gradescalefactor,
        /** @var ?int Grade needed to pass */
        public readonly ?int $gradepass,
        /** @var ?int Id of the gradebook category */
        public readonly ?int $gradecat,
        /** @var int Creation timestamp */
        public readonly int $timecreated,
        /** @var int Timestamp of the last change */
        public readonly int $timemodified,
    ) {
    }

    // Construction.
    /**
     * Builds the model from a record of the moodleoverflow table.
     *
     * The database returns every column as a string, so the values are cast to the types of the constructor.
     *
     * @param stdClass $record A full record of the moodleoverflow table.
     * @return self
     * @throws coding_exception If the record has no id.
     */
    public static function from_record(stdClass $record): self {
        if (empty($record->id)) {
            throw new coding_exception('The record of a moodleoverflow needs an id.');
        }

        return new self(
            id: (int) $record->id,
            course: (int) $record->course,
            name: (string) $record->name,
            intro: (string) ($record->intro ?? ''),
            introformat: (int) ($record->introformat ?? FORMAT_MOODLE),
            maxbytes: (int) ($record->maxbytes ?? 0),
            maxattachments: (int) ($record->maxattachments ?? 1),
            forcesubscribe: subscription_mode::from((int) ($record->forcesubscribe ?? subscription_mode::OPTIONAL->value))->value,
            trackingtype: tracking_type::from((int) ($record->trackingtype ?? tracking_type::OPTIONAL->value))->value,
            ratingpreference: rating_preference::from(
                (int) ($record->ratingpreference ?? rating_preference::STARTER->value)
            )->value,
            coursewidereputation: (bool) ($record->coursewidereputation ?? false),
            allowrating: (bool) ($record->allowrating ?? true),
            allowreputation: (bool) ($record->allowreputation ?? true),
            allownegativereputation: (bool) ($record->allownegativereputation ?? false),
            allowmultiplemarks: (bool) ($record->allowmultiplemarks ?? false),
            anonymous: anonymity::from((int) ($record->anonymous ?? 0))->value,
            needsreview: review_level::from((int) ($record->needsreview ?? review_level::NONE->value))->value,
            la_starttime: (int) ($record->la_starttime ?? 0),
            la_endtime: (int) ($record->la_endtime ?? 0),
            grademaxgrade: isset($record->grademaxgrade) ? (int) $record->grademaxgrade : null,
            gradescalefactor: isset($record->gradescalefactor) ? (int) $record->gradescalefactor : null,
            gradepass: isset($record->gradepass) ? (int) $record->gradepass : null,
            gradecat: isset($record->gradecat) ? (int) $record->gradecat : null,
            timecreated: (int) ($record->timecreated ?? 0),
            timemodified: (int) ($record->timemodified ?? 0),
        );
    }

    /**
     * Construct moodleoverflow instance from moodleoverflow id.
     * @param int $id
     * @return self
     */
    public static function from_id(int $id): self {
        global $DB;
        return self::from_record($DB->get_record('moodleoverflow', ['id' => $id], '*', MUST_EXIST));
    }

    /**
     * Construct instance from coursemodule id.
     * @param int $cmid
     * @return self
     */
    public static function from_cmid(int $cmid): self {
        return self::from_id(get_coursemodule_from_id('moodleoverflow', $cmid, 0, false, MUST_EXIST)->instance);
    }

    /**
     * Exports the moodleoverflow to a db object.
     * @return stdClass
     */
    public function build_db_object(): stdClass {
        $record = get_object_vars($this);
        unset($record['cm']);
        foreach (self::FLAGS as $flag) {
            $record[$flag] = (int) $record[$flag];
        }
        return (object) $record;
    }

    // Place in Moodle.

    /**
     * Returns the course module of this moodleoverflow.
     *
     * cm_info carries more than the course module database record: the context ($cm->context), the visibility for the current
     * user ($cm->uservisible) and the name. Note that $cm->id is the course module id, while $cm->instance is the
     * id of this moodleoverflow. Code that needs the plain record uses $cm->get_course_module_record().
     *
     * @return cm_info
     */
    public function get_cm(): cm_info {
        return $this->cm ??= get_fast_modinfo($this->course)->get_instances_of('moodleoverflow')[$this->id];
    }

    /**
     * Get the module context.
     * @return context_module
     */
    public function get_context(): context_module {
        return context_module::instance($this->get_cm()->id);
    }

    /**
     * Get the course the moodleoverflow belongs to. Uses core function that caches courses to reduce DB calls.
     * @return stdClass
     * @throws dml_exception
     */
    public function get_course(): stdClass {
        return get_course($this->course);
    }

    // Posting.

    /**
     * Start and end of the limited answer window, null when not set.
     * @return array{0: ?int, 1: ?int}
     */
    public function get_answer_window(): array {
        return [$this->la_starttime ?: null, $this->la_endtime ?: null];
    }

    /**
     * Whether answers are allowed at the given time.
     * @param ?int $now Timestamp to check, defaults to the current time.
     * @return bool
     */
    public function is_answer_window_open(?int $now = null): bool {
        $now ??= time();
        [$start, $end] = $this->get_answer_window();
        return !(($start && $start > $now) || ($end && $end < $now));
    }

    /**
     * Time in seconds in which an author can still edit their post.
     * @return int
     */
    public function get_edit_window(): int {
        return (int) get_config('moodleoverflow', 'maxeditingtime');
    }

    /**
     * Configured attachment limits. The effective size per user is resolved by the file API.
     * @return array{maxattachments: int, maxbytes: int}
     */
    public function get_attachment_limits(): array {
        return ['maxattachments' => $this->maxattachments, 'maxbytes' => $this->maxbytes];
    }

    // Review.

    /**
     * Effective review level: the instance setting, unless the admin disallows reviewing.
     * @return review_level
     */
    public function get_review_level(): review_level {
        return get_config('moodleoverflow', 'allowreview') == '1'
            ? review_level::from($this->needsreview)
            : review_level::NONE;
    }

    /**
     * Whether a new post has to be reviewed before it becomes visible.
     * Replaces review::should_post_be_reviewed().
     *
     * @param bool $isstarter Whether the post starts a discussion.
     * @return bool
     */
    public function requires_review(bool $isstarter): bool {
        return $this->get_review_level()->requires_review($isstarter);
    }

    /**
     * Time in seconds that has to pass before a post can be reviewed.
     * @return int
     */
    public function get_review_delay(): int {
        return (int) get_config('moodleoverflow', 'reviewpossibleaftertime');
    }

    // Anonymity: only what the setting says, no capabilities.

    /**
     * Anonymity setting of the instance. Capabilities are not considered here.
     * @return anonymity
     */
    public function get_anonymity(): anonymity {
        return anonymity::from($this->anonymous);
    }

    /**
     * Whether the author of a post is hidden by the anonymity setting.
     * @param bool $isquestioner Whether the author also started the discussion.
     * @return bool
     */
    public function is_author_anonymous(bool $isquestioner): bool {
        return $this->get_anonymity()->hides_author($isquestioner);
    }

    // Ratings and reputation.

    /**
     * Whether posts can be rated. The instance setting only counts if the admin allows disabling.
     * @return bool
     */
    public function is_rating_enabled(): bool {
        return $this->can_disable_rating() ? $this->allowrating : true;
    }

    /**
     * Whether the reputation is shown. The instance setting only counts if the admin allows disabling.
     * @return bool
     */
    public function is_reputation_enabled(): bool {
        return $this->can_disable_rating() ? $this->allowreputation : true;
    }

    /**
     * Which mark is pinned first in a discussion: the helpful mark of the question author or a teacher's solution.
     * @return rating_preference
     */
    public function get_rating_preference(): rating_preference {
        return rating_preference::from($this->ratingpreference);
    }

    /**
     * Whether the reputation is counted over all moodleoverflows of the course.
     * @return bool
     */
    public function is_reputation_course_wide(): bool {
        return $this->coursewidereputation;
    }

    /**
     * Whether the reputation of a user can become negative.
     * @return bool
     */
    public function allows_negative_reputation(): bool {
        return $this->allownegativereputation;
    }

    /**
     * Whether several answers can be marked as solved or helpful.
     * @return bool
     */
    public function allows_multiple_marks(): bool {
        return $this->allowmultiplemarks;
    }

    /**
     * Whether the admin allows teachers to disable rating and reputation.
     * @return bool
     */
    private function can_disable_rating(): bool {
        return get_config('moodleoverflow', 'allowdisablerating') == 1;
    }

    // Subscriptions and tracking.

    /**
     * Subscription mode of the instance.
     * @return subscription_mode
     */
    public function get_subscription_mode(): subscription_mode {
        return subscription_mode::from($this->forcesubscribe);
    }

    /**
     * Effective read tracking type: the instance setting, limited by trackreadposts and allowforcedreadtracking.
     * @return tracking_type
     */
    public function get_tracking_type(): tracking_type {
        if (!get_config('moodleoverflow', 'trackreadposts')) {
            return tracking_type::OFF;
        }
        $type = tracking_type::from($this->trackingtype);
        if ($type === tracking_type::FORCED && !get_config('moodleoverflow', 'allowforcedreadtracking')) {
            // Forced is configured, but the admin disallows forcing.
            return tracking_type::OPTIONAL;
        }
        return $type;
    }

    // Grading.

    /**
     * Whether the votes of this instance are transferred into the gradebook.
     * @return bool
     */
    public function is_graded(): bool {
        return $this->grademaxgrade > 0 && $this->gradescalefactor > 0;
    }
}
