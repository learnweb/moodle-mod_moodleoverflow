# moodle-mod_moodleoverflow

This plugin enables Moodle users to create a stackoverflow-like forum.
The plugin has the same features as the moodle forum.
Additionally, users can rate posts and have an own rating score.
Users, who have started a discussion, can mark a post as helpful and teachers can mark a post as solution.


## Installation
This plugin should go into `mod/mooodleoverflow`.

## Screenshots
Moodleoverflow activity:<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/2e3634f006078e4c99a1c99d564f0795/Dashboard.png" width="320">
<br><br>

Every user can see the discussions and the posts. 
The discussion overview shows the status, among other things. Thus users can see if a discussion is already solved (green tick) or if a post is marked as helpful (orange tick).
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/352944ce0e7c06cf7844275f0e948f57/discussion_list.png" width="320">
<br><br>
Posts can be marked as helpful (orange) by the question owner or as solved (green) by a teacher. The current post is marked blue.
Additionally, everybody can vote posts up or down. Post owners can edit their posts 30 minutes after posting. Teachers can edit and delete posts from everybody without the time restriction..
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/78af33c7f0357d8c0932fab266e5134c/post.png" width="320">
<br><br>
Users can attach files. If a picture is attached, it will be displayed as image. If another file type is attached, the file will be shown but not the content.
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/91dad17b6e36e7e14fc541ad534e6be5/attachment.png" width="320">
<br><br>
A discussion can be deleted by deleting the first post.

### Students' view
Unlike teachers students can't edit or delete a post or mark it as solved.
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/b0bca7f3a7792049f869dacd6515119c/students_view.png" width="320">
<br><br>

## Settings
### Global
In the global settings you can set e.g. the number of discussions per page, the maximum attachment size or read tracking.
In addition to these settings which are the same as in the forum, you can define the amount of reputation a vote or mark gives.
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/067bfe183f41b9e3bc3fe16ddc76d618/general_settings.png" width="320">
<br><br>

### Course wide
In the course settings you can override a few settings like maximum attachment size or read tracking.
Moreover, you can decide if helpful or solved posts are displayed first and how the reputation is calucated.
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/c21638affa0d95db41cc21dbc6283c40/course_settings.png" width="320">
<br><br>
If read tracking is set to "optional" and turned on by the students, the unread posts are highlighted. 
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/acc9225626f0c6d807244c5a7f6d67a5/unread_post.png" width="320">
<br><br>

### Students
Depending on the global and course settings students can choose if they want to track posts and receive email notifications.
<br><br>
<img src="https://wiwi-gitlab.uni-muenster.de/learnweb/moodle-mod_moodleoverflow/uploads/284ff86cc544d0062ef5a26b99f8a0d9/students_settings.png" width="320">
<br><br>