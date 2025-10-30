<?php
try {
    $db = new PDO('sqlite:petwatch.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->query("SELECT id, username, password_hash FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;

    foreach ($users as $user) {
        $id = $user['id'];
        $password = $user['password_hash'];

        if (str_starts_with($password, '$2y$')) {
            continue;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $update = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $update->execute([':hash' => $hashed, ':id' => $id]);

        $updatedCount++;
    }

    echo "Done. {$updatedCount} passwords updated.\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
