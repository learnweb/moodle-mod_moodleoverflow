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

$string['action_downvote'] = 'Click to downvote';
$string['action_remove_downvote'] = 'Click to remove downvote';
$string['action_remove_upvote'] = 'Click to remove upvote';
$string['action_upvote'] = 'Click to upvote';
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['addanewreply'] = 'Add a new answer';
$string['allowanonymous'] = 'Allow anonymous';
$string['allowanonymous_desc'] = 'Allow teachers to put moodleoverflow forums into anonymous question or full anonymous mode. Once enabled, anonymous forums will stay anonymous, even when this setting is disabled. If you really want to, you can reset anonymity in all forums <a href="{$a}">here</a>.';
$string['allowcoursereputation'] = 'Sum reputation within a course.';
$string['allowdisablerating'] = 'Allow teachers to disable rating and reputation';
$string['allowmultiplemarks'] = 'Multiple marks?';
$string['allowmultiplemarks_help'] = 'A post can be marked as helpful or solved. Within a discussion, only one post can be marked as helpful/solved. Click the checkbox to mark multiple posts as helpful/solved.';
$string['allownegativereputation'] = 'Allow negative reputation?';
$string['allownegativereputation_help'] = 'If set to yes, the users reputation within a course or within a module can be negative. If set to no, the reputation will stop to decrease at zero.';
$string['allowrating'] = 'Allow post ratings?';
$string['allowrating_help'] = 'If set to yes, users can up or downvote a post to give the reader an idea of how helpful the post was to other people. If set to no, ratings will be disabled.';
$string['allowratingchange'] = 'Allow rating changes';
$string['allowreputation'] = 'Allow user reputation?';
$string['allowreputation_help'] = 'If set to yes, the users can gain or lose reputation depending on other users voting on their posts. If set to no, user reputation will be disabled.';
$string['allowreview'] = 'Allow moderated forums';
$string['allowreview_desc'] = 'Allow teachers to enable that all posts (or only all questions) have to be reviewed by them in order to be published.';
$string['allsubscribe'] = 'Subscribe to all forums';
$string['allunsubscribe'] = 'Unsubscribe from all forums';
$string['alsomarkhelpful'] = "Also mark as helpful";
$string['alsomarksolved'] = "Also mark as solution";
$string['amount_unread_posts_in_discussion'] = 'There are {$a} unread posts in this discussion.';
$string['amount_waiting_for_review'] = '{$a} post(s) need to be reviewed!';
$string['anonym_you'] = 'Anonymous (You)';
$string['anonymous'] = 'Anonymous';
$string['anonymous:everything'] = 'Questioners and answerers (Irreversible!)';
$string['anonymous:only_questions'] = 'Only questioners (Irreversible!)';
$string['anonymous_help'] = 'This will hide username from all question (and answers).<br>WARNING: Once the questions (and answers) are anonymized, this cannot be reversed.<br>The setting can only be changed to a higher degree of anonymity.';
$string['answer'] = '{$a} Answer';
$string['answerer'] = 'Answerer #{$a}';
$string['answers'] = '{$a} Answers';
$string['approve'] = 'Approve';
$string['areaattachment'] = 'Attachments';
$string['areapost'] = 'Messages';
$string['attachment'] = 'Attachment';
$string['attachment_help'] = 'You can optionally attach one or more files to a forum post. If you attach an image, it will be displayed after the message.';
$string['attachments'] = 'Attachments';
$string['bynameondate'] = 'by {$a->name} ({$a->rating}) - {$a->date}';
$string['bynameondatenorating'] = 'by {$a->name} - {$a->date}';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannottrack'] = 'Could not stop tracking that forum';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that forum';
$string['cannotupdatepost'] = 'You can not update this post';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['configallowcoursereputation'] = 'Allow to sum the reputation of all instances of the current course?';
$string['configallowdisablerating'] = 'Set to \'yes\' if you want to give teachers the ability to disable rating and reputation.';
$string['configallowratingchange'] = 'Can a user change its ratings?';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
$string['configforcedreadtracking'] = 'Allows Moodleoverflows to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many moodleoverflows and posts. When off, any moodleoverflows previously set to Forced are treated as optional.';
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a Moodleoverflow instance per page';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all forum attachments on the site (subject to course limits and other local settings)';
$string['configmaxeditingtime'] = 'Default maximum seconds are 3600 (= one hour). Regarding editing posts, please also consider the <a href="#admin-reviewpossibleaftertime">"Review possible after" setting</a> for moderated forums.';
$string['configmaxmailingtime'] = 'Posts older than this number of hours will not be mailed to the users. This will help to avoid problems where the cron has not been running for a long time.';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['configpreferteachersmark'] = 'The answer marked as solution by a course owner are prioritized over the answer marked as helpful by the starter of the discussion.';
$string['configreputationnotnegative'] = 'Prohibits the users reputation being negative.';
$string['configshowuserstats'] = 'Allow teachers in courses to see statistics summarizing the activity of users in Moodleoverflows.';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['configtrackmoodleoverflow'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['configvotescaledownvote'] = 'The amount of reputation a downvote for your post gives.';
$string['configvotescalehelpful'] = 'The amount of reputation a mark as helpful on your post gives.';
$string['configvotescalesolved'] = 'The amount of reputation a mark as solution on your post gives.';
$string['configvotescaleupvote'] = 'The amount of reputation an upvote for your post gives.';
$string['configvotescalevote'] = 'The amount of reputation voting gives.';
$string['confirmsubscribe'] = 'Do you really want to subscribe to forum \'{$a}\'?';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in forum \'{$a->moodleoverflow}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from moodleoverflow \'{$a}\'?';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in moodleoverflow \'{$a->moodleoverflow}\'?';
$string['containsstarterrating'] = 'Contains a post marked as the most helpful by the questioner';
$string['containsteacherrating'] = 'Contains a post marked as the solution by a teacher';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['coursewidereputation'] = 'Cross module reputation?';
$string['coursewidereputation_help'] = 'If set to yes, the users reputations of all moodleoverflow modules in this course will be summed.';
$string['crontask'] = 'Moodleoverflow maintenance jobs';
$string['delete'] = 'Delete';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';
$string['desc:anonymous'] = 'No names will be displayed.';
$string['desc:only_questions'] = 'The name of questioners will not be displayed in their question and comments.';
$string['desc:review_everything'] = 'All questions and answers are going to be reviewed by a teacher before being published.';
$string['desc:review_questions'] = 'All questions are going to be reviewed by a teacher before being published.';
$string['digestunreadpost'] = 'Course: {$a->linktocourse} -> {$a->linktoforum}, Topic: {$a->linktodiscussion} has {$a->unreadposts} unread posts.';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['discussionlocked'] = 'This discussion has been locked so you can no longer reply to it.';
$string['discussionname'] = 'Discussion name';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->moodleoverflow}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->moodleoverflow}\'';
$string['discussions'] = 'Discussions';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['downvotenotchangeable'] = 'Cannot downvote';
$string['edit'] = 'Edit';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['email_rejected_subject'] = '{$a->coursename}: One of your posts has been rejected.';
$string['email_review_needed_subject'] = 'Review needed in {$a->coursename}: {$a->subject}';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventdiscussionviewed'] = 'Discussion viewed';
$string['eventpostcreated'] = 'Post created';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventpostupdated'] = 'Post updated';
$string['eventratingcreated'] = 'Rating created';
$string['eventratingdeleted'] = 'Rating deleted';
$string['eventratingupdated'] = 'Rating updated';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this forum';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this forum';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['generalmoodleoverflows'] = 'Forums in this course';
$string['give_a_reason'] = 'Give a reason (optional)';
$string['gotoindex'] = 'Manage preferences';
$string['grademaxgradeerror'] = 'Maximum grade must be a positive integer different than 0';
$string['gradesreport'] = 'Grades report';
$string['gradesupdated'] = 'Grades updated';
$string['helpamountofactivity'] = 'Each activity like writing a post, starting a discussion or giving a rating gives 1 point';
$string['hiddenmoodleoverflowpost'] = 'Hidden forum post';
$string['invaliddiscussionid'] = 'Discussion ID was incorrect';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['invalidmoodleoverflowid'] = 'Forum ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['invalidratingid'] = 'The submitted rating is neither an upvote nor a downvote.';
$string['jump_to_next_post_needing_review'] = 'Jump to next post needing to be reviewed.';
$string['la_endtime'] = 'Time at which students can no longer answer';
$string['la_endtime_help'] = 'Students can not answer to qustions after the set up date';
$string['la_endtime_ruleerror'] = 'End time must be in the future';
$string['la_sequence_error'] = 'The end time must be after the start time';
$string['la_starttime'] = 'Time at which students can start to answer';
$string['la_starttime_help'] = 'Students can not answer to questions until the set up date';
$string['la_starttime_ruleerror'] = 'Start time must be in the future';
$string['lastpost'] = 'Last post';
$string['limitedanswer_helpicon_teacher'] = 'This can be changed in the settings of the Moodleoverflow.';
$string['limitedanswer_info_endtime'] = 'Posts can not be answered after {$a->limitedanswerdate}.';
$string['limitedanswer_info_start'] = 'This Moodleoverflow is in a limited answer mode.';
$string['limitedanswer_info_starttime'] = 'Posts can not be answered until {$a->limitedanswerdate}.';
$string['limitedanswerheading'] = 'Limited Answer Mode';
$string['limitedanswerwarning_answers'] = 'There are already answered posts in this Moodleoverflow.';
$string['limitedanswerwarning_conclusion'] = 'You can only set a time until students are able to answer';
$string['mailindexlink'] = 'Change your forum preferences: {$a}';
$string['manydiscussions'] = 'Discussions per page';
$string['markallread'] = 'Mark all posts in this discussion as read';
$string['markallread_forum'] = 'Mark all posts as read';
$string['markdiscussionreadsuccessful'] = 'The discussion has been marked as read.';
$string['markhelpful'] = 'Mark as helpful';
$string['markmoodleoverflowreadsuccessful'] = 'All posts have been marked as read.';
$string['marknothelpful'] = 'Not Helpful';
$string['marknotsolved'] = 'Remove solution mark';
$string['markread'] = 'Mark read';
$string['markreadfailed'] = 'A post of the discussion could not be marked as read.';
$string['marksolved'] = 'Mark as solution';
$string['markunread'] = 'Mark unread';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a forum post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a forum post.';
$string['maxeditingtime'] = 'Maximum amount of time during which a post can be edited by its owner (sec)';
$string['maxmailingtime'] = 'Maximal mailing time';
$string['message'] = 'Message';
$string['messageprovider:posts'] = 'Notification of new posts';
$string['modulename'] = 'Moodleoverflow';
$string['modulename_help'] = 'The Moodleoverflow module enables participants to use a question-answer forum structure. The forum display is non-chronological as the ordering depends on collaborative voting instead of on time.';
$string['modulenameplural'] = 'Moodleoverflows';
$string['moodleoverflow'] = 'Moodleoverflow';
$string['moodleoverflow:addinstance'] = 'Add a new Moodleoverflow instance';
$string['moodleoverflow:allowforcesubscribe'] = 'Allow forced subscription';
$string['moodleoverflow:createattachment'] = 'Create attachments';
$string['moodleoverflow:deleteanypost'] = 'Delete posts';
$string['moodleoverflow:deleteownpost'] = 'Delete own posts';
$string['moodleoverflow:editanypost'] = 'Edit posts';
$string['moodleoverflow:managesubscriptions'] = 'Manage subscriptions';
$string['moodleoverflow:marksolved'] = 'Mark a post as solution';
$string['moodleoverflow:movetopic'] = 'Move Topic to another Moddleoverflow Forum';
$string['moodleoverflow:ratehelpful'] = 'Mark a post as helpful';
$string['moodleoverflow:ratepost'] = 'Rate a post';
$string['moodleoverflow:replypost'] = 'Reply in discussion';
$string['moodleoverflow:reviewpost'] = 'Review (approve or reject) posts';
$string['moodleoverflow:startdiscussion'] = 'Start a discussion';
$string['moodleoverflow:viewanyrating'] = 'View total ratings that anyone received';
$string['moodleoverflow:viewdiscussion'] = 'View discussion';
$string['moodleoverflowauthorhidden'] = 'Author (hidden)';
$string['moodleoverflowbodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['moodleoverflowfieldset'] = 'Custom example fieldset';
$string['moodleoverflowname'] = 'Moodleoverflow name';
$string['moodleoverflows'] = 'Moodleoverflows';
$string['moodleoverflowsubjecthidden'] = 'Subject (hidden)';
$string['movetopicicon'] = 'Move this discussion to another moodleoverflow';
$string['movetopictoforum'] = 'Move discussion to {$a->forumname}';
$string['nodiscussions'] = 'There are no discussion topics yet in this forum.';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view subscribers';
$string['nopostmoodleoverflow'] = 'Sorry, you are not allowed to post to this forum.';
$string['noratemoodleoverflow'] = 'Sorry, you are not allowed to vote in this forum.';
$string['noratingchangeallowed'] = 'You are not allowed to change your ratings.';
$string['notexists'] = 'Discussion no longer exists';
$string['nothing'] = 'Nothing';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['notrackmoodleoverflow'] = 'Don\'t track unread posts';
$string['notstartuser'] = 'Only the user who started the discussion can mark an answer as helpful.';
$string['notteacher'] = 'Only course owners can do this.';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this forum';
$string['nowallsubscribed'] = 'All forums in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All forums in {$a} are unsubscribed.';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->moodleoverflow}\'';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->moodleoverflow}\'.';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->moodleoverflow}\'';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->moodleoverflow}\'.';
$string['oldpostdays'] = 'Read after days';
$string['original_post'] = 'Original post';
$string['parent'] = 'Show parent';
$string['pending_review'] = 'Pending review';
$string['pending_review_but_cannot_now'] = 'Pending review, but cannot be approved until {$a} after the creation of this post to allow the author a bit of time to edit it.';
$string['permalink'] = 'Permalink';
$string['pluginadministration'] = 'Moodleoverflow administration';
$string['pluginname'] = 'Moodleoverflow';
$string['post_was_approved'] = 'The post was approved.';
$string['post_was_rejected'] = 'The post was rejected.';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['postincontext'] = 'See this post in context';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}. To reply click on this link: {$a->replylink}';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['postnotexist'] = 'Requested post does not exist';
$string['posts'] = 'Posts';
$string['posttomoodleoverflow'] = 'Post to forum';
$string['postupdated'] = 'Your post was updated';
$string['preferteachersmark'] = 'Prefer course owners\' marks?';
$string['privacy:anonym_discussion_name'] = 'Anonymized discussion name';
$string['privacy:anonym_post_message'] = 'This content has been deleted.';
$string['privacy:anonym_user_name'] = 'Anonymous';
$string['privacy:discussionsubscriptionpreference'] = 'You have chosen the following discussion subscription preference for this forum: "{$a->preference}"';
$string['privacy:grade'] = 'Your grade for this Moodleoverflow forum.';
$string['privacy:metadata:core_files'] = 'Moodleoverflow stores files which have been uploaded by the user to form part of a forum post.';
$string['privacy:metadata:moodleoverflow_discuss_subs'] = 'Information about the subscriptions to individual forum discussions. This includes when a user has chosen to subscribe to a discussion or to unsubscribe from one where they would otherwise be subscribed.';
$string['privacy:metadata:moodleoverflow_discuss_subs:discussion'] = 'The ID of the discussion that was subscribed / unsubscribed.';
$string['privacy:metadata:moodleoverflow_discuss_subs:preference'] = 'The start time of the subscription.';
$string['privacy:metadata:moodleoverflow_discuss_subs:userid'] = 'The ID of the user who changed the subscription settings.';
$string['privacy:metadata:moodleoverflow_discussions'] = 'Information about forum discussions. This includes which discussions a user has started.';
$string['privacy:metadata:moodleoverflow_discussions:name'] = 'The name of the discussion.';
$string['privacy:metadata:moodleoverflow_discussions:timemodified'] = 'The time when the discussion (e.g. a post) was last modified.';
$string['privacy:metadata:moodleoverflow_discussions:userid'] = 'The ID of the user who started the discussion.';
$string['privacy:metadata:moodleoverflow_discussions:usermodified'] = 'The ID of the last user who modified the discussion';
$string['privacy:metadata:moodleoverflow_grades'] = 'Information about the grade a user got for his contribution in a forum.';
$string['privacy:metadata:moodleoverflow_grades:grade'] = 'The grade the user got.';
$string['privacy:metadata:moodleoverflow_grades:moodleoverflowid'] = 'The ID of the moodleoverflow forum in which he got the grade.';
$string['privacy:metadata:moodleoverflow_grades:userid'] = 'The ID of the user who got the grade.';
$string['privacy:metadata:moodleoverflow_posts'] = 'Information about forum posts. This includes data of posts a user has written.';
$string['privacy:metadata:moodleoverflow_posts:created'] = 'The date this post was created.';
$string['privacy:metadata:moodleoverflow_posts:discussion'] = 'The ID of the discussion this post is contributing to.';
$string['privacy:metadata:moodleoverflow_posts:message'] = 'The text of this post.';
$string['privacy:metadata:moodleoverflow_posts:modified'] = 'The last date this post was modified.';
$string['privacy:metadata:moodleoverflow_posts:parent'] = 'The ID of the post this post is referring to.';
$string['privacy:metadata:moodleoverflow_posts:userid'] = 'The ID of the user who submitted this post.';
$string['privacy:metadata:moodleoverflow_ratings'] = 'Information about ratings of posts. This includes when a user has rated a post and its specific rating.';
$string['privacy:metadata:moodleoverflow_ratings:discussionid'] = 'The ID of the discussion the rated post is part of.';
$string['privacy:metadata:moodleoverflow_ratings:firstrated'] = 'The date the rating was submitted.';
$string['privacy:metadata:moodleoverflow_ratings:lastchanged'] = 'The date the rating was changed the last time.';
$string['privacy:metadata:moodleoverflow_ratings:moodleoverflowid'] = 'The ID of the Moodleoverflow forum that contains the rated post.';
$string['privacy:metadata:moodleoverflow_ratings:postid'] = 'The ID of the post that was rated.';
$string['privacy:metadata:moodleoverflow_ratings:rating'] = 'The submitted rating. 0 = neutral, 1 = negative, 2 = positive, 3 = helpful, 4 = solution';
$string['privacy:metadata:moodleoverflow_ratings:userid'] = 'The ID of the user who submitted the rating.';
$string['privacy:metadata:moodleoverflow_read'] = 'Information about read tracking of posts. This includes when posts were read by a user.';
$string['privacy:metadata:moodleoverflow_read:discussionid'] = 'The ID of the discussion the read post belongs to,';
$string['privacy:metadata:moodleoverflow_read:firstread'] = 'The date the post was read the first time.';
$string['privacy:metadata:moodleoverflow_read:lastread'] = 'The date the post was read the last time by the user.';
$string['privacy:metadata:moodleoverflow_read:postid'] = 'The ID of the post that has been read.';
$string['privacy:metadata:moodleoverflow_read:userid'] = 'The ID of the user who read the post.';
$string['privacy:metadata:moodleoverflow_subscriptions'] = 'Information about subscriptions to forums. This includes which forums a user has subscribed.';
$string['privacy:metadata:moodleoverflow_subscriptions:moodleoverflow'] = 'The ID of the Moodleoverflow forum the user has subscribed.';
$string['privacy:metadata:moodleoverflow_subscriptions:userid'] = 'The ID of the user who has subscribed a forum.';
$string['privacy:metadata:moodleoverflow_tracking'] = 'Information about the tracking of forums. This includes which forums a user does not track.';
$string['privacy:metadata:moodleoverflow_tracking:moodleoverflowid'] = 'The ID of the moodleoverflow forum that is not tracked by the user.';
$string['privacy:metadata:moodleoverflow_tracking:userid'] = 'The ID of the user who does not track the forum.';
$string['privacy:postwasread'] = 'This post was first read on {$a->firstread} and most recently read on {$a->lastread}';
$string['privacy:readtrackingdisabled'] = 'You have chosen to not track which posts that you have read within this forum.';
$string['privacy:subscribedtoforum'] = 'You are subscribed to this forum.';
$string['questioner'] = 'Questioner';
$string['questions'] = 'Questions';
$string['questions_and_posts'] = 'Questions and answers';
$string['ratedbystarter'] = 'This post was marked as the most helpful by the questioner';
$string['ratedbyteacher'] = 'This post was marked as the solution by a teacher';
$string['rateownpost'] = 'You cannot rate your own post.';
$string['ratingfailed'] = 'Rating failed. Try again.';
$string['ratingheading'] = 'Rating and reputation';
$string['ratingpreference'] = 'Display first';
$string['ratingpreference_help'] = 'Answers can be marked as solution and helpful. This option decides which of these will be pinned as the first answer of the discussion. There are 2 options:

