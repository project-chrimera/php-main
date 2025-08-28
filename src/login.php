<?php
include("./include.php");

if(get('action') == 'logout') {
  session_destroy();
  del_cookieSession();
}

ini_set('max_execution_time', 300); //300 seconds = 5 minutes. In case if your CURL is slow and is loading too much (Can be IPv6 problem)

error_reporting(E_ALL);

if ($afterlogin = @$_GET['url']) {
     $_SESSION['afterlogin'] = $afterlogin;
}
// Start the login process by sending the user to Discord's authorization page
if(get('action') == 'login') {

  $params = array(
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => $login_url,
    'response_type' => 'code',
    'scope' => 'identify email'
  );

  // Redirect the user to Discord's authorization page
  header('Location: https://discord.com/api/oauth2/authorize' . '?' . http_build_query($params));
  die();
}

// When Discord redirects the user back here, there will be a "code" and "state" parameter in the query string
if(get('code')) {

  // Exchange the auth code for a token
  $token = apiRequest($tokenURL, array(
    "grant_type" => "authorization_code",
    'client_id' => OAUTH2_CLIENT_ID,
    'client_secret' => OAUTH2_CLIENT_SECRET,
    'redirect_uri' => 'https://www.yetanotherprojecttosavetheworld.org/login.php',
    'code' => get('code')
  ));
  $logout_token = $token->access_token;
  $_SESSION['access_token'] = $token->access_token;

  header('Location: ' . $_SERVER['PHP_SELF']);
}

if(session('access_token')) {
  $discorduser = apiRequest($apiURLBase);

  if (get_username($discorduser->id) == null) {
$_SESSION['discord_id'] = $discorduser->id;
$_SESSION['email'] = $discorduser->email;
    header('Location: ./signup.php');
    die();
  }
  
  $aUser = set_cookieSession($discorduser->id);
  $aRoles = get_roles($discorduser->id);
 if (!$aRoles) {
     discordInvite();
     $bSabePage = true;
   die();
  }
/* } else {
   if (!@$aRoles['member']) {
     #make person member
     add_roles($discorduser->id,'member');
     echo "you are now member welcome";   
     $bSabePage = true;
   }
 */

#loged in
  if($url = @$_SESSION['afterlogin']) {
     unset($_SESSION['afterlogin']);
     header("Location: $url");
     die();
   }
   header("Location: ./wordpress");
   die();

} else {
  echo '<h3>Not logged in</h3>';
  echo '<p><a href="?action=login">Log In</a></p>';
  echo '<p>your email is (currently) required to create a wordpress user account</p>';
  die();
}



?>
