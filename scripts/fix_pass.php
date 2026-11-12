<?php
$pdo = new PDO("sqlite:database/mwu_rpee.sqlite");
$hash = '$2y$12$UNBwhtooL6Z15BEj19644eSjgdrggtP76VEi0eLHp8VpaEhwBa8hK';
$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
$stmt->execute([':hash' => $hash, ':email' => 'test.admin@mwu.edu.et']);
echo "Update complete.";
?>