* Heplful - A topic starter\'s helpful mark will be pinned at the top of the discussion
* Solved - A teacher\'s solution mark will be pinned at the top of the discussion';
$string['ratingtoold'] = 'Ratings can only be changed within 30 minutes after the first vote. ';
$string['re'] = 'Re:';
$string['reject'] = 'Reject';
$string['reply'] = 'Comment';
$string['replyfirst'] = 'Answer';
$string['reputation'] = 'Reputation';
$string['reputationnotnegative'] = 'Reputation just positive?';
$string['resetanonymous_warning'] = 'Are you sure? If you are in production, <b>this is most certainly a bad decision</b> because your students and teachers posted their questions and answers, believing they would remain anonymous. <br><br><b>{$a->fullanoncount}</b> forums are currently fully anonymized, and in <b>{$a->questionanoncount}</b> additional forums the questioners are anonymized.<br><br><b>In all these forums, the real names of posters will be displayed again, even in already existing posts!</b><br><br>There is no way of reverting those changes!</br>';
$string['review'] = 'Review';
$string['review_help'] = 'Select what has to be approved by a teacher before being shown to students.';
$string['review_needed'] = 'Review needed!';
$string['reviewpossibleaftertime'] = 'Review possible after (secs)';
$string['reviewpossibleaftertime_desc'] = 'A teacher cannot reject or approve a post until this amount of time (in seconds) after the creation of the post. After a teacher has approved a post, the post cannot be edited by it\'s author anymore, even if still within the maxeditingtime duration.';
$string['reviewpost'] = 'Review a post';
$string['scalefactor'] = 'Scale factor';
$string['scalefactor_help'] = 'The user rating is divided by the scale factor to obtain each user\'s grade. If the resulting grade is greater than the maximum grade, the value is limited to the specified maximum grade';
$string['scalefactorerror'] = 'Scale factor must be a positive integer different than 0';
$string['seeuserstats'] = 'View user statistics';
$string['showuserstats'] = 'Show cumulative user statistics';
$string['smallmessage'] = '{$a->user} posted in {$a->moodleoverflowname}';
$string['starterrating'] = 'Helpful';
$string['subject'] = 'Subject';
$string['subscribe'] = 'Subscribe to this forum';
$string['subscribed'] = 'Subscribed';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to post notifications.';
$string['subscribestart'] = 'Send me notifications of new posts in this forum';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this forum';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a forum it means they will receive forum post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptiontrackingheader'] = 'Subscription and tracking';
$string['switchtoauto'] = 'If you switch to the auto subscription, all enrolled users will be subscribed to this forum!';
$string['switchtooptional'] = 'If you switch to the optional subscription, all currently subscribed users will be unsubscribed from this forum!';
$string['taskcleanreadrecords'] = 'Moodleoverflow maintenance job to clean old read records';
$string['tasksenddailymail'] = 'Moodleoverflow job to send a daily mail of unread post';
$string['tasksendmails'] = 'Moodleoverflow maintenance job to send mails';
$string['taskupdategrades'] = 'Moodleoverflow maintenance job to update grades';
$string['teacherrating'] = 'Solution';
$string['there_are_no_posts_needing_review'] = 'There are no more posts in this forum that need to be reviewed.';
$string['tracking'] = 'Track';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'Read tracking enables participants to easily check which posts they have not yet seen by highlighting any new posts.

