<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


include($_SERVER['DOCUMENT_ROOT'] . "./config.php");
include($_SERVER['DOCUMENT_ROOT'] . "./functions.php");
include($_SERVER['DOCUMENT_ROOT'] . "./db.php");

$db = new db($dbhost, $dbuser, $dbpass, $dbname);

session_start();

if (session('access_token')) {
    $discorduser = apiRequest($apiURLBase);

    $roles = get_roles($discorduser->id); // original roles array with 'name' (and 'id' if DB has it)
    
    $roles_ids = get_roles_ids($discorduser->id);

    $aUser = [
        'id'            => $discorduser->id,
        'username'      => $discorduser->username,
        'discriminator' => $discorduser->discriminator,
        'avatar'        => $discorduser->avatar ? "https://cdn.discordapp.com/avatars/{$discorduser->id}/{$discorduser->avatar}.png" : null,
        'email'         => $discorduser->email,
        'roles'         => $roles,      // original role array with names
        'roles_ids'     => $roles_ids,  // new array with only IDs
        'session'       => set_cookieSession($discorduser->id)
    ];
    $_SESSION['user'] = $aUser;
    $_SESSION['discord_id'] = $discorduser->id;
}

?>
