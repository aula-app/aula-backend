<?php
require_once ('base_config.php'); // load base config with paths to classes etc.
require_once ('error_msg.php');

require ('functions.php'); // include Class autoloader (models)

//load helper classes
require_once ($baseHelperDir.'Crypt.php');




/* */
// Create a new Database object with the MySQL credentials
$db = new Database();
$crypt = new Crypt($cryptFile); // path to $cryptFile is currently known from base_config.php -> will be changed later to be secure
$syslog = new Systemlog ($db); // systemlog
$idea = new Idea ($db, $crypt, $syslog); //, $syslog); // instanciate group model class
$room = new Room ($db, $crypt, $syslog); // instanciate room model class
$topic = new Topic ($db, $crypt, $syslog); // instanciate topic model class



function out ($text, $form=false){ // lazy helper function
  $formstart="";
  $formend="";

  if ($form) {
    $formstart="<h2>";
    $formend="</h2>";
  }

  echo ("<br>".$formstart.$text.$formend."<br>");
}


out ("EXAMPLES FOR USING THE MODELS (in this case...ROOMS and GROUPS)",true);
/* */
// Example for adding a room in the database
/* */



$user = new User($db, $crypt, $syslog);

// create a random appendix to have different users....
$testrand = rand (100,1000);
$appendix = microtime(true).$testrand;


$ideas = array();

// Random ideas to populate the array
$randomIdeas = array(
    "Create a mobile app for productivity",
    "Design a website for online learning",
    "Develop a game with augmented reality",
    "Build a social media platform for pets",
    "Start a podcast about technology trends",
    "Write a book on personal development",
    "Invent a smart device for home automation",
    "Launch an e-commerce store for handmade crafts",
    "Organize a charity event for a good cause",
    "Create a video tutorial series on coding",
    "Design a new logo for a local business",
    "Start a YouTube channel about travel experiences",
    "Develop a fitness app with personalized workout plans",
    "Build a community forum for music enthusiasts",
    "Create an online marketplace for renting tools",
    "Write a screenplay for a short film",
    "Invent a new board game for family entertainment",
    "Organize a virtual conference on sustainability",
    "Develop a language learning app with interactive lessons",
    "Start a blog about healthy cooking recipes",
    "Design a fashion line using sustainable materials",
    "Build a platform for connecting freelance writers with clients",
    "Create a podcast network featuring various genres",
    "Develop a virtual reality game for educational purposes",
    "Start a mentoring program for aspiring entrepreneurs",
    "Build an app for tracking personal finances",
    "Design a website for connecting pet owners with pet sitters",
    "Create an online course on digital marketing",
    "Develop a mobile app for finding local hiking trails",
    "Start a photography business specializing in portrait shots",
    "Build a platform for connecting language exchange partners",
    "Create a social networking app for book lovers",
    "Design a travel itinerary planner with personalized recommendations",
    "Start a web development agency for small businesses",
    "Develop a mindfulness meditation app",
    "Build a platform for online tutoring sessions",
    "Create a virtual reality experience for exploring historical landmarks",
    "Design a subscription box service for self-care products",
    "Start a podcast about personal finance and investment strategies",
    "Build a mobile app for tracking daily habits and goals",
    "Develop a platform for sharing and discovering local artwork",
    "Create a website for connecting volunteers with nonprofit organizations",
    "Design a mobile game based on solving puzzles",
    "Start an online store selling eco-friendly clothing",
    "Build a platform for connecting musicians for collaboration",
    "Develop a meditation app for children",
    "Create an online platform for personalized fitness coaching",
    "Design a mobile app for finding the best deals and discounts",
    "Start a YouTube channel about DIY home improvement projects",
    "Build a platform for sharing and discovering new music",
    "Develop a virtual reality training program for medical students",
    "Create a social networking app for connecting outdoor enthusiasts",
    "Design a website for sharing travel experiences and tips",
    "Start a podcast about mental health and self-care",
    "Build a platform for connecting remote freelance designers with clients",
    "Develop a mobile app for learning musical instruments",
    "Create an online store for handmade jewelry",
    "Design a mobile game that promotes environmental awareness",
    "Start a blog about sustainable living and zero-waste lifestyle",
    "Build a platform for sharing and discovering healthy recipes",
    "Develop a language translation app with real-time voice recognition",
    "Create a social networking app for connecting professionals in a specific industry",
    "Design a website for booking and reviewing local services",
    "Start a podcast about the history of art",
    "Build a platform for connecting travelers with local tour guides",
    "Develop a mobile app for practicing mindfulness and meditation",
    "Create an online marketplace for second-hand goods",
    "Design a mobile game that helps improve memory and cognitive skills",
    "Start a blog about personal development and motivation",
    "Build a platform for sharing and discovering short stories",
    "Develop a mobile app for managing personal budget and expenses",
    "Create a social networking app for connecting food enthusiasts",
    "Design a website for finding and booking vacation rentals",
    "Start a podcast about current trends in technology and innovation",
    "Build a platform for connecting freelance photographers with clients",
    "Develop a language learning app focused on slang and colloquial expressions",
    "Create an online store for eco-friendly household products",
    "Design a mobile game that teaches coding concepts",
    "Start a blog about sustainable fashion and ethical clothing brands",
    "Build a platform for sharing and discovering travel itineraries",
    "Develop a mobile app for mental health tracking and support",
    "Create a social networking app for connecting artists and art enthusiasts",
    "Design a website for sharing and reviewing local restaurants",
    "Start a podcast about personal stories of overcoming adversity",
    "Build a platform for connecting pet owners with veterinarians",
    "Develop a mobile app for learning and practicing yoga",
    "Create an online marketplace for digital artwork",
    "Design a mobile game that promotes physical fitness and exercise",
    "Start a blog about personal finance and money-saving tips",
    "Build a platform for sharing and discovering inspirational quotes",
    "Develop a mobile app for finding and joining local sports activities",
    "Create a social networking app for connecting music producers and artists",
    "Design a website for sharing and reviewing movies and TV shows",
    "Start a podcast about science and technology advancements",
    "Build a platform for connecting remote freelance writers with clients",
    "Develop a language learning app focused on conversational skills",
    "Create an online store for organic and natural beauty products",
    "Design a mobile game that promotes environmental conservation",
    "Start a blog about healthy eating and plant-based diets",
    "Build a platform for sharing and discovering workout routines",
    "Develop a mobile app for finding and attending local art exhibitions",
    "Create a social networking app for connecting gamers",
    "Design a website for sharing and discussing current news topics",
    "Start a podcast about entrepreneurship and startup success stories",
    "Build a platform for connecting remote freelance developers with clients",
    "Develop a mobile app for learning and practicing meditation",
    "Create an online marketplace for handmade home decor",
    "Design a mobile game that teaches basic math skills",
    "Start a blog about travel photography and adventure destinations",
    "Build a platform for sharing and discovering healthy meal recipes",
    "Develop a language learning app focused on business vocabulary",
    "Create a social networking app for connecting fashion designers and enthusiasts",
    "Design a website for sharing and reviewing books",
    "Start a podcast about psychology and self-improvement",
    "Build a platform for connecting remote freelance marketers with clients",
    "Develop a mobile app for learning and practicing drawing",
    "Create an online store for personalized gifts",
    "Design a mobile game that promotes mental agility and problem-solving",
    "Start a blog about sustainable gardening and urban farming",
    "Build a platform for sharing and discovering motivational quotes",
    "Develop a mobile app for finding and participating in local volunteer opportunities"
    // Add more random ideas as needed
);