If set to optional, tracking is turned on by default but participants can turn tracking off.

If \'Allow forced read tracking\' is enabled in the site administration, then a further option is available - forced. This means that tracking is always on.';
$string['trackmoodleoverflow'] = 'Track unread posts';
$string['unknownerror'] = 'This is not expected to happen.';
$string['unreadposts'] = 'Unread posts';
$string['unreadpostsnumber'] = '{$a} unread posts';
$string['unreadpostsone'] = '1 unread post';
$string['unsubscribe'] = 'Unsubscribe from this forum';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['unsubscribediscussionlink'] = 'Unsubscribe from this discussion: {$a}';
$string['unsubscribelink'] = 'Unsubscribe from this forum: {$a}';
$string['updategrades'] = 'Update grades';
$string['upvotenotchangeable'] = 'Cannot upvote';
$string['userstatscourseactivity'] = 'Activity (coursewide)';
$string['userstatscoursereputation'] = 'Reputation (coursewide)';
$string['userstatsdownvotes'] = 'Received downvotes';
$string['userstatsforumactivity'] = 'Activity (this forum)';
$string['userstatsforumreputation'] = 'Reputation (this forum)';
$string['userstatsupvotes'] = 'Received upvotes';
$string['votescaledownvote'] = 'Reputation: Downvote';
$string['votescalehelpful'] = 'Reputation: Helpful';
$string['votescalesolved'] = 'Reputation: Solution';
$string['votescaleupvote'] = 'Reputation: Upvote';
$string['votescalevote'] = 'Reputation: Vote.';
$string['your_post_was_rejected'] = 'Your post was rejected.';
$string['your_post_was_rejected_with_reason'] = 'Your post was rejected with the following reason:';
$string['yournewtopic'] = 'Your new discussion topic';
$string['yourreply'] = 'Your reply';
