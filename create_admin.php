
<?php

require_once __DIR__ . '/config/database.php';

$name = "Super Admin";
$email = "admin@company.com";
$password = "Admin@12345";

// Hash password securely
$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

try {

    // Check if admin already exists
    $check = $pdo->prepare(
        "SELECT id FROM admins WHERE email = ?"
    );

    $check->execute([$email]);

    if ($check->fetch()) {

        die("Admin already exists!");

    }

    // Insert admin
    $stmt = $pdo->prepare(
        "INSERT INTO admins (name, email, password)
         VALUES (?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        $hashedPassword
    ]);

    echo "Super Admin created successfully!";

    echo "<br>Email: " . htmlspecialchars($email);
    echo "<br>Password: " . htmlspecialchars($password);

} catch (PDOException $e) {

    error_log($e->getMessage());

    echo "Something went wrong!";

}
