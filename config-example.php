<?php
//edit and rename to config.php
$dbhost = 'localhost';
$dbuser = 'mole';
$dbpass = '';
$dbname = 'chrimera';

define('OAUTH2_CLIENT_ID', '');
define('OAUTH2_CLIENT_SECRET', '');

$authorizeURL = 'https://discord.com/api/oauth2/authorize';
$tokenURL = 'https://discord.com/api/oauth2/token';
$apiURLBase = 'https://discord.com/api/users/@me';

$wordpress_enabled = true;
$wordpress_api_url = '';
$wordpress_api_token = '';
$login_url = "http://logintoyourwebsite.example.com";

define('LDAP_HOST', 'ldap://localhost');
define('LDAP_ADMIN_DN', 'cn=admin,dc=chrimera,dc=org');
define('LDAP_ADMIN_PASS', 'oneworld');
define('LDAP_BASE_DN', 'dc=chrimera,dc=org');


?>