// Check if there are enough random ideas to fill the array
if (count($randomIdeas) < 100) {
    echo "Not enough random ideas available.";
    exit();
}

// Fill the database with another 100 different random ideas
$randomIndexes = array_rand($randomIdeas, 100);
$i=0;
foreach ($randomIndexes as $index) {
  $content = $randomIdeas[$index];
  $i++;
  $room_id = rand (1,10);
  $user_id = $i;
  $status=1;

    // uncomment to add more ideas to DB
    //$idea->addIdea ($content, $user_id, $status, $order_importance=10, $updater_id=0, $votes_available_per_user=1, $info="", $room_id);
    $ideas[] = $randomIdeas[$index];
}
// Get content of single idea
$idea_id = 5;
$result =  $idea->getIdeaContent ($idea_id)['data'];
echo ("<br>Idea #".$idea_id." ".$crypt->decrypt ($result['displayname']).": ".$crypt->decrypt ($result['content'])." (".$result['created'].")");

$offset = 0; // set start at dataset #10
$limit = 5; // get 10 datasets
out ("Reading multiple idea datasets (only active, status = 1) <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$ideadata = $idea->getIdeas($offset, $limit, 4, 1, 1);
// idea list:
echo ("found a total of ".$ideadata['count']." datasets.");
foreach ($ideadata['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Idea: " . $crypt->decrypt ($result['content']));
    out ("Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}

$offset = 0; // set start at dataset #0
$limit = 5; // get 5 datasets
$room_id = 2;
// function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id)
out ("Reading multiple idea datasets (of a certain room#".$room_id.") <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$ideadata = $idea->getIdeasByRoom($offset, $limit, 4, 1, 1, $room_id);
out ("Total datasets: ".$ideadata ['count']);
// idea list:
if ($ideadata['success']){
  foreach ($ideadata['data'] as $result) {
      out ("ID: " . $result['id']);
      out ("Name: " . $crypt->decrypt ($result['displayname']));
      out ("Idea: " . $crypt->decrypt ($result['content']));
      out ("Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes']));
      out ("Last Update: " . $result['last_update']);
      out ("created: " . $result['created']);
  }
} else {
  out ("No ideas found for room ".$room_id);
}

$offset = 0; // set start at dataset #0
$limit = 5; // get 5 datasets
$user_id = 1;
// function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id)
out ("Reading multiple idea datasets (of a certain user#".$user_id.") <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$ideadata = $idea->getIdeasByUser($offset, $limit, 4, 1, 1, $user_id);
out ("Total datasets: ".$ideadata ['count']);
// idea list:
foreach ($ideadata['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Idea: " . $crypt->decrypt ($result['content']));
    out ("Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
    out ("room #: " . $result['room_id']);
}


$offset = 0; // set start at dataset #0
$limit = 5; // get 5 datasets
$group_id = 9;
// function getIdeasByRoom ($offset, $limit, $orderby=3, $asc=0, $status=1, $room_id)
out ("Reading multiple idea datasets (of a certain group#".$group_id.") <br>using Idea class with limit ".$offset.",".$limit." ordered by id (4) Ascending (1)...",true);
$ideadata = $idea->getIdeasByGroup($offset, $limit, 4, 1, 1, $group_id);
// idea list:
out ("Total datasets: ".$ideadata ['count']);
foreach ($ideadata['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Idea: " . $crypt->decrypt ($result['content']));
    out ("Sum Votes: " . ($result['sum_votes']).", Sum Likes: " . ($result['sum_likes']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
    out ("room #: " . $result['room_id']);
}


// reporting feature
$idea_id=5;
$user_id=4;
$updater_id=42;
$reason = "this idea is scandalous";
out ("Report an idea #".$idea_id." by the user #".$user_id." for the reason: ".$reason."<br>using Idea class",true);

$retvalue = $idea->reportIdea ($idea_id, $user_id, $updater_id, $reason);
out ("return code:".$retvalue ['error_code']." data:".$retvalue ['data']);

// add a vote to an idea
$idea_id=7;
$user_id=4;
$user_id_target=9;
$room_id =4;

$updater_id=43;
$vote_value=-1;
/* out ("Vote for an idea #".$idea_id." by the user #".$user_id." vote value: ".$vote_value."<br>using Idea class",true);
// voteForIdea($idea_id, $vote_value, $user_id, $updater_id=0)
$retvalue = $idea->voteForIdea ($idea_id, $vote_value, $user_id, $updater_id);
out ("return code:".$retvalue);
*/
// add a vote to an idea
$idea_id=7;
$user_id=3;
$user_id_target=9;
$room_id =4;

$updater_id=43;
$vote_value=-1;
/*
out ("Try vote for an idea #".$idea_id." by the user #".$user_id." vote value: ".$vote_value."<br>using Idea class",true);
// voteForIdea($idea_id, $vote_value, $user_id, $updater_id=0)
$retvalue = $idea->voteForIdea ($idea_id, $vote_value, $user_id, $updater_id);
out ("return code:".$retvalue ['data']);
*/
// revoking vote
out ("Revoke Vote from an idea #".$idea_id." by the user #".$user_id."<br>using Idea class",true);
$retvalue = $idea->RevokeVoteFromIdea($idea_id, $user_id, $updater_id=0);
out ("return code:".$retvalue ['error_code']." ".$retvalue ['data']);

$topic_id = 4;
// delegating vote
out ("Delegating voting right from user #".$user_id." to ".$user_id_target."<br to >using User class",true);
$retvalue = $user->delegateVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue ['data']);

// delegating vote
out ("Revoking delegation right from user #".$user_id_target." back to ".$user_id."<br>using User class",true);
$retvalue = $user->revokeVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue ['data']);

$user_id_target = 15;
$topic_id = 4;
// delegating vote
out ("Delegating voting right from user #".$user_id." to ".$user_id_target."<br to >using User class",true);
$retvalue = $user->delegateVoteRight($user_id, $user_id_target, $room_id, $topic_id, $updater_id=0);
out ("return code:".$retvalue['data']);

// getting delegtaions
out ("Getting received delegations user #".$user_id_target." for room ".$room_id."<br to >using User class",true);

$users = $user->getReceivedDelegations ($user_id_target, $room_id);
foreach ($users['data'] as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("email: " . $crypt->decrypt ($result['email']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}
// print_r ($users);

// getting delegtaions
out ("Getting given delegations user #".$user_id." for room ".$room_id."<br to >using User class",true);

$users = $user->getGivenDelegations ($user_id, $room_id);
$userdata = $users ['data'];

foreach ($userdata as $result) {
    out ("ID: " . $result['id']);
    out ("Name: " . $crypt->decrypt ($result['displayname']));
    out ("Email: " . $crypt->decrypt ($result['email']));
    out ("Last Update: " . $result['last_update']);
    out ("created: " . $result['created']);
}
//print_r ($users);

out ("User #".$user_id." follows ".$user_id_target."<br to >using User class",true);
$retvalue = $user->followUser($user_id, $user_id_target);
out ("return code:".$retvalue['data']);


$user_id = 4;
out ("User #".$user_id." blocks ".$user_id_target."<br to >using User class",true);
$retvalue = $user->blockUser($user_id, $user_id_target, 1, 42, 3);
out ("return code:".$retvalue['data']);

$user_id = 3;
out ("User #".$user_id." unfollows ".$user_id_target." <br to >using User class",true);
$retvalue = $user->unfollowUser($user_id, $user_id_target);
out ("return code:".$retvalue['count']);


?>
