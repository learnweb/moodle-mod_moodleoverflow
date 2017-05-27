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

// Default strings.
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

// Strings for the view.php.
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this moodleoverflow';

// Strings for the locallib.php.
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['nodiscussions'] = 'There are no discussion topics yet in this moodleoverflow.';
$string['headerdiscussion'] = 'Discussion';
$string['headerstartedby'] = 'Started by';
$string['headerreplies'] = 'Replies';
$string['headerlastpost'] = 'Last post';
$string['headerunread'] = 'Unread';
$string['headervotes'] = 'Votes';
$string['headerstatus'] = 'Status';
$string['markallread'] = 'Mark read';
$string['markallread'] = 'Mark all posts in this discussion read.';
$string['delete'] = 'Delete';
$string['parent'] = 'Show parent';
$string['markread'] = 'Mark read';
$string['markunread'] = 'Mark unread';
$string['permalink'] = 'Permalink';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['bynameondate'] = 'by {$a->name} - {$a->date}';

// Strings for the settings.php.
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

// Strings for the post.php.
$string['invalidmoodleoverflowid'] = 'Moodleoverflow ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['nopostmoodleoverflow'] = 'Sorry, you are not allowed to post to this moodleoverflow.';
$string['yourreply'] = 'Your reply';
$string['re'] = 'Re:';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['edit'] = 'Edit';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['cannotupdatepost'] = 'You can not update this post';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['postupdated'] = 'Your post was updated';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';


// Strings for the classes/mod_form.php.
$string['subject'] = 'Subject';
$string['reply'] = 'Comment';
$string['replyfirst'] = 'Answer';
$string['message'] = 'Message';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['posttomoodleoverflow'] = 'Post to moodleoverflow';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['yournewtopic'] = 'Your new discussion topic';

// Strings for the classes/ratings.php
$string['postnotexist'] = 'Requested post does not exist';
$string['noratemoodleoverflow'] = 'Sorry, you are not allowed to vote in this moodleoverflow.';
$string['configallowratingchange'] = 'Can a user change its ratings?';
$string['allowratingchange'] = 'Allow rating changes';
$string['configpreferteachersmark'] = 'The answer marked as correct by a teacher are prioritized over the answer marked as correct by the starter of the discussion.';
$string['preferteachersmark'] = 'Prefer teachers marks?';
$string['noratingchangeallowed'] = 'You are not allowed to change your ratings.';
$string['invalidratingid'] = 'The submitted rating is neither an upvote nor a downvote.';
$string['notstartuser'] = 'Only the user who started the discussion can mark an answer as the solution.';
$string['notteacher'] = 'Only users with the status teacher can do this.';

// Strings for the discussion.php.
$string['invaliddiscussionid'] = 'Discussion ID was incorrect';
$string['notexists'] = 'Discussion no longer exists';
$string['discussionname'] = 'Discussion name';
$string['discussionlocked'] = 'This discussion has been locked so you can no longer reply to it.';
$string['hiddenmoodleoverflowpost'] = 'Hidden moodleoverflow post';
$string['moodleoverflowsubjecthidden'] = 'Subject (hidden)';
$string['moodleoverflowauthorhidden'] = 'Author (hidden)';
$string['moodleoverflowbodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['addanewreply'] = 'Add a new answer';
$string['ratingfailed'] = 'Rating failed. Try again.';
$string['marksolved'] = 'Mark as Solved';
$string['marknotsolved'] = 'Not Solved';
$string['markcorrect'] = 'Mark as Correct';
$string['marknotcorrect'] = 'Not Correct';

