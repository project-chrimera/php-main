<?php 
function discordInvite() {
$invite="https://discord.gg/D3xwCnt86Y";
echo "<p>Please join the discord server to continue<br> <a href=\"$invite\" target=\"_blank\">yap2stw discord</a> member role after joining</p>";

}
function apiRequest($url, $post=FALSE, $headers=array()) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

  $response = curl_exec($ch);


  if($post)
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));

  $headers[] = 'Accept: application/json';

  if(session('access_token'))
    $headers[] = 'Authorization: Bearer ' . session('access_token');

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  return json_decode($response);
}

function get($key, $default=NULL) {
  return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

function session($key, $default=NULL) {
  return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
}


function get_ldap_connection() {
    $ldap = ldap_connect(LDAP_HOST);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (!ldap_bind($ldap, LDAP_ADMIN_DN, LDAP_ADMIN_PASS)) {
        die("LDAP bind failed");
    }
    return $ldap;
}

#Page access function

/*function checkDiscordRole(string $requiredRole): bool {
    global $apiURLBase;
    global $_SESSION;

    if (session('access_token')) {
        $discorduser = apiRequest($apiURLBase);

        if (get_username($discorduser->id) === null) {
            $_SESSION['discord_id'] = $discorduser->id;
            $_SESSION['email'] = $discorduser->email;
            header('Location: ./signup.php');
            die();
        } else {
            $_SESSION['discord_id'] = $discorduser->id;
            $_SESSION['email'] = $discorduser->email;

            $aUser = set_cookieSession($discorduser->id);
            $aRoles = get_roles($discorduser->id);

             return array_key_exists($requiredRole, $aRoles);
        }
    }

 return false;
}
*/
/**
 * Check if the current Discord user has at least one of the required roles.
 * Usage: checkDiscordRole('role1', 'role2', 'role3');
 */
function checkDiscordRole(string ...$requiredRoles): bool {
    global $apiURLBase;
    global $_SESSION;

    if (session('access_token')) {
        $discorduser = apiRequest($apiURLBase);

        if (get_username($discorduser->id) === null) {
            $_SESSION['discord_id'] = $discorduser->id;
            $_SESSION['email'] = $discorduser->email;
          #  header('Location: ./signup.php');
            die();
        } else {
            $_SESSION['discord_id'] = $discorduser->id;
            $_SESSION['email'] = $discorduser->email;

            $aUser = set_cookieSession($discorduser->id);
            $aRoles = get_roles($discorduser->id);

            // Check if user has any of the required roles
            foreach ($requiredRoles as $role) {
                if (array_key_exists($role, $aRoles)) {
                    return true;
                }
            }
            return false;
        }
    }

    return false;
}



#cookie functions

function set_cookieSession($user_id) {
  if (!is_numeric($user_id)) {
    return false;
  }
  global $db;
  $qUser = $db->query("select * FROM `users` where `discord_id` = '$user_id' AND `banned` = 0");
  $aUser = $db->fetchArray($qUser);
  if ($aUser) {

    $cookietoken = bin2hex(random_bytes(40));
    setcookie("token", $cookietoken, time() + 30 * 24 * 60 * 60,'/','www.yetanotherprojecttosavetheworld.org');
#setcookie(name, value, expire, path, domain, 
    $sql = ("INSERT INTO `sessions` (`token`, `user_id`) VALUES ('$cookietoken', '$aUser[id]');");   
   #echo $sql;
    $db->query($sql);   
    return $aUser;
  }
  return false;
}


function get_cookieSession() {
    $token = $_COOKIE["token"];
    if (($token) && ctype_xdigit($token)) {

    global $db;
    $qSession = $db->query("select * FROM `sessions` WHERE `token` ='$token';");   
    $aSession = $db->fetchArray($qSession);
    if ($id = $aSession['user_id']) {

      $qUser = $db->query("select * FROM `users` where `id` = '$id' AND `banned` = 0");
      $aUser = $db->fetchArray($qUser);
      if ($aUser) {
        return $aUser;
      }
    }
  }
  return false;
}
function del_cookieSession() {
    $token = @$_COOKIE["token"];
    if (($token) && ctype_xdigit($token)) {

      global $db;
      $db->query("delete from `sessions` where `token` = '$token'");
      setcookie("token","", time() - 3600,'/','www.yetanotherprojecttosavetheworld.org');
      return true;
    }

    return false;
}


//EAGLE SCRIPTS
function call_api($endpoint, $token, $method = 'GET', $data = []) {
    $url = 'http://127.0.0.1:5000' . $endpoint;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Set the custom header for the API token
    $headers = [
        'X-API-Key: ' . $token,
    ];

    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'body' => json_decode($response, true),
        'http_code' => $http_code
    ];
}
/**
 * Retrieves the roles of a Discord user via the bot's API.
 *
 * @param int $user_id The Discord user's ID.
 * @param string $api_token The API key for authentication.
 * @return array|false An array of roles on success, or false on failure.
 */
