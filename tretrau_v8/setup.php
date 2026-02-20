<?php
// setup.php — Run once to initialize DB and create admin account
require_once 'config.php';

$secret = $_GET['secret'] ?? '';
if ($secret !== 'setup_' . md5(ADMIN_SECRET)) {
    die('Access denied. Use: setup.php?secret=setup_' . md5(ADMIN_SECRET));
}

$db = getDB();
installDatabase();

// Create admin user if not exists
$admin_user = 'admin';
$admin_email = 'admin@localhost';
$admin_pass = 'Admin@12345'; // CHANGE THIS

$check = $db->prepare("SELECT id FROM users WHERE username=? OR email=?");
$check->execute([$admin_user, $admin_email]);
if (!$check->fetch()) {
    $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,'admin')")
       ->execute([$admin_user, $admin_email, password_hash($admin_pass, PASSWORD_DEFAULT)]);
    echo "<p style='color:green'>Admin account created: <strong>$admin_user</strong> / <strong>$admin_pass</strong></p>";
    echo "<p style='color:red'><strong>IMPORTANT: Change admin password immediately!</strong></p>";
} else {
    echo "<p>Admin account already exists.</p>";
}

echo "<p style='color:green'>Database initialized successfully!</p>";
echo "<p><a href='index.php'>Go to website</a> | <a href='admin/admin.php'>Go to admin panel</a></p>";
?>
