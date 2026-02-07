<?php
// setup_admin.php
require_once 'includes/config.php';

// Configuration
$username = 'admin';
$password = 'admin123'; // Default password (CHANGE THIS LATER)
$role = 'super_admin';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin already exists
    $check = $pdo->prepare("SELECT id FROM adm_user WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->rowCount() > 0) {
        die("<div style='color: red; font-family: sans-serif;'>Error: Admin user '$username' already exists.</div>");
    }

    // Insert Admin
    $stmt = $pdo->prepare("INSERT INTO adm_user (username, password, role) VALUES (?, ?, ?)");
    if ($stmt->execute([$username, $hashed_password, $role])) {
        echo "<div style='color: green; font-family: sans-serif; padding: 20px; border: 1px solid green; background: #f0fff0;'>";
        echo "<h3>✅ Admin User Created Successfully</h3>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>";
        echo "<p><em>⚠️ Please delete this file (setup_admin.php) from your server now!</em></p>";
        echo "</div>";
    } else {
        echo "Failed to insert user.";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>