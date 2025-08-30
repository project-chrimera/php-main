<?php
require_once 'include.php';
require_once 'hass_functions.php';
checkDiscordRole('member'); // Just ensure logged in
require(__DIR__ . '/wordpress/wp-load.php');
get_header();

// --- Get user roles from session ---
$user_roles_ids = $_SESSION['user']['roles_ids'] ?? [];

// --- Fetch all actions accessible by user's roles ---
$actions = $db->fetchAll($db->query("
    SELECT a.*, r.name AS role_name
    FROM hass_actions a
    LEFT JOIN roles r ON a.required_role = r.role_id
    WHERE a.required_role IS NULL OR a.required_role IN (" . implode(',', $user_roles_ids) . ")
    ORDER BY a.required_role, a.name
"));

// --- Group actions by role (or "No role") ---
$groups = [];
foreach ($actions as $a) {
    $group_name = $a['role_name'] ?: 'No Role / Public';
    if (!isset($groups[$group_name])) $groups[$group_name] = [];
    $groups[$group_name][] = $a;
}
?>

<h1>Home Assistant Actions</h1>
<p>Actions you have access to are listed below. Only groups you belong to will appear.</p>

<?php foreach ($groups as $group_name => $group_actions): ?>
    <h2><?=htmlspecialchars($group_name)?></h2>
    <?php foreach ($group_actions as $action): ?>
        <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px; background:#f9f9f9;">
            <?php hass_render_action($action['id']); ?>
        </div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php
get_footer();
?>
