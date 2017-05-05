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
 * English strings for moodleoverflow
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_moodleoverflow
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'moodleoverflow';
$string['modulenameplural'] = 'moodleoverflows';
$string['modulename_help'] = 'The moodleoverflow module enables participants to use a StackOverflow-like forumstructure.';
$string['moodleoverflow:addinstance'] = 'Add a new moodleoverflow instance';
$string['moodleoverflow:submit'] = 'Submit moodleoverflow';
$string['moodleoverflow:view'] = 'View moodleoverflow';
$string['moodleoverflowfieldset'] = 'Custom example fieldset';
$string['moodleoverflowname'] = 'moodleoverflow name';
$string['moodleoverflowname_help'] = 'This is the content of the help tooltip associated with the moodleoverflowname field. Markdown syntax is supported.';
$string['moodleoverflow'] = 'moodleoverflow';
$string['pluginadministration'] = 'moodleoverflow administration';
$string['pluginname'] = 'Moodleoverflow';

// Strings for the view.php
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this moodleoverflow';

// Strings for the lib.php
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['nodiscussions'] = 'There are no discussion topics yet in this moodleoverflow';
$string['headerdiscussion'] = 'Discussion';
$string['headerstartedby'] = 'Started by';
$string['headerreplies'] = 'Replies';
$string['headerlastpost'] = 'Last post';
$string['headerunread'] = 'Unread';
$string['markallread'] = 'Mark read';
$string['markalldread'] = 'Mark all posts in this discussion read.';

// Stromgs for the settings.php
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a moodleoverflow instance per page';
$string['manydiscussions'] = 'Discussions per page';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['oldpostdays'] = 'Read after days';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['trackmoodleoverflow'] = 'Track unread posts';
$string['configtrackmoodleoverflow'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['configforcedreadtracking'] = 'Allows moodleoverflows to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many moodleoverflows and posts. When off, any moodleoverflows previously set to Forced are treated as optional.';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