function eagle_get_roles($user_id, $api_token) {
    if (!is_numeric($user_id)) {
        return false;
    }

    $endpoint = '/api/get_roles/' . $user_id;
    $response = call_api($endpoint, $api_token, 'GET');

    if ($response['http_code'] === 200 && isset($response['body']['roles'])) {
        return $response['body']['roles'];
    }

    return false;
}


/**
 * Soft-bans a Discord user via the bot's API.
 *
 * @param int|string $identifier The Discord user's ID or username.
 * @param string $api_token The API key for authentication.
 * @return array|false The API response on success, or false on failure.
 */
function eagle_soft_ban($identifier, $api_token) {
    $endpoint = '/api/soft_ban/' . urlencode($identifier);
    $response = call_api($endpoint, $api_token, 'POST');

    if ($response['http_code'] === 200 && isset($response['body']['status']) && $response['body']['status'] === 'success') {
        return $response['body'];
    }

    return false;
}


/**
 * Adds a role to a Discord user via the bot's API.
 *
 * @param int $user_id The Discord user's ID.
 * @param string $role_name The name of the role to add.
 * @param string $api_token The API key for authentication.
 * @return array|false The API response on success, or false on failure.
 */
function add_roles($user_id, $role_name, $api_token) {
    if (!is_numeric($user_id)) {
        return false;
    }

    $endpoint = '/api/add_roles/' . $user_id . '/' . urlencode($role_name);
    $response = call_api($endpoint, $api_token, 'POST');

    // Check for a successful HTTP status code and a "success" status in the body.
    if ($response['http_code'] === 200 && isset($response['body']['status']) && $response['body']['status'] === 'success') {
        return $response['body'];
    }

    return false;
}

//----------------------------------------------------------------------------------------------------

/**
 * Removes a role from a Discord user via the bot's API.
 *
 * @param int $user_id The Discord user's ID.
 * @param string $role_name The name of the role to remove.
 * @param string $api_token The API key for authentication.
 * @return array|false The API response on success, or false on failure.
 */
function del_roles($user_id, $role_name, $api_token) {
    if (!is_numeric($user_id)) {
        return false;
    }

    $endpoint = '/api/del_roles/' . $user_id . '/' . urlencode($role_name);
    $response = call_api($endpoint, $api_token, 'POST');

    if ($response['http_code'] === 200 && isset($response['body']['status']) && $response['body']['status'] === 'success') {
        return $response['body'];
    }

    return false;
}

//----------------------------------------------------------------------------------------------------

/**
 * Kicks a Discord user from the guild via the bot's API.
 *
 * @param int $user_id The Discord user's ID.
 * @param string $api_token The API key for authentication.
 * @return array|false The API response on success, or false on failure.
 */
function kick($user_id, $api_token) {
    if (!is_numeric($user_id)) {
        return false;
    }

    $endpoint = '/api/kick/' . $user_id;
    $response = call_api($endpoint, $api_token, 'POST');

    if ($response['http_code'] === 200 && isset($response['body']['status']) && $response['body']['status'] === 'success') {
        return $response['body'];
    }

    return false;
}


