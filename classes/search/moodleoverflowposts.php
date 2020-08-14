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
 * Search api for moodleoverflowposts
 * Copyright 2020 Robin Tschudi
 */

namespace mod_moodleoverflow\search;
use core_search\document;
defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/../../locallib.php');
class moodleoverflowposts extends \core_search\base_mod {

    /**
     * @var array Internal quick static cache.
     */
    protected $postsdata = array();
    protected $moodleoverflows = array();
    protected $discussions = array();

    protected static $levels = [CONTEXT_COURSE];

    public function uses_file_indexing() {
        return false;
    }

    public function get_document($record, $options = array()) {
        try {
            $cm = $this->get_cm('moodleoverflow', $record->moodleoverflowid, $record->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);

        $doc->set('title', content_to_text($record->title, true));
        $doc->set('content', content_to_text($record->message, FORMAT_HTML));
        $doc->set('contextid', $context->id);
        $doc->set('type', \core_search\manager::TYPE_TEXT);
        $doc->set('courseid', $record->course);
        $doc->set('modified', $record->modified);
        $doc->set('userid', $record->userid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $postdata[$record->id[0]] = array('discussionid' => $record->discussion, 'moodleoverflowid' => $record->moodleoverflowid);
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $record->created)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    private function get_discussion_from_id($discussionid) {
        global $DB;
        if (isset($this->discussions[$discussionid])) {
            return $this->discussions[$discussionid];
        } else {
            if (!$discussion = $DB->get_record('moodleoverflow_discussions', array('id' => $discussionid))) {
                return false;
            }
            $this->discussions[$discussionid] = $discussion;
            return $discussion;
        }
    }

    private function get_moodleoverflow_from_id($moodleoverflowid) {
        global $DB;
        if (isset($this->moodleoverflows[$moodleoverflowid])) {
            return $this->moodleoverflows[$moodleoverflowid];
        } else {
            if (!$moodleoverflow = $DB->get_record('moodleoverflow', array('id' => $moodleoverflowid))) {
                return false;
            }
            $this->moodleoverflows[$moodleoverflowid] = $moodleoverflow;
            return $moodleoverflow;
        }
    }

    public function check_access($id) {
        try {
            $post = moodleoverflow_get_post_full($id);
            if (!$discussion = $this->get_discussion_from_id($post->discussion)) {
                return \core_search\manager::ACCESS_DELETED;
            }
            if (!$moodleoverflow = $this->get_moodleoverflow_from_id($post->moodleoverflow)) {
                return \core_search\manager::ACCESS_DELETED;
            }
            $context = moodleoverflow_get_context($post->moodleoverflow);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }
        if (moodleoverflow_user_can_see_discussion($moodleoverflow, $discussion, $context)) {
            return \core_search\manager::ACCESS_GRANTED;
        }
        return \core_search\manager::ACCESS_DENIED;
    }

    public function get_discussionid_for_document(document $doc) {
        global $DB;
        $postid = $doc->get('itemid');
        if (isset($this->postsdata[$postid]) && isset($this->postsdata[$postid]['discussionid'])) {
            return $this->postsdata[$postid]['discussionid'];
        } else {
            $discussionid = $DB->get_field('moodleoverflow_posts', 'discussion', array('id' => $postid));
            if (!isset($this->postsdata[$postid])) {
                $this->postsdata[$postid] = array();
            }
            $this->postsdata[$postid]['discussionid'] = $discussionid;
            return $discussionid;
        }
    }

    public function get_moodleoverflowid_for_document(document $doc) {
        global $DB;
        $discussionid = $this->get_discussionid_for_document($doc);
        $postid = $doc->get('itemid');
        if (isset($this->postsdata[$postid]) && isset($this->postsdata[$postid]["moodleoverflowid"])) {
            return $this->postsdata[$postid]["moodleoverflowid"];
        } else {
            $moodleoverflowid = $DB->get_field('moodleoverflow_discussions', 'moodleoverflow', array('id' => $discussionid));
            if (!isset($this->postsdata[$postid])) {
                $this->postsdata[$postid] = array();
            }
            $this->postsdata[$postid]['moodleoverflowid'] = $moodleoverflowid;
            return $moodleoverflowid;
        }
    }

    public function get_doc_url(document $doc) {
        return new \moodle_url('/mod/moodleoverflow/discussion.php', array('d' => $this->get_discussionid_for_document($doc)),
            "p" . $doc->get('itemid'));
    }

    public function get_context_url(document $doc) {
        return new \moodle_url('/mod/moodleoverflow/view.php', array('m' => $this->get_moodleoverflowid_for_document($doc)));
    }

    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;
        list($contextjoin, $contextparams) = $this->get_context_restriction_sql($context, 'moodleoverflow', 'discussions');
        if ($contextjoin === null) {
            return null;
        }
        $sql = "SELECT md.name as title, mp.*, mo.course, mo.id as moodleoverflowid FROM {moodleoverflow_posts} mp
                                                        JOIN {moodleoverflow_discussions} md ON mp.discussion = md.id
                                                        JOIN {moodleoverflow} mo ON md.moodleoverflow = mo.id
                    $contextjoin
                WHERE mp.modified >= ? ORDER BY mp.modified ASC";
        return $DB->get_recordset_sql($sql, array_merge($contextparams, [$modifiedfrom]));
    }
}
