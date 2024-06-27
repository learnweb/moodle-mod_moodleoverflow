<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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
 * The moodleoverflow ratings manager.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_moodleoverflow;
use moodle_exception;

/**
 * Static methods for managing the ratings of posts.
 *
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ratings {

    /**
     * Add a rating.
     * This is the basic function to add or edit ratings.
     *
     * @param object $moodleoverflow
     * @param int    $postid
     * @param int    $rating
     * @param object $cm
     * @param int   $userid
     *
     * @return bool|int
     */
    public static function moodleoverflow_add_rating($moodleoverflow, $postid, $rating, $cm, $userid) {
        global $DB;

        // Is the submitted rating valid?
        $possibleratings = [RATING_NEUTRAL, RATING_DOWNVOTE, RATING_UPVOTE, RATING_SOLVED,
            RATING_HELPFUL, RATING_REMOVE_DOWNVOTE, RATING_REMOVE_UPVOTE,
            RATING_REMOVE_SOLVED, RATING_REMOVE_HELPFUL, ];
        moodleoverflow_throw_exception_with_check(!in_array($rating, $possibleratings), 'invalidratingid');

        // Get the related post.
        $post = moodleoverflow_get_record_or_exception('moodleoverflow_posts', ['id' => $postid], 'invalidparentpostid');

        // Check if the post belongs to a discussion.
        $discussion = moodleoverflow_get_record_or_exception('moodleoverflow_discussions', ['id' => $post->discussion],
                                                   'notpartofdiscussion');

        // Get the related course.
        $course = moodleoverflow_get_record_or_exception('course', ['id' => $moodleoverflow->course],
                                                         'invalidcourseid', '*', true);

        // Are multiple marks allowed?
        $markssetting = $DB->get_record('moodleoverflow', ['id' => $moodleoverflow->id], 'allowmultiplemarks');
        $multiplemarks = (bool) $markssetting->allowmultiplemarks;

        // Retrieve the contexts.
        $modulecontext = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);

        // Redirect the user if capabilities are missing.
        if (!self::moodleoverflow_user_can_rate($post, $modulecontext, $userid)) {

            // Catch unenrolled users.
            $returnurl = '/mod/moodleoverflow/view.php?m' . $moodleoverflow->id;
            moodleoverflow_catch_unenrolled_user($coursecontext, $course->id, $returnurl);

            // Notify the user, that he can not post a new discussion.
            throw new moodle_exception('noratemoodleoverflow', 'moodleoverflow');
        }

        // Make sure post author != current user, unless they have permission.
        $authorcheck = ($post->userid == $userid) && ! (($rating == RATING_SOLVED || $rating == RATING_REMOVE_SOLVED) &&
                                                        has_capability('mod/moodleoverflow:marksolved', $modulecontext));
        moodleoverflow_throw_exception_with_check($authorcheck, 'rateownpost');

        // Check if we are removing a mark.
        if (in_array($rating / 10, $possibleratings)) {
            moodleoverflow_get_config_or_exception('moodleoverflow', 'allowratingchange',
                                                   'noratingchangeallowed', 'moodleoverflow');

            // Delete the rating.
            return self::moodleoverflow_remove_rating($postid, $rating / 10, $userid, $modulecontext);
        }

        // Check for an older rating in this discussion.
        $oldrating = self::moodleoverflow_check_old_rating($postid, $userid);

        // Mark a post as solution or as helpful.
        if ($rating == RATING_SOLVED || $rating == RATING_HELPFUL) {

            // Make sure that a helpful mark is made by the user who started the discussion.
            $isnotstartuser = $rating == RATING_HELPFUL && $userid != $discussion->userid;
            moodleoverflow_throw_exception_with_check($isnotstartuser, 'nostartuser');

            // Make sure that a solution mark is made by a teacher (or someone with the right capability).
            $isnotteacher = $rating == RATING_SOLVED && !has_capability('mod/moodleoverflow:marksolved', $modulecontext);
            moodleoverflow_throw_exception_with_check($isnotteacher, 'notteacher');

            // Check if multiple marks are not enabled.
            if (!$multiplemarks) {

                // Get other ratings in the discussion.
                $sql = "SELECT *
                        FROM {moodleoverflow_ratings}
                        WHERE discussionid = ? AND rating = ?";
                $otherrating = $DB->get_record_sql($sql, [ $discussion->id, $rating ]);

                // If there is an old rating, update it. Else create a new rating record.
                if ($otherrating) {
                    return self::moodleoverflow_update_rating_record($post->id, $rating, $userid, $otherrating->id, $modulecontext);

                } else {
                    return self::moodleoverflow_add_rating_record($moodleoverflow->id, $discussion->id, $post->id,
                                                                  $rating, $userid, $modulecontext);
                }
            } else {
                // If multiplemarks are allowed, only create a new rating.
                return self::moodleoverflow_add_rating_record($moodleoverflow->id, $discussion->id, $post->id,
                                                              $rating, $userid, $modulecontext);
            }
        }

        // Update an rating record.
        if ($oldrating['normal']) {
            moodleoverflow_get_config_or_exception('moodleoverflow', 'allowratingchange',
                                                   'noratingchangeallowed', 'moodleoverflow');

            // Check if the rating can still be changed.
            if (!self::moodleoverflow_can_be_changed($postid, $oldrating['normal']->rating, $userid)) {
                return false;
            }

            // Update the rating record.
            return self::moodleoverflow_update_rating_record($post->id, $rating, $userid, $oldrating['normal']->id, $modulecontext);
        }

        // Create a new rating record.
        return self::moodleoverflow_add_rating_record($moodleoverflow->id, $post->discussion, $postid,
                                                      $rating, $userid, $modulecontext);
    }

    /**
     * Get the reputation of a user.
     * Whether within a course or an instance is decided by the settings.
     *
     * @param int  $moodleoverflowid
     * @param int $userid
     * @param bool $forcesinglerating If true you only get the reputation for the given $moodleoverflowid,
     * even if coursewidereputation = true
     *
     * @return int
     */
    public static function moodleoverflow_get_reputation($moodleoverflowid, $userid, $forcesinglerating = false) {
        // Check the moodleoverflow instance.
        $moodleoverflow = moodleoverflow_get_record_or_exception('moodleoverflow', ['id' => $moodleoverflowid],
                                                                 'invalidmoodleoverflowid');

        // Check whether the reputation can be summed over the whole course.
        if ($moodleoverflow->coursewidereputation && !$forcesinglerating) {
            return self::moodleoverflow_get_reputation_course($moodleoverflow->course, $userid);
        }

        // Else return the reputation within this instance.
        return self::moodleoverflow_get_reputation_instance($moodleoverflow->id, $userid);
    }

    /**
     * Sort the answers of a discussion by their marks, votes and for equal votes by time modified.
     *
     * @param array $posts all the posts from a discussion.
     */
    public static function moodleoverflow_sort_answers_by_ratings($posts) {
        // Create a copy that only has the answer posts and save the parent post.
        $answerposts = $posts;
        $parentpost = array_shift($answerposts);

        // Create an empty array for the sorted posts and add the parent post.
        $sortedposts = [];
        $sortedposts[0] = $parentpost;

        // Check if solved posts are preferred over helpful posts.
        $solutionspreferred = false;
        if ($posts[array_key_first($posts)]->ratingpreference == 1) {
            $solutionspreferred = true;
        }
        // Build array groups for different types of answers (solved and helpful, only solved/helpful, unmarked).
        $solvedhelpfulposts = [];
        $solvedposts = [];
        $helpfulposts = [];
        $unmarkedposts = [];

        // Sort the answer posts by ratings..
        // markedsolved == 1 means the post is marked as solved.
        // markedhelpful == 1 means the post is marked as helpful.
        // Step 1: Iterate trough the answerposts and assign each post to a group.
        foreach ($answerposts as $post) {
            if ($post->markedsolution > 0) {
                if ($post->markedhelpful > 0) {
                    $solvedhelpfulposts[] = $post;
                } else {
                    $solvedposts[] = $post;
                }
            } else {
                if ($post->markedhelpful > 0) {
                    $helpfulposts[] = $post;
                } else {
                    $unmarkedposts[] = $post;
                }
            }
        }

        // Step 2: Sort each group after their votes and eventually time modified.
        self::moodleoverflow_sort_postgroup($solvedhelpfulposts, 0, count($solvedhelpfulposts) - 1);
        self::moodleoverflow_sort_postgroup($solvedposts, 0, count($solvedposts) - 1);
        self::moodleoverflow_sort_postgroup($helpfulposts, 0, count($helpfulposts) - 1);
        self::moodleoverflow_sort_postgroup($unmarkedposts, 0, count($unmarkedposts) - 1);

        // Step 3: Put each group together in the right order depending on the rating preferences.
        $temp = $solutionspreferred ? array_merge($solvedposts, $helpfulposts) : array_merge($helpfulposts, $solvedposts);
        $sortedposts = array_merge($sortedposts, $solvedhelpfulposts, $temp, $unmarkedposts);

        // Rearrange the indices and return the sorted posts.
        $neworder = [];
        foreach ($sortedposts as $post) {
            $neworder[$post->id] = $post;
        }

        // Return now the sorted posts.
        return $neworder;
    }

    /**
     * Did the current user rated the post?
     *
     * @param int  $postid
     * @param null $userid
     *
     * @return mixed
     */
    public static function moodleoverflow_user_rated($postid, $userid = null) {
        global $DB, $USER;

        // Is a user submitted?
        if (!$userid) {
            $userid = $USER->id;
        }

        // Get the rating.
        $sql = "SELECT firstrated, rating
                  FROM {moodleoverflow_ratings}
                 WHERE userid = ? AND postid = ? AND (rating = 1 OR rating = 2)";

        return ($DB->get_record_sql($sql, [ $userid, $postid ]));
    }

    /**
     * Get the rating of a single post.
     *
     * @param int $postid
     *
     * @return array
     */
    public static function moodleoverflow_get_rating($postid) {
        global $DB;

        // Retrieve the full post.
        if (!$post = $DB->get_record('moodleoverflow_posts', ['id' => $postid])) {
            throw new moodle_exception('postnotexist', 'moodleoverflow');
        }

        // Get the rating for this single post.
        return self::moodleoverflow_get_ratings_by_discussion($post->discussion, $postid);
    }

    /**
     * Get the ratings of all posts in a discussion.
     *
     * @param int  $discussionid
     * @param null $postid
     *
     * @return array
     */
    public static function moodleoverflow_get_ratings_by_discussion($discussionid, $postid = null) {
        global $DB;

        // Get the amount of votes.
        $sql = "SELECT id as postid,
                       (SELECT COUNT(rating) FROM {moodleoverflow_ratings} WHERE postid=p.id AND rating = 1) AS downvotes,
	                   (SELECT COUNT(rating) FROM {moodleoverflow_ratings} WHERE postid=p.id AND rating = 2) AS upvotes,
                       (SELECT COUNT(rating) FROM {moodleoverflow_ratings} WHERE postid=p.id AND rating = 3) AS issolved,
                       (SELECT COUNT(rating) FROM {moodleoverflow_ratings} WHERE postid=p.id AND rating = 4) AS ishelpful
                  FROM {moodleoverflow_posts} p
                 WHERE p.discussion = ?
              GROUP BY p.id";
        $votes = $DB->get_records_sql($sql, [ $discussionid ]);

        // A single post is requested.
        if ($postid) {

            // Check if the post is part of the discussion.
            if (array_key_exists($postid, $votes)) {
                return $votes[$postid];
            }

            // The requested post is not part of the discussion.
            throw new moodle_exception('postnotpartofdiscussion', 'moodleoverflow');
        }

        // Return the array.
        return $votes;
    }

    /**
     * Check if a discussion is marked as solved or helpful.
     *
     * @param int  $discussionid
     * @param bool $teacher
     *
     * @return bool|mixed
     */
    public static function moodleoverflow_discussion_is_solved($discussionid, $teacher = false) {
        global $DB;

        // Is the teachers solved-status requested?
        if ($teacher) {

            // Check if a teacher marked a solution as solved.
            if ($DB->record_exists('moodleoverflow_ratings', ['discussionid' => $discussionid, 'rating' => 3])) {

                // Return the rating records.
                return $DB->get_records('moodleoverflow_ratings', ['discussionid' => $discussionid, 'rating' => 3]);
            }

            // The teacher has not marked the discussion as solved.
            return false;
        }

        // Check if the topic starter marked a solution as helpful.
        if ($DB->record_exists('moodleoverflow_ratings', ['discussionid' => $discussionid, 'rating' => 4])) {

            // Return the rating records.
            return $DB->get_records('moodleoverflow_ratings', ['discussionid' => $discussionid, 'rating' => 4]);
        }

        // The topic starter has not marked a solution as helpful.
        return false;
    }

    /**
     * Get the reputation of a user within a single instance.
     *
     * @param int  $moodleoverflowid
     * @param null $userid
     *
     * @return int
     */
    public static function moodleoverflow_get_reputation_instance($moodleoverflowid, $userid = null) {
        global $DB, $USER;

        // Get the user id.
        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Check the moodleoverflow instance.
        $moodleoverflow = moodleoverflow_get_record_or_exception('moodleoverflow', ['id' => $moodleoverflowid],
                                                                 'invalidmoodleoverflowid');
        // Initiate a variable.
        $reputation = 0;

        // Get all posts of this user in this module.
        // Do not count votes for own posts.
        $sql = "SELECT r.id, r.postid as post, r.rating
              FROM {moodleoverflow_posts} p
              JOIN {moodleoverflow_ratings} r ON p.id = r.postid
              JOIN {moodleoverflow} m ON r.moodleoverflowid = m.id
             WHERE p.userid = ? AND NOT r.userid = ? AND r.moodleoverflowid = ? AND m.anonymous <> ?";

        if ($moodleoverflow->anonymous == anonymous::QUESTION_ANONYMOUS) {
            $sql .= " AND p.parent <> 0 ";
        }

        $sql .= "ORDER BY r.postid ASC";

        $params = [$userid, $userid, $moodleoverflowid, anonymous::EVERYTHING_ANONYMOUS];
        $records = $DB->get_records_sql($sql, $params);

        // Iterate through all ratings.
        foreach ($records as $record) {
            switch ($record->rating) {
                case RATING_DOWNVOTE:
                    $reputation += get_config('moodleoverflow', 'votescaledownvote');
                    break;
                case RATING_UPVOTE:
                    $reputation += get_config('moodleoverflow', 'votescaleupvote');
                    break;
                case RATING_HELPFUL:
                    $reputation += get_config('moodleoverflow', 'votescalehelpful');
                    break;
                case RATING_SOLVED:
                    $reputation += get_config('moodleoverflow', 'votescalesolved');
                    break;
            }
        }

        // Get votes this user made.
        // Votes for own posts are not counting.
        $sql = "SELECT COUNT(id) as amount
                FROM {moodleoverflow_ratings}
                WHERE userid = ? AND moodleoverflowid = ? AND (rating = 1 OR rating = 2)";
        $params = [$userid, $moodleoverflowid];
        $votes = $DB->get_record_sql($sql, $params);

        // Add reputation for the votes.
        $reputation += get_config('moodleoverflow', 'votescalevote') * $votes->amount;

        // Can the reputation of a user be negative?
        if (!$moodleoverflow->allownegativereputation && $reputation <= 0) {
            $reputation = 0;
        }

        // Return the rating of the user.
        return $reputation;
    }

    /**
     * Get the reputation of a user within a course.
     *
     * @param int  $courseid
     * @param null $userid
     *
     * @return int
     */
    public static function moodleoverflow_get_reputation_course($courseid, $userid = null) {
        global $USER, $DB;

        // Get the userid.
        if (!isset($userid)) {
            $userid = $USER->id;
        }

        // Initiate a variable.
        $reputation = 0;

        // Check if the course exists.
        $course = moodleoverflow_get_record_or_exception('course', ['id' => $courseid], 'invalidcourseid', '*', true);

        // Get all moodleoverflow instances in this course.
        $sql = "SELECT id
                  FROM {moodleoverflow}
                 WHERE course = ?
                   AND coursewidereputation = 1";
        $params = [$course->id];
        $instances = $DB->get_records_sql($sql, $params);

        // Sum the reputation of each individual instance.
        foreach ($instances as $instance) {
            $reputation += self::moodleoverflow_get_reputation_instance($instance->id, $userid);
        }

        // The result does not need to be corrected.
        return $reputation;
    }

    /**
     * Check for all old rating records from a user for a specific post.
     *
     * @param int  $postid
     * @param int  $userid
     * @param null $oldrating
     *
     * @return array|mixed
     */
    private static function moodleoverflow_check_old_rating($postid, $userid, $oldrating = null) {
        global $DB;

        // Initiate the array.
        $rating = [];

        $sql = "SELECT *
                FROM {moodleoverflow_ratings}";
        // Get the normal rating.
        $condition = "WHERE userid = ? AND postid = ? AND (rating = 1 OR rating = 2)";
        $rating['normal'] = $DB->get_record_sql($sql . $condition, [ $userid, $postid ]);

        // Return the rating if it is requested.
        if ($oldrating == RATING_DOWNVOTE || $oldrating == RATING_UPVOTE) {
            return $rating['normal'];
        }

        // Get the solved rating.
        $condition = "WHERE postid = ? AND rating = 3";
        $rating['solved'] = $DB->get_record_sql($sql . $condition, [ $postid ]);

        // Return the rating if it is requested.
        if ($oldrating == RATING_SOLVED) {
            return $rating['solved'];
        }

        // Get the helpful rating.
        $condition = "WHERE postid = ? AND rating = 4";
        $rating['helpful'] = $DB->get_record_sql($sql . $condition, [ $postid ]);

        // Return the rating if it is requested.
        if ($oldrating == RATING_HELPFUL) {
            return $rating['helpful'];
        }

        // Return all ratings.
        return $rating;
    }

    /**
     * Check if the rating can be changed.
     *
     * @param int $postid
     * @param int $rating
     * @param int $userid
     *
     * @return bool
     */
    private static function moodleoverflow_can_be_changed($postid, $rating, $userid) {
        // Check if the old read record exists.
        $old = self::moodleoverflow_check_old_rating($postid, $userid, $rating);
        if (!$old) {
            return false;
        }

        return true;
    }

    /**
     * Removes a rating record.
     * @param int             $postid
     * @param int             $rating
     * @param int             $userid
     * @param \context_module $modulecontext
     *
     * @return bool
     */
    private static function moodleoverflow_remove_rating($postid, $rating, $userid, $modulecontext) {
        global $DB;

        // Check if the post can be removed.
        if (!self::moodleoverflow_can_be_changed($postid, $rating, $userid)) {
            return false;
        }

        // Get the old rating record.
        $oldrecord = self::moodleoverflow_check_old_rating($postid, $userid, $rating);

        // Trigger an event.
        $event = \mod_moodleoverflow\event\rating_deleted::create(['objectid' => $oldrecord->id, 'context' => $modulecontext]);
        $event->add_record_snapshot('moodleoverflow_ratings', $oldrecord);
        $event->trigger();

        // Remove the rating record.
        return $DB->delete_records('moodleoverflow_ratings', ['id' => $oldrecord->id]);
    }

    /**
     * Add a new rating record.
     *
     * @param int             $moodleoverflowid
     * @param int             $discussionid
     * @param int             $postid
     * @param int             $rating
     * @param int             $userid
     * @param \context_module $mod
     *
     * @return bool|int
     */
    private static function moodleoverflow_add_rating_record($moodleoverflowid, $discussionid, $postid, $rating, $userid, $mod) {
        global $DB;

        // Create the rating record.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->postid = $postid;
        $record->discussionid = $discussionid;
        $record->moodleoverflowid = $moodleoverflowid;
        $record->rating = $rating;
        $record->firstrated = time();
        $record->lastchanged = time();

        // Add the record to the database.
        $recordid = $DB->insert_record('moodleoverflow_ratings', $record);

        // Trigger an event.
        $params = [
            'objectid' => $recordid,
            'context' => $mod,
        ];
        $event = \mod_moodleoverflow\event\rating_created::create($params);
        $event->trigger();

        // Add the record to the database.
        return $recordid;
    }

    /**
     * Update an existing rating record.
     *
     * @param int             $postid
     * @param int             $rating
     * @param int             $userid
     * @param int             $ratingid
     * @param \context_module $modulecontext
     *
     * @return bool
     */
    private static function moodleoverflow_update_rating_record($postid, $rating, $userid, $ratingid, $modulecontext) {
        global $DB;

        // Update the record.
        $sql = "UPDATE {moodleoverflow_ratings}
                   SET postid = ?, userid = ?, rating=?, lastchanged = ?
                 WHERE id = ?";

        // Trigger an event.
        $params = [
            'objectid' => $ratingid,
            'context' => $modulecontext,
        ];
        $event = \mod_moodleoverflow\event\rating_updated::create($params);
        $event->trigger();

        return $DB->execute($sql, [$postid, $userid, $rating, time(), $ratingid]);
    }

    /**
     * Check if a user can rate the post.
     *
     * @param object $post
     * @param \context_module   $modulecontext
     * @param null|int $userid
     *
     * @return bool
     */
    public static function moodleoverflow_user_can_rate($post, $modulecontext, $userid = null) {
        global $USER;
        if (!$userid) {
            // Guests and non-logged-in users can not rate.
            if (isguestuser() || !isloggedin()) {
                return false;
            }
            $userid = $USER->id;
        }

        // Check the capability.
        return capabilities::has(capabilities::RATE_POST, $modulecontext, $userid)
            && $post->reviewed == 1;
    }

    /**
     * Helper function for moodleoverflow_sort_answer_by_rating. Sorts a group of posts (solved and helpful, only solved/helpful
     * and other) after their votesdifference and if needed after their modified time.
     *
     * @param array $posts  The array that will be sorted
     * @param int   $low    Startindex from where equal votes will be checked
     * @param int   $high   Endindex until where equal votes will be checked
     * @return void
     */
    private static function moodleoverflow_sort_postgroup(&$posts, $low, $high) {
        // First sort the array after their votesdifference.
        moodleoverflow_quick_array_sort($posts, 0, $high, 'votesdifference', 'desc');

        // Check if posts have the same votesdifference and sort them after their modified time if needed.
        while ($low < $high) {
            if ($posts[$low]->votesdifference == $posts[$low + 1]->votesdifference) {
                $tempstartindex = $low;
                $tempendindex = $tempstartindex + 1;
                while (($tempendindex + 1 <= $high) &&
                      ($posts[$tempendindex]->votesdifference == $posts[$tempendindex + 1]->votesdifference)) {
                    $tempendindex++;
                }
                moodleoverflow_quick_array_sort($posts, $tempstartindex, $tempendindex, 'modified', 'asc');
                $low = $tempendindex + 1;
            } else {
                $low++;
            }
        }
    }
}
