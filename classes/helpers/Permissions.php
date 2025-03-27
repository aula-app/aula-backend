<?php

require_once('../base_config.php'); // load base config with paths to classes etc.
require_once('../error_msg.php');
require_once($baseHelperDir . 'Crypt.php');

function checkPermissions($model_name, $model, $method, $arguments, $user_id, $userlevel, $roles, $user_hash)
{
  $roles_map = [
    10 => "guest",
    20 => "user",
    30 => "moderator",
    31 => "moderator_v",
    40 => "super_moderator",
    41 => "super_moderator_v",
    44 => "principal",
    45 => "principal_v",
    50 => "admin",
    60 => "tech_admin"
  ];

  $all_models = [
    "Text",
    "Topic",
    "Idea",
    "Comment",
    "Command",
    "Converters",
    "Settings",
    "Group",
    "Media",
    "Message",
    "Room",
    "User"
  ];

  $permissions_table = [
    "Comment" => [
      "getCommentsByIdeaId" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "idea_id",
          "get_room_method" => "getRoomByIdea"
        ]
      ],

      "addComment" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "idea_id",
          "get_room_method" => "getRoomByIdea"
        ],
        # TODO: Verify updater_id
        "checks" => [
          "user_id:user_id",
        ]
      ],

      "getLikeStatus" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "comment_id",
        ],
      ],

      "CommentAddLike" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "comment_id",
        ],
        "checks" => [
          "user_id:user_id",
        ]
      ],

      "CommentRemoveLike" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "comment_id",
        ],
        "checks" => [
          "user_id:user_id",
        ]
      ],

      "editComment" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "comment_id",
        ],
        "owner" => ["comment_id"],
      ],

      "deleteComment" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "user",
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "comment_id",
        ],
        "owner" => ["comment_id"],
      ],

    ],

    "Media" => [
      "userAvatar" => [
        "roles" => ["all"]
      ]
    ],

    "Command" => [
      "getCommands" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "deleteCommand" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "getCommandBaseData" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "getDueCommands" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "addCommand" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "setActiveStatus" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "setCommandStatus" => [
        "open_roles" => ["admin", "tech_admin"]
      ],

      "setCommandDate" => [
        "open_roles" => ["admin", "tech_admin"]
      ],
    ],

    "Settings" =>
      [
        "setWorkdays" => [
          "roles" => ["principal", "principal_v", "admin", "tech_admin"]
        ],

        "setDailyStartTime" => [
          "roles" => ["principal", "principal_v", "admin", "tech_admin"]
        ],

        "setDailyEndTime" => [
          "roles" => ["principal", "principal_v", "admin", "tech_admin"]
        ],

        "setQuorum" => [
          "roles" => ["principal", "principal_v", "admin", "tech_admin"]
        ],

        "getGlobalConfig" => [
          "roles" => ["all"]
        ],

        "getInstanceSettings" => [
          "roles" => ["all"]
        ],

        "getQuorum" => [
          "roles" => ["all"]
        ],

        "setOauthStatus" => [
          "open_roles" => ["admin", "tech_admin"]
        ],

        "setAllowRegistration" => [
          "open_roles" => ["admin", "tech_admin"]
        ],

        "setInstanceOnlineMode" => [
          "open_roles" => ["admin", "tech_admin"]
        ],

        "setInstanceInfo" => [
          "roles" => ["admin", "tech_admin"]
        ]
      ],
    "Converters" => [
      "getGlobalPhaseDurations" => [
        "roles" => ["all"]
      ],
      "createDBDump" => [
        "roles" => ["admin", "tech_admin"]
      ]
    ],

    "Text" => [

      # TODO: Check if it's correct
      "getTextBaseData" => [
        "roles" => [
          "all"
        ]
      ],

      "getTexts" => [
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ]
      ],

      "addText" => [
        "roles" => [
          "admin"
        ]
      ],

      "editText" => [
        "roles" => [
          "admin"
        ]
      ],

      "deleteText" => [
        "roles" => [
          "admin"
        ]
      ],

      "setTextStatus" => [
        "roles" => [
          "all"
        ],
        "checks" => ["user_id:updater_id"]
      ],

    ],

    "Room" => [
      "addRoom" => [
        "roles" => ["admin"]
      ],

      "deleteRoom" => [
        "roles" => ["admin"]
      ],

      "editRoom" => [
        "roles" => ["admin"]
      ],

      "getRooms" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin",
          "tech_admin"
        ]
      ],

      "getRoomsByUser" => [
        "open_roles" => ["principal", "principal_v", "admin"],
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "getRoomBaseData" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "from_room" => ["arg" => "room_id"]
      ],

      "getUsersInRoom" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "from_room" => ["arg" => "room_id"]
      ]

    ],

    "User" => [
      "getPossibleDelegations" => [
        "open_roles" => ["admin"],
        "roles" => [
          "user",
          "moderator_v",
          "super_moderator_v",
          "principal_v",
        ],
        "from_room" => ["arg" => "room_id"],
        "checks" => ["user_id:user_id"]
      ],

      "getUsersByRoom" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
      ],

      "addCSV" => [
        "open_roles" => [
          "admin"
        ]
      ],

      "refresh_token" => [
        "roles" => ["all"]
      ],

      "getDelegationStatus" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "getReceivedDelegations" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "setUserRoles" => [
        "open_roles" => ["admin"]
      ],

      "addUserRole" => [
        "open_roles" => ["admin"]
      ],

      "delegateVoteRight" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "giveBackAllDelegations" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "getMissingConsents" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      "giveConsent" => [
        "roles" => ["all"],
        "checks" => ["user_id:user_id"]
      ],

      # TODO: This need to be fixed on the frontend
      "getUsers" => [
        "roles" => [
          "all"
        ]
      ],

      "addUser" => [
        "roles" => [
          "admin"
        ]
      ],

      "editUser" => [
        "roles" => [
          "admin"
        ]
      ],

      "setUserAbout" => [
        "open_roles" => [
          "admin",
          "tech_admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin",
          "tech_admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "setUserDisplayname" => [
        "open_roles" => [
          "admin",
          "tech_admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin",
          "tech_admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "setUserRealname" => [
        "roles" => [
          "admin",
          "tech_admin"
        ]
      ],

      "setUserEmail" => [
        "roles" => [
          "admin",
          "tech_admin"
        ]
      ],

      "setUserUsername" => [
        "roles" => [
          "admin",
          "tech_admin"
        ]
      ],

      "deleteUser" => [
        "roles" => [
          "admin"
        ]
      ],

      "getUserRooms" => [
        "open_roles" => [
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "getUserGroups" => [
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "getUserBaseData" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin",
        ],
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "getUserGDPRData" => [
        "roles" => [
          "guest",
          "user",
          "moderator",
          "moderator_v",
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "checks" => ["user_id:user_id"]
      ],

      "addUserToRoom" => [
        "roles" => [
          "admin"
        ]
      ],

      "removeUserFromRoom" => [
        "roles" => [
          "admin"
        ]
      ],

      "addUserToGroup" => [
        "roles" => [
          "admin"
        ]
      ],

      "removeUserFromGroup" => [
        "roles" => [
          "admin"
        ]
      ],

    ],

    "Message" => [
      "getMessagesByUser" => [
        "checks" => ["user_id:user_id"]
      ],

      "getPersonalMessagesByUser" => [
        "checks" => ["user_id:user_id"]
      ],

      # TODO: Check if it's correct
      "getMessages" => [
        "roles" => ["all"]
      ],

      "addMessage" => [
        "roles" => ["all"]
      ],

      "editMessage" => [
        "roles" => ["all"]
      ],

      "setMessageStatus" => [
        "roles" => ["admin"]
      ]

    ],

    "Group" => [
      "getGroups" => [
        "open_roles" => [
          "principal",
          "principal_v",
          "admin",
          "tech_admin"
        ]
      ],
      "addGroup" => [
        "roles" => [
          "principal",
          "principal_v",
          "admin"
        ]
      ],
      "editGroup" => [
        "roles" => [
          "principal",
          "principal_v",
          "admin"
        ]
      ],
      "deleteGroup" => [
        "roles" => [
          "principal",
          "principal_v",
          "admin"
        ]
      ]
    ],

    "Topic" => [
      "getTopicBaseData" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => ["all"],
        "from_room" => [
          "get_room" => "topic_id"
        ]
      ],
      "getTopics" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ]
      ],

      "addTopic" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "arg" => "room_id"
        ]
      ],

      "editTopic" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "topic_id"
        ]
      ],

      "deleteTopic" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => [
          "moderator",
          "moderator_v"
        ],
        "from_room" => [
          "get_room" => "topic_id"
        ]

      ],

      "getTopicsByPhase" => [
        "open_roles" => [
          "super_moderator",
          "super_moderator_v",
          "principal",
          "principal_v",
          "admin"
        ],
        "roles" => ["all"],
        "from_room" => [
          "arg" => "room_id"
        ]
      ],

    ],

    "Idea" =>
      [
        "setToWinning" => [
          "open_roles" => ["super_moderator", "super_moderator_v", "principal", "principal_v", "admin"],
          "roles" => ["moderator"],
          "from_room" => [
            "get_room" => "idea_id",
          ],
        ],

        "setToLosing" => [
          "open_roles" => ["super_moderator", "super_moderator_v", "principal", "principal_v", "admin"],
          "roles" => ["moderator"],
          "from_room" => [
            "get_room" => "idea_id",
          ],
        ],

        "addSurvey" => [
          "roles" => [
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "setApprovalStatus" => [
          "roles" => [
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "getCategories" => [
          "roles" => ["all"]
        ],

        "getIdeas" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "editIdea" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin",
          ],
          "roles" => [
            "user",
            "moderator",
            "moderator_v"
          ],
          "from_room" => [
            "get_room" => "idea_id",
          ],
          "owner" => ["idea_id"],
          "checks" => ["user_id:updater_id"]
        ],

        "deleteIdea" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "user",
            "moderator",
            "moderator_v"
          ],
          "from_room" => [
            "get_room" => "idea_id",
          ],
          "owner" => ["idea_id"],
          "checks" => ["user_id:updater_id"]
        ],

        "getLikeStatus" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
          "checks" => [
            "user_id:user_id"
          ]
        ],

        "getVoteValue" => [
          "open_roles" => [
            "super_moderator_v",
            "principal_v",
          ],
          "roles" => [
            "guest",
            "user",
            "moderator_v",
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
          "checks" => [
            "user_id:user_id"
          ]
        ],

        "addIdeaToCategory" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin",
            "owner"
          ],
          "roles" => [
            "user",
            "moderator",
            "moderator_v"
          ],
          "owner" => "idea_id"
        ],

        "IdeaAddLike" => [
          "roles" => [
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
          "checks" => [
            "user_id:user_id"
          ]
        ],

        "addIdeaToTopic" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "moderator",
            "moderator_v",
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
        ],

        "removeIdeaFromTopic" => [
          "open_roles" => [
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
        ],

        "voteForIdea" => [
          "open_roles" => [
            "super_moderator_v",
            "principal_v",
          ],
          "roles" => [
            "user",
            "moderator_v",
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
          "checks" => [
            "user_id:user_id"
          ]
        ],

        "IdeaRemoveLike" => [
          "roles" => [
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ],
          "checks" => [
            "user_id:user_id"
          ]
        ],

        "removeIdeaFromCategory" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin",
            "owner"
          ],
          "owner" => "idea_id"
        ],

        "getIdeasByRoom" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v"
          ],
          "from_room" => ["arg" => "room_id"]
        ],

        "getIdeaBaseData" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ]
        ],

        "getIdeaVoteStats" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ]
        ],

        "getIdeaTopic" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v",
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "from_room" => [
            "get_room" => "idea_id"
          ]
        ],

        "getIdeasByTopic" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "guest",
            "user",
            "moderator",
            "moderator_v"
          ],
          "from_room" => [
            "get_room" => "topic_id",
            "get_room_method" => "getTopicRoom"
          ]
        ],

        "addCategory" => [
          "roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "editCategory" => [
          "roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "deleteCategory" => [
          "roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ]
        ],

        "getUpdatesByUser" => [
          "checks" => ["user_id:user_id"]
        ],

        "getDashboardByUser" => [
          "checks" => ["user_id:user_id"]
        ],

        "getWildIdeasByUser" => [
          "checks" => ["user_id:user_id"]
        ],

        "addIdea" => [
          "open_roles" => [
            "super_moderator",
            "super_moderator_v",
            "principal",
            "principal_v",
            "admin"
          ],
          "roles" => [
            "user",
            "moderator",
            "moderator_v",
          ],
          "checks" => ["user_id:user_id", "user_id:updater_id"],
          "from_room" => ["arg" => "room_id"]
        ]
      ]
  ];

  if (in_array($model_name, $all_models)) {
    if (!in_array($model_name, array_keys($permissions_table))) {
      return ["allowed" => false];
    }

    $all_checks = [];

    if (in_array($method, array_keys($permissions_table[$model_name]))) {
      # check open roles
      if (in_array("open_roles", array_keys($permissions_table[$model_name][$method]))) {
        if (in_array($roles_map[$userlevel], $permissions_table[$model_name][$method]["open_roles"])) {
          return ["allowed" => true];
        }
      }

      # check ownership
      if (in_array("owner", array_keys($permissions_table[$model_name][$method]))) {
        $isOwner = $model->isOwner($user_id, $arguments[$permissions_table[$model_name][$method]["owner"][0]]);
        array_push($all_checks, $isOwner);
      }

      # check roles
      if (in_array("roles", array_keys($permissions_table[$model_name][$method]))) {
        if (in_array("all", $permissions_table[$model_name][$method]["roles"])) {
          array_push($all_checks, true);
        } else {
          if (in_array($roles_map[$userlevel], $permissions_table[$model_name][$method]["roles"])) {
            array_push($all_checks, true);
          }
        }
      }

      # check arguments
      if (in_array("checks", array_keys($permissions_table[$model_name][$method]))) {
        $checks = $permissions_table[$model_name][$method]["checks"];

        $total_checks = 0;
        foreach ($checks as $c) {
          $cparts = explode(":", $c);
          if (${$cparts[0]} == $arguments[$cparts[1]]) {
            $total_checks += 1;
          }
        }
        if (count($checks) == $total_checks) {
          array_push($all_checks, true);
        } else {
          return ["allowed" => false];
        }
      }

      # check user room
      if (in_array("from_room", array_keys($permissions_table[$model_name][$method]))) {
        if (in_array("arg", array_keys($permissions_table[$model_name][$method]["from_room"]))) {
          $request_room_id = $arguments[$permissions_table[$model_name][$method]["from_room"]["arg"]];
          $user_roles_in_room = array_values(array_filter($roles, fn($r) => $r->room == $request_room_id));

          if (count($user_roles_in_room) > 0) {
            if (
              in_array("all", $permissions_table[$model_name][$method]["roles"])
              || in_array($roles_map[$user_roles_in_room[0]->role], $permissions_table[$model_name][$method]["roles"])
              || (in_array("open_roles", array_keys($permissions_table[$model_name][$method]))
                && in_array($roles_map[$user_roles_in_room[0]->role], $permissions_table[$model_name][$method]["open_roles"]))
            ) {
              array_push($all_checks, true);
            } else {
              array_push($all_checks, false);
            }
          } else {
            array_push($all_checks, false);
          }
        } else if (in_array("get_room", array_keys($permissions_table[$model_name][$method]["from_room"]))) {
          $get_room_method = "getRoom";
          if (in_array("get_room_method", array_keys($permissions_table[$model_name][$method]["from_room"]))) {
            $get_room_method = $permissions_table[$model_name][$method]["from_room"]["get_room_method"];
          }

          $room_hash = $model->$get_room_method($arguments[$permissions_table[$model_name][$method]["from_room"]["get_room"]]);
          $user_roles_in_room = array_values(array_filter($roles, fn($r) => $r->room == $room_hash));

          if (count($user_roles_in_room) > 0) {
            if (in_array('all', $permissions_table[$model_name][$method]["roles"])) {
              array_push($all_checks, true);
            } else if (
              in_array($roles_map[$user_roles_in_room[0]->role], $permissions_table[$model_name][$method]["roles"])
              || (in_array("open_roles", array_keys($permissions_table[$model_name][$method]))
                && in_array($roles_map[$user_roles_in_room[0]->role], $permissions_table[$model_name][$method]["open_roles"]))
            ) {
              array_push($all_checks, true);
            } else {
              array_push($all_checks, false);
            }
          } else {
            array_push($all_checks, false);
          }
        }
      }

      if (count($all_checks) > 0) {
        foreach ($all_checks as $c) {
          if (!$c)
            return ["allowed" => false];
        }
        return ["allowed" => true];
      }
    }
  }

  return ["allowed" => false];
}

?>
