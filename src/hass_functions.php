<?php
/**
 * Render a Home Assistant Action
 * - Checks Discord role at render time and at submit
 * - Generates a form with all bound items
 * - Calls HA service with item values
 */
function hass_render_action(int $action_id) {
    global $db;

    // Fetch action
    $action = $db->query("SELECT * FROM hass_actions WHERE id = ?", $action_id)->fetchArray();
    if (!$action) {
        echo "Action not found";
        return;
    }

    $required_role_id = $action['required_role'];
    $user_roles = $_SESSION['user']['roles_ids'] ?? [];

    if (!in_array($required_role_id, $user_roles)) {
        echo "You are not authenticated. Required role ID: {$required_role_id}";
        return;
    }

    // Fetch action fields and associated items
    $fields = $db->query("
        SELECT h.id AS field_id, i.name AS item_name, i.type AS item_type, i.options AS item_options, h.parameter_name
        FROM hass_action_fields h
        LEFT JOIN hass_items i ON h.item_id = i.id
        WHERE h.action_id = ?
    ", $action_id)->fetchAll();

    // Check if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [];

        foreach ($fields as $f) {
            $param = $f['parameter_name'];
            $type = $f['item_type'];

            // Support multiple values for this parameter
            $value = $_POST[$param] ?? null;

            switch ($type) {
                case 'checkbox':
                    $data[$param] = !empty($value);
                    break;

                case 'number':
                    $data[$param] = is_numeric($value) ? +$value : null;
                    break;

                case 'select':
                    $options = $f['item_options'] ? json_decode($f['item_options'], true) : [];
                    if (!in_array($value, $options)) {
                        echo "<p style='color:red;'>Invalid value for {$param}. Options: " . implode(", ", $options) . "</p>";
                        return;
                    }
                    $data[$param] = $value;
                    break;

                case 'text':
                default:
                    $data[$param] = $value;
            }
        }

        // Call HA service
        $resp = ha_call_service($action['ha_domain'], $action['ha_service'], $data);

        if ($resp['http_code'] >= 200 && $resp['http_code'] < 300) {
            echo "<p style='color:green;'>Action '{$action['name']}' executed successfully (OK)</p>";
        } else {
            echo "<p style='color:red;'>Action '{$action['name']}' failed (Not OK)</p>";
            echo "<pre>" . print_r($resp, true) . "</pre>";
        }

        return; // Stop rendering form after submit
    }

    // Render form (GET)
    echo "<form method='POST'>";
    echo "<h3>" . htmlspecialchars($action['name']) . "</h3>";

    if (!empty($action['description'])) {
        echo "<p style='font-style:italic; color:#555;'>" . htmlspecialchars($action['description']) . "</p>";
    }

    foreach ($fields as $f) {
        $param = htmlspecialchars($f['parameter_name']);
        $label = htmlspecialchars($f['item_name']);
        $type = $f['item_type'];
        $options = $f['item_options'] ? json_decode($f['item_options'], true) : [];

        echo "<label>{$label}</label> ";

        switch ($type) {
            case 'text':
            case 'number':
                echo "<input type='{$type}' name='{$param}'><br>";
                break;

            case 'select':
                echo "<select name='{$param}'>";
                foreach ($options as $value => $labelOption) {
                    if (is_numeric($value)) $value = $labelOption;
                    echo "<option value='" . htmlspecialchars($value) . "'>" . htmlspecialchars($labelOption) . "</option>";
                }
                echo "</select><br>";
                break;

            case 'checkbox':
                echo "<input type='checkbox' name='{$param}'><br>";
                break;

            default:
                echo "<input type='text' name='{$param}'><br>";
        }
    }

    echo "<button type='submit'>Run</button>";
    echo "</form>";
}





/**
 * Makes a request to the Home Assistant REST API.
 */
function ha_request($endpoint, $method = 'GET', $data = null) {
    global $ha_url, $ha_token;
    $url = rtrim($ha_url, '/') . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $ha_token",
        "Content-Type: application/json"
    ]);

    $method = strtoupper($method);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST' && $data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $http_code,
        'body' => json_decode($result, true)
    ];
}

// ------------------- Core API functions -------------------

function ha_call_service($domain, $service, $data = []) {
    $endpoint = "/api/services/$domain/$service";
    return ha_request($endpoint, 'POST', $data);
}

function ha_call_script($script_name, $data = []) {
    $endpoint = "/api/services/script/$script_name";
    return ha_request($endpoint, 'POST', $data);
}

function ha_call_scene($scene_name) {
    $endpoint = "/api/services/scene/turn_on";
    return ha_request($endpoint, 'POST', ['entity_id' => $scene_name]);
}

function ha_get_state($entity_id) {
    $endpoint = "/api/states/$entity_id";
    return ha_request($endpoint, 'GET');
}

function ha_get_entities() {
    $endpoint = "/api/states";
    return ha_request($endpoint, 'GET');
}

/**
 * Set an entity's state dynamically.
 *
 * @param string $entity_id e.g., 'light.living_room'
 * @param string $state e.g., 'on', 'off', '25'
 * @param array $attributes optional attributes like brightness, color, etc.
 * @return array
 */
function ha_set_entity($entity_id, $state, $attributes = []) {
    // Determine domain from entity_id
    $parts = explode('.', $entity_id, 2);
    if (count($parts) !== 2) {
        return ['error' => 'Invalid entity_id'];
    }
    $domain = $parts[0];

    // HA uses services like light.turn_on / light.turn_off
    if ($state === 'on' || $state === 'off') {
        $service = "turn_$state";
        $data = array_merge(['entity_id' => $entity_id], $attributes);
        return ha_call_service($domain, $service, $data);
    } else {
        // For other states (sensors, input numbers, etc.)
        $data = ['entity_id' => $entity_id, 'state' => $state];
        if (!empty($attributes)) $data['attributes'] = $attributes;
        return ha_request("/api/states/$entity_id", 'POST', $data);
    }
}

/**
 * Evaluate a Home Assistant template.
 */
function ha_template($template) {
    $endpoint = "/api/template";
    return ha_request($endpoint, 'POST', ['template' => $template]);
}
