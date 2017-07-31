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
 * @package   mod_moodleoverflow
 * @copyright 2017 Kennet Winter <k_wint10@uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Default strings.
$string['modulename'] = 'moodleoverflow';
$string['modulenameplural'] = 'moodleoverflows';
$string['modulename_help'] = 'The moodleoverflow module enables participants to use a StackOverflow-like forumstructure.';
$string['moodleoverflowfieldset'] = 'Custom example fieldset';
$string['moodleoverflowname'] = 'moodleoverflow name';
$string['moodleoverflowname_help'] = 'This is the content of the help tooltip associated with the moodleoverflowname field. Markdown syntax is supported.';
$string['moodleoverflow'] = 'Moodleoverflow';
$string['pluginadministration'] = 'moodleoverflow administration';
$string['pluginname'] = 'Moodleoverflow';

// Capabilities.
$string['moodleoverflow:addinstance'] = 'Add a new moodleoverflow instance';
$string['moodleoverflow:submit'] = 'Submit moodleoverflow';
$string['moodleoverflow:allowforcesubscribe'] = 'Allow forced subscription';
$string['moodleoverflow:managesubscriptions'] = 'Manage subscriptions';
$string['moodleoverflow:ratesolved'] = 'Mark a post as helpful';
$string['moodleoverflow:ratepost'] = 'Rate a post';
$string['moodleoverflow:viewanyrating'] = 'View ratings';
$string['moodleoverflow:deleteanypost'] = 'Delete posts';
$string['moodleoverflow:deleteownpost'] = 'Delete own posts';
$string['moodleoverflow:editanypost'] = 'Edit posts';
$string['moodleoverflow:startdiscussion'] = 'Start a discussion';
$string['moodleoverflow:replypost'] = 'Reply in discussion';
$string['moodleoverflow:viewdiscussion'] = 'View discussion';
$string['moodleoverflow:view'] = 'View discussionlist';
$string['nowallsubscribed'] = 'All moodleoverflows in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All moodleoverflows in {$a} are not subscribed.';

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
$string['bynameondate'] = 'by {$a->name} ({$a->rating}) - {$a->date}';
$string['bynameondatenorating'] = 'by {$a->name} - {$a->date}';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';

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

$string['votescalevote'] = 'Reputation: Vote.';
$string['configvotescalevote'] = 'The amount of reputation voting gives.';
$string['votescaledownvote'] = 'Reputation: Downvote';
$string['configvotescaledownvote'] = 'The amount of reputation a downvote for your post gives.';
$string['votescaleupvote'] = 'Reputation: Upvote';
$string['configvotescaleupvote'] = 'The amount of reputation an upvote for your post gives.';
$string['votescalecorrect'] = 'Reputation: Correct';
$string['configvotescalecorrect'] = 'The amount of reputation a mark as correct on your post gives.';
$string['votescalehelpful'] = 'Reputation: Helpful';
$string['configvotescalehelpful'] = 'The amount of reputation a mark as helpful on your post gives.';
$string['reputationnotnegative'] = 'Reputation just positive?';
$string['configreputationnotnegative'] = 'Prohibits the users reputation being negative.';
$string['allowcoursereputation'] = 'Sum reputation within a course.';
$string['configallowcoursereputation'] = 'Allow to sum the reputation of all instances of the current course?';
$string['maxmailingtime'] = 'Maximal mailing time';
$string['configmaxmailingtime'] = 'Posts older than this number of hours will not be mailed to the users. This will help to avoid problems where the cron has not een running for a long time.';






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
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';

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

// Strings for the classes/ratings.php.
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
$string['ratingtoold'] = 'Ratings can only be changed within 30 minutes after the first vote. ';

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
$string['markcorrect'] = 'Mark as Helpful';
$string['marknotcorrect'] = 'Not Helpful';

// Strings for the readtracking.php.
$string['markreadfailed'] = 'A post of the discussion could not be marked as read.';
$string['markdiscussionreadsuccessful'] = 'The discussion has been marked as read.';
$string['markmoodleoverflowreadsuccessful'] = 'All posts have been marked as read.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';

