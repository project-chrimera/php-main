<?php
require_once 'include.php';
checkDiscordRole('hassAdmin'); // Only superAdmins can manage actions
require(__DIR__ . '/wordpress/wp-load.php');
get_header();

// --- Handle creating reusable items ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_item'])) {
    $name = trim($_POST['item_name']);
    $type = $_POST['item_type'];
    $options = $_POST['item_options'] ?: null;

    if ($options) {
        $options = json_encode(array_map('trim', explode(',', $options)));
    }

    $db->query("INSERT INTO hass_items (name,type,options) VALUES (?,?,?)", $name, $type, $options);
    echo "<p>Item '$name' created successfully!</p>";

echo "<p>Action saved. Redirecting in 2 seconds...</p>";
echo '<script>
    setTimeout(function() {
        window.location.href = "hass_manager.php";
    }, 2000);
</script>';
get_footer();
die();
}

// --- Handle creating or editing actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_action']) || isset($_POST['edit_action']))) {
    $name = trim($_POST['name']);
    $ha_domain = trim($_POST['ha_domain']);
    $ha_service = trim($_POST['ha_service']);
    $required_role = $_POST['required_role'] ?: null;
    $static_attributes = $_POST['static_attributes'] ?: null;
    $description = $_POST['description'] ?: null;

    if (isset($_POST['create_action'])) {
        $db->query(
            "INSERT INTO hass_actions (name, ha_domain, ha_service, required_role, static_attributes, description) VALUES (?,?,?,?,?,?)",
            $name, $ha_domain, $ha_service, $required_role, $static_attributes, $description
        );
        $action_id = $db->lastInsertID();
        echo "<p>Action '$name' created successfully!</p>";
    } else { // Edit
        $action_id = $_POST['action_id'];
        $db->query(
            "UPDATE hass_actions SET name=?, ha_domain=?, ha_service=?, required_role=?, static_attributes=?, description=? WHERE id=?",
            $name, $ha_domain, $ha_service, $required_role, $static_attributes, $description, $action_id
        );
        $db->query("DELETE FROM hass_action_fields WHERE action_id=?", $action_id);
        echo "<p>Action '$name' updated successfully!</p>";
    }

    // Bind selected items with parameter names
    foreach ($_POST['items'] as $item_id => $data) {
        if (!empty($data['use']) && !empty($data['param'])) {
            $db->query(
                "INSERT INTO hass_action_fields (action_id, item_id, parameter_name) VALUES (?,?,?)",
                $action_id, $item_id, $data['param']
            );
        }
    }
echo "<p>Action saved. Redirecting in 2 seconds...</p>";
echo '<script>
    setTimeout(function() {
        window.location.href = "hass_manager.php";
    }, 2000);
</script>';
get_footer();
die();

}

// --- Handle deleting actions ---
if (isset($_GET['delete_action'])) {
    $action_id = (int)$_GET['delete_action'];
    $db->query("DELETE FROM hass_actions WHERE id=?", $action_id);
    $db->query("DELETE FROM hass_action_fields WHERE action_id=?", $action_id);
    echo "<p>Action deleted successfully!</p>";

echo "<p>Action saved. Redirecting in 2 seconds...</p>";
echo '<script>
    setTimeout(function() {
        window.location.href = "hass_manager.php";
    }, 2000);
</script>';
get_footer();
die();
}

// --- Fetch items and roles for dropdowns ---
$items = $db->fetchAll($db->query("SELECT * FROM hass_items ORDER BY name"));
$roles = $db->fetchAll($db->query("SELECT id,name,role_id FROM roles"));

// --- Fetch existing actions for table ---
$actions = $db->fetchAll($db->query("SELECT * FROM hass_actions ORDER BY id DESC"));

// --- Load action for editing ---
$edit_action = null;
$edit_fields = [];
if (isset($_GET['edit_action'])) {
    $edit_action = $db->query("SELECT * FROM hass_actions WHERE id=?", (int)$_GET['edit_action'])->fetchArray();
    if ($edit_action) {
        $edit_fields = $db->fetchAll($db->query("SELECT * FROM hass_action_fields WHERE action_id=?", $edit_action['id']));
    }
}

function is_item_used($item_id, $fields) {
    foreach ($fields as $f) {
        if ($f['item_id'] == $item_id) return true;
    }
    return false;
}

function get_param_name($item_id, $fields) {
    foreach ($fields as $f) {
        if ($f['item_id'] == $item_id) return $f['parameter_name'];
    }
    return '';
}
?>
<h2><?= $edit_action ? 'Edit Action' : 'Create HA Action' ?></h2>