//DB
function get_username($user_id) {
    if (!is_numeric($user_id)) return false;
    global $db;
    $qUser = $db->query("SELECT name FROM users WHERE discord_id = ?", $user_id);
    $aUser = $db->fetchArray($qUser);
    return $aUser['name'] ?? false;
}

function get_banned_username($user_id) {
    if (!is_numeric($user_id)) return false;
    global $db;
    $qUser = $db->query("SELECT name FROM users WHERE discord_id = ? AND banned = 1", $user_id);
    $aUser = $db->fetchArray($qUser);
    return $aUser['name'] ?? false;
}

function get_user_email_by_discord_id($discord_id) {
    if (!is_numeric($discord_id)) return null;
    global $db;
    $stmt = $db->query("SELECT email FROM users WHERE discord_id = ? LIMIT 1", $discord_id);
    $row = $db->fetchArray($stmt);
    return $row['email'] ?? null;
}

function set_username($username, $user_id, $email) {
    if (!is_numeric($user_id)) return false;
    if (!preg_match('/^[A-Za-z]{1}[A-Za-z0-9\-_]{2,49}[A-Za-z0-9]{1}$/', $username)) {
        echo 'create a better username this one is not accepted';
        return false;
    }

    global $db;
    $qUser = $db->query("SELECT name FROM users WHERE UPPER(name) = UPPER(?)", $username);
    $aUser = $db->fetchArray($qUser);
    if ($aUser) {
        echo "Cant use $username that username exists";
        return false;
    }

    $db->query("INSERT INTO users (discord_id, name, email) VALUES (?, ?, ?)", $user_id, $username, $email);
    return true;
}

function get_roles($discord_id = null) {
    global $db;

    if ($discord_id !== null) {
        if (!is_numeric($discord_id)) return [];
        // Fetch roles assigned to a specific user
        $stmt = $db->query("
            SELECT r.name
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            INNER JOIN users u ON ur.user_id = u.id
            WHERE u.discord_id = ?
        ", $discord_id);
    } else {
        // Fetch all roles
        $stmt = $db->query("SELECT role_name FROM roles");
    }

    return $db->fetchAll($stmt);
}


//WORDPRESS
function create_wordpress_user($username, $email, $role = "subscriber") {
    global $wordpress_enabled, $wordpress_api_url, $wordpress_api_token;

    if (!$wordpress_enabled) {
        return false;
    }

    $post_data = json_encode([
        "username" => $username,
        "email" => $email,
        "role" => $role
    ]);

    $ch = curl_init($wordpress_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-API-Token: $wordpress_api_token"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        return json_decode($response, true); // return response as array
    } else {
        error_log("WordPress user creation failed: HTTP $httpcode - $response");
        return false;
    }
}

function call_wp_role_api($email, $role, $action = 'add') {
    global $wordpress_api_url, $wordpress_api_token;

    $url = $wordpress_api_url . ($action === 'add' ? '/add-role' : '/remove-role');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Token: ' . $wordpress_api_token
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'role'  => $role
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    echo "[DEBUG] WP {$action} role {$role} for {$email}: {$response}\n";
    return $response;
}

function call_bbpress_role_api($email, $role) {
    global $wordpress_api_url, $wordpress_api_token;

    $ch = curl_init("{$wordpress_api_url}/bbpress-set-role");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Token: ' . $wordpress_api_token
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'email' => $email,
        'role'  => $role
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    echo "[DEBUG] bbPress role set {$role} for {$email}: {$response}\n";
    return $response;
}

function get_bbpress_role($email) {
    global $wordpress_api_url, $wordpress_api_token;

    $ch = curl_init("{$wordpress_api_url}/bbpress-get-role?email={$email}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Token: ' . $wordpress_api_token
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['bbpress_role'] ?? null;
}

?>
