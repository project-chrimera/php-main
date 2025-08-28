<?php
/**
 * get_password.php
 *
 * Generates a random LDAP password for the user identified by Discord ID,
 * updates it in LDAP, and shows it temporarily on screen.
 */

require_once __DIR__ . '/include.php';

// --- 1. Authentication check ---
$discord_id = $_SESSION['discord_id'] ?? null;
if (!$discord_id) {
    // Prevent redirect loops
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: ./login.php?url=./get_password.php");
        exit;
    } else {
        die("Not logged in. Please authenticate via Discord.");
    }
}

// --- 2. Role check (allow multiple roles) ---
checkDiscordRole('newMember', 'member');

// --- 3. Get user email ---
$email = get_user_email_by_discord_id($discord_id);
if (!$email) {
    die("Could not find email address for this Discord ID.");
}

// --- 4. LDAP connection ---
$ldap = get_ldap_connection();
if (!$ldap) {
    die("Failed to connect to LDAP server.");
}

// --- 5. Find user entry by email ---
$search = ldap_search($ldap, LDAP_BASE_DN, "(mail=$email)");
if (!$search) {
    die("LDAP search failed: " . ldap_error($ldap));
}
$entries = ldap_get_entries($ldap, $search);
if ($entries["count"] == 0) {
    die("User with email $email not found in LDAP.");
}
$dn = $entries[0]["dn"];

// --- 6. Generate random password ---
$random_password = bin2hex(random_bytes(12)); // 24 chars hex (~96 bits entropy)

// Hash password with SSHA (LDAP standard)
function ldap_hash_password($password) {
    $salt = random_bytes(4);
    $hash = sha1($password . $salt, true) . $salt;
    return '{SSHA}' . base64_encode($hash);
}
$hashed_password = ldap_hash_password($random_password);

// --- 7. Update password in LDAP ---
$result = @ldap_mod_replace($ldap, $dn, ['userPassword' => $hashed_password]);
if (!$result) {
    $err = ldap_error($ldap);
    die("Failed to set password in LDAP for $email: $err");
}

// --- 8. Update timestamp in database ---
$db->query("UPDATE users SET last_pass_generated = NOW() WHERE discord_id = ?", $discord_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>LDAP Password Management</title>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 40px;
}
h2 {
    color: #333;
}
button {
    padding: 8px 16px;
    margin-top: 10px;
    cursor: pointer;
}
#password {
    display: none;
    font-weight: bold;
    margin-left: 10px;
    color: darkred;
}
</style>
<script>
function togglePassword() {
    const passEl = document.getElementById('password');
    if (passEl.style.display === 'none') {
        passEl.style.display = 'inline';
        setTimeout(() => passEl.style.display = 'none', 20000); // auto-hide after 20s
    } else {
        passEl.style.display = 'none';
    }
}
</script>
</head>
<body>
<h2>LDAP Password for <?= htmlspecialchars($email) ?></h2>
<p>A new password was successfully generated and stored in LDAP.</p>
<button onclick="togglePassword()">Show/Hide Password</button>
<span id="password"><?= htmlspecialchars($random_password) ?></span>
</body>
</html>