// OTHER.
$string['unknownerror'] = 'This is not expected to happen.';
$string['crontask'] = 'Moodleoverflow maintenance jobs';
$string['taskcleanreadrecords'] = 'Moodleoverflow maintenance job to clean old read records';
$string['tasksendmails'] = 'Moodleoverflow maintenance job to send mails';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view moodleoverflow subscribers';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to moodleoverflow post notifications.';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this moodleoverflow';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->moodleoverflow}\'';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that moodleoverflow';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->moodleoverflow}\'';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this moodleoverflow';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->moodleoverflow}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->moodleoverflow}\'';
$string['unsubscribe'] = 'Unsubscribe from this moodleoverflow';
$string['subscribe'] = 'Subscribe to this moodleoverflow';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in moodleoverflow \'{$a->moodleoverflow}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from moodleoverflow \'{$a}\'?';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in forum \'{$a->moodleoverflow}\'?';
$string['confirmsubscribe'] = 'Do you really want to subscribe to forum \'{$a}\'?';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['smallmessage'] = '{$a->user} posted in {$a->moodleoverflowname}';
$string['moodleoverflows'] = 'Moodleoverflows';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}.

To reply click on this link: {$a->replylink}';
$string['unsubscribelink'] = 'Unsubscribe from this forum: {$a}';
$string['unsubscribediscussionlink'] = 'Unsubscribe from this discussion: {$a}';
$string['postincontext'] = 'See this post in context';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->moodleoverflow}\'.';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->moodleoverflow}\'.';
$string['cannottrack'] = 'Could not stop tracking that moodleoverflow';
$string['notrackmoodleoverflow'] = 'Don\'t track unread posts';
$string['trackmoodleoverflow'] = 'Track unread posts';
$string['discussions'] = 'Discussions';
$string['subscribed'] = 'Subscribed';
$string['unreadposts'] = 'Unread posts';
$string['tracking'] = 'Track';
$string['allsubscribe'] = 'Subscribe to all moodleoverflows';
$string['allunsubscribe'] = 'Unsubscribe from all moodleoverflows';
$string['generalmoodleoverflows'] = 'Moodleoverflows in this course';
$string['subscribestart'] = 'Send me notifications of new posts in this moodleoverflow';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this moodleoverflow';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this moodleoverflow';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this moodleoverflow';
$string['mailindexlink'] = 'Change your moodleoverflow preferences: {$a}';
$string['gotoindex'] = 'Manage preferences';

// EVENTS.
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionviewed']  = 'Discussion viewed';
$string['eventratingcreated'] = 'Rating created';
$string['eventratingupdated'] = 'Rating updated';
$string['eventratingdeleted'] = 'Rating deleted';
$string['eventpostcreated'] = 'Post created';
$string['eventpostupdated'] = 'Post updated';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';


$string['subscriptiontrackingheader'] = 'Subscription and tracking';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a moodleoverflow it means they will receive forum post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'Read tracking enables participants to easily check which posts they have not yet seen by highlighting any new posts.

If set to optional, participants can choose whether to turn tracking on or off via a link in the administration block.

If \'Allow forced read tracking\' is enabled in the site administration, then a further option is available - forced. This means that tracking is always on.';
$string['ratingheading'] = 'Rating and reputation';
$string['starterrating'] = 'Correct';
$string['teacherrating'] = 'Helpful';
$string['ratingpreference'] = 'Display first';
$string['ratingpreference_help'] = 'Answers can be marked as correct and helpful. This option decides which of these will be pinned as the first answer of the discussion. There are 2 options:

* Correct - A teachers mark as helpful will be pinned at the top of the discussion
* Helpful - A topic starters mark as correct will be pinned at the top of the discussion';
$string['allownegativereputation'] = 'Allow negative reputation?';
$string['allownegativereputation_help'] = 'If set to yes, the users reputation within a course or within a module can be negative or will stop to decrease at zero.';
$string['coursewidereputation'] = 'Cross module reputation?';
$string['coursewidereputation_help'] = 'If set to yes, the users reputations of all moodleoverflow modules in this course will be summed.';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';