<?php
require_once 'include.php';

$discord_id = $_SESSION['discord_id'] ?? null;
if (!$discord_id) die("Not logged in");

$action_id = $_GET['action_id'] ?? null;
if (!$action_id) die("Action not specified");

// Fetch action
$action = $db->fetchArray($db->query("SELECT * FROM hass_actions WHERE id = ?", $action_id));

// Check required role
$user_roles = get_roles($discord_id);
if ($action['required_role'] && !array_key_exists($action['required_role'], $user_roles)) {
    die("You do not have permission to run this action.");
}

// Fetch fields
$fields = $db->fetchAll($db->query("SELECT * FROM hass_action_fields WHERE action_id = ?", $action_id));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [];
    foreach ($fields as $f) {
        $value = $_POST[$f['name']] ?? null;
        if ($f['type'] === 'checkbox') $value = $value ? true : false;
        if ($f['type'] === 'select' && $f['options']) {
            $opts = json_decode($f['options'], true);
            if (!in_array($value, $opts)) die("Invalid selection for field {$f['label']}");
        }
        $payload[$f['name']] = $value;
    }

    // Call HA API
// If entity_id is specified in the action fields, use ha_set_entity
if (!empty($payload['entity_id'])) {
    $entity_id = $payload['entity_id'];
    $state = $payload['state'] ?? 'on'; // default 'on'
    $attributes = $payload;
    unset($attributes['entity_id'], $attributes['state']);
    $result = ha_set_entity($entity_id, $state, $attributes);
} else {
    // fallback to service call
    $result = ha_call_service($action['ha_domain'], $action['ha_service'], $payload);
}


    // Log execution
    $db->query("INSERT INTO hass_action_runs (action_id, discord_id, data) VALUES (?, ?, ?)",
        $action_id, $discord_id, json_encode($payload)
    );

    echo "<pre>Result: "; print_r($result); echo "</pre>";
}
?>

<h2><?= htmlspecialchars($action['name']) ?></h2>
<form method="POST">
<?php foreach ($fields as $f): ?>
    <label><?= htmlspecialchars($f['label']) ?></label>
    <?php if ($f['type']=='text' || $f['type']=='number'): ?>
        <input type="<?= $f['type'] ?>" name="<?= htmlspecialchars($f['name']) ?>"/><br>
    <?php elseif ($f['type']=='select'):
        $opts = json_decode($f['options'], true) ?? [];
    ?>
        <select name="<?= htmlspecialchars($f['name']) ?>">
            <?php foreach ($opts as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
        </select><br>
    <?php elseif ($f['type']=='checkbox'): ?>
        <input type="checkbox" name="<?= htmlspecialchars($f['name']) ?>" value="1"/><br>
    <?php endif; ?>
<?php endforeach; ?>
<button type="submit">Run Action</button>
</form>
