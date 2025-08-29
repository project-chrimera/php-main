<?php
/**
 * manage_quota.php
 *
 * Manage quotas of members for groups
 */

require_once __DIR__ . '/include.php';

// --- 1. Authentication check ---
$discord_id = $_SESSION['discord_id'] ?? null;
if (!$discord_id) {
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header("Location: ./login.php?url=./manage_quota.php");
        exit;
    } else {
        die("Not logged in. Please authenticate via Discord.");
    }
}

// --- 2. Role check (admin only) ---
checkDiscordRole('superAdmin');

global $db;

// --- 3. Handle form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_id'], $_POST['quota'])) {
        // Edit quota
        $db->query("UPDATE roles SET quota = ? WHERE id = ?", $_POST['quota'], $_POST['edit_id']);
        header("Location: ./manage_quota.php");
        exit;
    } elseif (isset($_POST['delete_id'])) {
        // Delete quota
        $db->query("UPDATE roles SET quota = NULL WHERE id = ?", $_POST['delete_id']);
        header("Location: ./manage_quota.php");
        exit;
    } elseif (isset($_POST['add_group_id'], $_POST['new_quota'])) {
        // Add quota to a NULL quota group
        $db->query("UPDATE roles SET quota = ? WHERE id = ?", $_POST['new_quota'], $_POST['add_group_id']);
        header("Location: ./manage_quota.php");
        exit;
    }
}


// --- 4. Fetch groups ---
$groups_with_quota = $db->fetchAll($db->query("SELECT * FROM roles WHERE quota IS NOT NULL ORDER BY name"));
$groups_without_quota = $db->fetchAll($db->query("SELECT * FROM roles WHERE quota IS NULL ORDER BY name"));

$groups_with_quota = $db->fetchAll($db->query("SELECT * FROM roles WHERE quota IS NOT NULL ORDER BY name"));

// --- 5. Handle LDAP sync ---
if (isset($_POST['ldap_sync'])) {
    $ldap = get_ldap_connection(); // your function from functions.php

    foreach ($groups_with_quota as $group) {
        $quota = $group['quota'];
        $group_id = $group['id'];

        // Get all users in this group
        $stmt = $db->query("
            SELECT u.*
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
        ", $group_id);

        $users = $db->fetchAll($stmt);

        foreach ($users as $user) {
            // Search LDAP by email
            $filter = "(mail={$user['email']})";
            $search = ldap_search($ldap, LDAP_BASE_DN, $filter);
            $entries = ldap_get_entries($ldap, $search);

            if ($entries['count'] > 0) {
                $dn = $entries[0]['dn'];
                $entry = ['quota' => $quota . 'MB'];
                if (@ldap_modify($ldap, $dn, $entry)) {
                    echo "Updated quota for {$user['email']} to {$quota}MB<br>";
                } else {
                    echo "Failed to update quota for {$user['email']}: " . ldap_error($ldap) . "<br>";
                }
            } else {
                echo "LDAP user not found for email: {$user['email']}<br>";
            }
        }
    }

    ldap_unbind($ldap);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Group Quotas</title>
    <style>
        table { border-collapse: collapse; width: 60%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #eee; }
        form { display: inline; }
    </style>
</head>
<body>
<h1>Manage Group Quotas</h1>

<h2>Existing Quotas</h2>
<table>
    <tr>
        <th>Group Name</th>
        <th>Quota (MB)</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($groups_with_quota as $group): ?>
        <tr>
            <td><?= htmlspecialchars($group['name']) ?></td>
            <td>
                <form method="POST" style="display:inline">
                    <input type="number" name="quota" value="<?= $group['quota'] ?>" min="0" required>
                    <input type="hidden" name="edit_id" value="<?= $group['id'] ?>">
                    <button type="submit">Edit</button>
                </form>
            </td>
            <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete quota?');">
                    <input type="hidden" name="delete_id" value="<?= $group['id'] ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Add Quota to Group</h2>
<?php if ($groups_without_quota): ?>
    <form method="POST">
        <select name="add_group_id" required>
            <option value="">Select group</option>
            <?php foreach ($groups_without_quota as $group): ?>
                <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['name']) ?: "[Unnamed Group ID {$group['id']}]" ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="new_quota" placeholder="Quota in MB" min="0" required>
        <button type="submit">Add Quota</button>
    </form>
<?php else: ?>
    <p>No groups without a quota.</p>
<?php endif; ?>
<!-- Add LDAP sync button -->
<h2>Sync Quotas to LDAP</h2>
<form method="POST">
    <button type="submit" name="ldap_sync" onclick="return confirm('Update all users in LDAP with current quotas?');">Sync to LDAP</button>
</form>
</body>
</html>