<!-- Help Box -->
<div style="border:1px solid #ccc; padding:10px; margin-bottom:15px; background:#f9f9f9;">
    <strong>Help:</strong>
    <ul>
        <li><strong>Lights / Switches:</strong> Use services like <em>turn_on</em>, <em>turn_off</em>, or send attributes in JSON format (static attributes or item parameters)</li>
        <li><strong>Scripts:</strong> Set domain = <code>script</code>, service = script name. Parameters are passed as data.</li>
        <li><strong>Static Attributes:</strong> JSON attributes always sent (optional)</li>
        <li><strong>Description:</strong> Explain what the action does (optional)</li>
    </ul>
</div>

<form method="POST">
    <input type="hidden" name="<?= $edit_action ? 'edit_action' : 'create_action' ?>" value="1"/>
    <?php if ($edit_action): ?>
        <input type="hidden" name="action_id" value="<?= $edit_action['id'] ?>"/>
    <?php endif; ?>
    
    <label>Name:</label> <input name="name" required value="<?= $edit_action['name'] ?? '' ?>"/><br>
    <label>HA Domain:</label> <input name="ha_domain" required value="<?= $edit_action['ha_domain'] ?? '' ?>"/><br>
    <label>HA Service:</label> <input name="ha_service" required value="<?= $edit_action['ha_service'] ?? '' ?>"/><br>
    
    <label>Required Discord Role (optional):</label>
    <select name="required_role">
        <option value="">-- None --</option>
        <?php foreach ($roles as $r): ?>
            <option value="<?=htmlspecialchars($r['role_id'])?>" <?= isset($edit_action['required_role']) && $edit_action['required_role']==$r['role_id']?'selected':'' ?>><?=htmlspecialchars($r['name'])?></option>
        <?php endforeach; ?>
    </select>
    <br>

    <label>Description (optional)</label><br>
    <textarea name="description" rows="2" cols="50"><?= $edit_action['description'] ?? '' ?></textarea><br>

    <label>Static Attributes (JSON)</label><br>
    <textarea name="static_attributes" rows="4" cols="50"><?= $edit_action['static_attributes'] ?? '' ?></textarea><br>
    <small>Example: {"brightness":200,"color":"red"}</small><br>

    <h3>Bind Items as Parameters</h3>
    <table>
    <tr><th>Use</th><th>Item</th><th>Parameter Name for HA</th></tr>
    <?php foreach ($items as $i): ?>
    <tr>
        <td><input type="checkbox" name="items[<?=$i['id']?>][use]" value="1" <?= $edit_action && is_item_used($i['id'], $edit_fields) ? 'checked' : '' ?>/></td>
        <td><?=htmlspecialchars($i['name'])?> (<?=$i['type']?>)</td>
        <td><input type="text" name="items[<?=$i['id']?>][param]" placeholder="Parameter name" value="<?= $edit_action ? get_param_name($i['id'], $edit_fields) : '' ?>"/></td>
    </tr>
    <?php endforeach; ?>
    </table>
    <button type="submit"><?= $edit_action ? 'Update Action' : 'Create Action' ?></button>
</form>

<hr>

<h2>Existing HA Actions</h2>
<table border="1" cellpadding="5">
<tr><th>ID</th><th>Name</th><th>Description</th><th>Domain</th><th>Service</th><th>Required Role</th><th>Static Attributes</th><th>Actions</th></tr>
<?php foreach ($actions as $a): ?>
<tr>
    <td><?=$a['id']?></td>
    <td><?=htmlspecialchars($a['name'])?></td>
    <td><?=htmlspecialchars($a['description'])?></td>
    <td><?=htmlspecialchars($a['ha_domain'])?></td>
    <td><?=htmlspecialchars($a['ha_service'])?></td>
<td>
<?php
$role_name = '';
foreach ($roles as $r) {
    if ($r['role_id'] == $a['required_role']) {
        $role_name = $r['name'];
        break;
    }
}
echo htmlspecialchars($role_name ?: '—');
?>
</td>
    <td><?=htmlspecialchars($a['static_attributes'])?></td>
    <td>
        <a href="?edit_action=<?=$a['id']?>">Edit</a> |
        <a href="?delete_action=<?=$a['id']?>" onclick="return confirm('Delete action?')">Delete</a>
    </td>
</tr>
<?php endforeach; ?>
</table>

<h2>Create / Edit Reusable Item</h2>
<form method="POST">
    <input type="hidden" name="create_item" value="1"/>
    <label>Name:</label> <input name="item_name" required/><br>
    <label>Type:</label>
    <select name="item_type">
        <option value="text">Text</option>
        <option value="number">Number</option>
        <option value="select">Select</option>
        <option value="checkbox">Checkbox</option>
    </select><br>
    <label>Options (comma separated, for select only):</label>
    <input name="item_options"/><br>
    <button type="submit">Create Item</button>
</form>

<hr>


<?php
get_footer();
?>

