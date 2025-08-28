<?php
session_start();
include("./include.php");

// ---------------- PRECHECK ----------------
if (!isset($_SESSION['discord_id'])) {
    header('Location: ./login.php');
    exit();
}

$discord_id = intval($_SESSION['discord_id']);
$email = $_SESSION['email'] ?? null;

if (get_banned_username($discord_id)) {
    die('It seems you are banned. Get out of here.');
}

// ---------------- ACCEPT RULES ----------------
if (!($_POST['rule1'] && $_POST['rule2'] && $_POST['rule3'])) {
    echo '<h3>Please agree to the rules:</h3>
    <form method="post">
        <input type="checkbox" name="rule1" id="rule1">
        <label for="rule1">All persons in this group shall be treated equally...</label><br>
        <input type="checkbox" name="rule2" id="rule2">
        <label for="rule2">I shall not use this community for illegal activities.</label><br>
        <input type="checkbox" name="rule3" id="rule3">
        <label for="rule3">I shall behave and respect community leadership.</label><br><br>
        <input type="submit" value="Submit">
    </form>';
    exit();
}

// ---------------- CHOOSE USERNAME ----------------
if (!isset($_POST['username'])) {
    echo '<h3>Choose a unique username (cannot be changed later):</h3>
    <form method="post">
        <input type="text" name="username" required>
        <input type="submit" value="Submit">
    </form>';
    exit();
}

$username = trim($_POST['username']);
if (!set_username($username, $discord_id, $email)) {
    echo "<p>Username invalid or already exists. Try again.</p>";
    echo '<form method="post">
            <input type="text" name="username" required>
            <input type="submit" value="Submit">
          </form>';
    exit();
}

// ---------------- ASSIGN ROLES AND OPTIONAL WORDPRESS ----------------
$roles = get_roles($discord_id);

// Discord roles
if (!isset($roles['newMember']) && !isset($roles['member'])) {
    add_roles($discord_id, 'newMember');
    del_roles($discord_id, 'invader');
    echo "<p>You are now a new member. Welcome!</p>";
}

// Optional WordPress creation
if ($wordpress_enabled) {
    $wp_result = create_wordpress_user($username, $email, "subscriber");
    if ($wp_result) {
        echo "<p>WordPress account created successfully.</p>";
    } else {
        echo "<p>Failed to create WordPress account.</p>";
    }
}

// Continue
echo '<form action="./signup.php">
        <input type="submit" value="Continue">
      </form>';
?>
