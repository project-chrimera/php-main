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

    $aUser = [
        'id'            => $discorduser->id,
        'username'      => $discorduser->username,
        'discriminator' => $discorduser->discriminator,
        'avatar'        => $discorduser->avatar ? "https://cdn.discordapp.com/avatars/{$discorduser->id}/{$discorduser->avatar}.png" : null,
        'email'         => $discorduser->email,
        'roles'         => get_roles($discorduser->id),
        'session'       => set_cookieSession($discorduser->id)
    ];
}


#echo "foo";
?>
