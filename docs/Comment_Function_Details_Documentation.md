# Functions from Comment.php

| Function Name        | Parameters                                                                                                                                    | Description                                                                                                           |
|:---------------------|:----------------------------------------------------------------------------------------------------------------------------------------------|:----------------------------------------------------------------------------------------------------------------------|
| __construct          | $db, $crypt, $syslog                                                                                                                          | only process script if variable $allowed_include is set to 1, otherwise exit this prevents direct call of this script |
| getCommentOrderId    | $orderby                                                                                                                                      | For further description look into code comments.                                                                                              |
| getCommentHashId     | $comment_id                                                                                                                                   | For further description look into code comments.                                                                                              |
| getCommentsByIdeaId  | $idea_id, $offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = 1                                                                        | For further description look into code comments.                                                                                              |
| archiveComment       | $comment_id, $updater_id = 0                                                                                                                  | For further description look into code comments.                                                                                              |
| activateComment      | $comment_id, $updater_id = 0                                                                                                                  | For further description look into code comments.                                                                                              |
| deactivateComment    | $comment_id, $updater_id                                                                                                                      | For further description look into code comments.                                                                                              |
| suspendComment       | $comment_id, $updater_id                                                                                                                      | For further description look into code comments.                                                                                              |
| setCommenttoReview   | $comment_id, $updater_id                                                                                                                      | For further description look into code comments.                                                                                              |
| getCommentBaseData   | $comment_id                                                                                                                                   | For further description look into code comments.                                                                                              |
| getCommentsByUser    | $user_id, $publish_date = 0, $idea_id = 0                                                                                                     | For further description look into code comments.                                                                                              |
| getCommentsByParent  | $user_id, $publish_date = 0, $parent_id = 0                                                                                                   | returns all comments for this specific user                                                                           |
| getCommentsToReview  | $user_id = 0, $publish_date = 0, $idea_id = 0, $parent_idea = 0                                                                               | returns all comments for this specific user                                                                           |
| getSuspendedComments | $user_id = 0, $publish_date = 0, $idea_id = 0, $parent_idea = 0                                                                               | returns all comments for this specific user                                                                           |
| getComments          | $offset = 0, $limit = 0, $orderby = 0, $asc = 0, $status = 1, $extra_where = "", $last_update = 0, $idea_id = 0, $parent_id = 0, $user_id = 0 | returns all comments for this specific user                                                                           |
| addComment           | $content, $user_id, $idea_id = 0, $parent_id = 0, $status = 1, $updater_id = 0, $language_id = 0                                              | For further description look into code comments.                                                                                              |
| getLikeStatus        | $user_id, $comment_id                                                                                                                         | For further description look into code comments.                                                                                              |
| CommentAddLike       | $comment_id, $user_id                                                                                                                         | bind all VALUES generate unique hash for this vote                                                                    |
| removeLikeUser       | $user_id, $comment_id                                                                                                                         | For further description look into code comments.                                                                                              |
| CommentRemoveLike    | $comment_id, $user_id                                                                                                                         | get vote value for this user on this idea bind all VALUES                                                             |
| reportComment        | $comment_id, $user_id, $updater_id, $reason = ""                                                                                              | For further description look into code comments.                                                                                              |
| setCommentStatus     | $comment_id, $status, $updater_id = 0                                                                                                         | For further description look into code comments.                                                                                              |
| editComment          | $comment_id, $content, $status = 1, $updater_id = 0                                                                                           | For further description look into code comments.                                                                                              |
| deleteComment        | $comment_id, $updater_id = 0                                                                                                                  | For further description look into code comments.                                                                                              |

# Summary of Functionalities


The `Comment.php` file is responsible for managing comments within the system. Key functionalities include:

- **Comment Management**:
  - Adding, updating, and deleting comments.
  - Handling comment-specific details and statuses.

- **Data Retrieval**:
  - Fetching comment-related data, such as content, author, and timestamps.

- **User Interaction**:
  - Facilitating user interaction by enabling commenting on posts or ideas.

- **Validation and Moderation**:
  - Ensuring that comments meet specific criteria and providing tools for moderation.

This file is essential for managing and organizing user feedback and discussions effectively within the application.
