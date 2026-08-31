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

namespace mod_moodleoverflow\search;

use context;
use context_module;
use core_search\document;
use core_search\document_factory;
use core_search\manager;
use dml_exception;
use dml_missing_record_exception;
use mod_moodleoverflow\anonymous;
use moodle_recordset;
use moodle_url;
use stdClass;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/moodleoverflow/locallib.php');

/**
 * Moodleoverflow posts search area.
 *
 * @package    mod_moodleoverflow
 * @copyright  2026 Tamaro Walter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post extends \core_search\base_mod {
    /**
     * @var \mod_moodleoverflow\models\post[] Internal quick static cache of post entities.
     */
    protected $posts = [];

    /**
     * Returns recordset containing required data for indexing forum posts.
     *
     * @param int $modifiedfrom timestamp
     * @param context|null $context Optional context to restrict scope of returned results
     * @return moodle_recordset|null Recordset (or null if no results)
     */
    public function get_document_recordset($modifiedfrom = 0, ?context $context = null): moodle_recordset|null {
        global $DB;

         [$contextjoin, $contextparams] = $this->get_context_restriction_sql($context, 'moodleoverflow', 'm');
        if ($contextjoin === null) {
            return null;
        }

        $sql = "SELECT p.*, m.id AS moodleoverflowid, m.course AS courseid, m.anonymous AS anonymous,
                       d.name AS discussionname, d.userid AS discussionuserid
                FROM {moodleoverflow_posts} p
                JOIN {moodleoverflow_discussions} d ON d.id = p.discussion
                JOIN {moodleoverflow} m ON m.id = d.moodleoverflow
                $contextjoin
                WHERE p.modified >= ?
                ORDER BY p.modified ASC";

        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom]));
    }

    /**
     * Returns the document associated with this post id.
     *
     * @param stdClass $record Post info.
     * @param array    $options
     * @return document|false
     */
    public function get_document($record, $options = []): document|false {

        try {
            $cm = $this->get_cm('moodleoverflow', $record->moodleoverflowid, $record->courseid);
            $context = context_module::instance($cm->id);
        } catch (dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $re = get_string('re', 'mod_moodleoverflow') . ' ';
        $anonymous = anonymous::is_post_anonymous(
            (object) ['userid' => $record->discussionuserid],
            (object) ['anonymous' => $record->anonymous],
            $record->userid
        );

        $doc = document_factory::instance($record->id, $this->componentname, $this->areaname);
        $title = $record->parent ? $re . $record->discussionname : $record->discussionname;
        $doc->set('title', content_to_text($title, false));
        $doc->set('content', content_to_text($record->message, $record->messageformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->courseid);
        $doc->set('owneruserid', manager::NO_OWNER_ID);
        $doc->set('modified', $record->modified);
        if (!$anonymous) {
            $doc->set('userid', $record->userid);
        }

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->created)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Returns true if this area uses file indexing.
     * @return bool
     */
    public function uses_file_indexing(): bool {
        return true;
    }

    /**
     * Return the context info required to index files for this search area.
     * @return array
     */
    public function get_search_fileareas(): array {
        return ['attachment', 'post'];
    }

    /**
     * Add the post attachments.
     *
     * @param document $document The current document
     * @return void
     */
    public function attach_files($document): void {
        $postid = $document->get('itemid');
        $context = context::instance_by_id($document->get('contextid'));
        $fs = get_file_storage();
        foreach ($this->get_search_fileareas() as $filearea) {
            foreach ($fs->get_area_files($context->id, $this->get_component_name(), $filearea, $postid, '', false) as $file) {
                $document->add_stored_file($file);
            }
        }
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id Forum post id
     * @return int
     */
    public function check_access($id): int {
        try {
            $post = $this->get_post($id);
            $cminfo = $this->get_cm('moodleoverflow', $post->get_moodleoverflow()->id, $post->get_moodleoverflow()->course);
        } catch (dml_missing_record_exception $ex) {
            return manager::ACCESS_DELETED;
        } catch (dml_exception $ex) {
            return manager::ACCESS_DENIED;
        }

        if ($cminfo->uservisible === false || !moodleoverflow_user_can_see_post($post, $cminfo)) {
            return manager::ACCESS_DENIED;
        }

        return manager::ACCESS_GRANTED;
    }

    /**
     * Link to the post discussion.
     *
     * @param document $doc
     * @return moodle_url
     */
    public function get_doc_url(document $doc): moodle_url {
        $post = $this->get_post($doc->get('itemid'));
        $path = '/mod/moodleoverflow/discussion.php';
        return new moodle_url($path, ['d' => $post->get_discussionid()], 'p' . $doc->get('itemid'));
    }

    /**
     * Link to the moodleoverflow.
     *
     * @param document $doc
     * @return moodle_url
     */
    public function get_context_url(document $doc): moodle_url {
        $contextmodule = context::instance_by_id($doc->get('contextid'));
        return new moodle_url('/mod/moodleoverflow/view.php', ['id' => $contextmodule->instanceid]);
    }

    /**
     * Returns the specified forum post from its internal cache.
     *
     * @param int $postid
     * @return \mod_moodleoverflow\models\post
     * @throws dml_missing_record_exception|dml_exception
     */
    protected function get_post(int $postid): \mod_moodleoverflow\models\post {
        global $DB;
        if (empty($this->posts[$postid])) {
            $record = $DB->get_record('moodleoverflow_posts', ['id' => $postid], '*', MUST_EXIST);
            $this->posts[$postid] = \mod_moodleoverflow\models\post::from_record($record);
        }
        return $this->posts[$postid];
    }

    /**
     * Changes the context ordering so that the forums with most recent discussions are indexed
     * first.
     *
     * @return string[] SQL join and ORDER BY
     */
    protected function get_contexts_to_reindex_extra_sql(): array {
        return [
            'JOIN {moodleoverflow_discussions} md ON md.course = cm.course AND md.moodleoverflow = cm.instance',
            'MAX(md.timemodified) DESC',
        ];
    }
}
