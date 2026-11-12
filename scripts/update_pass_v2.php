<?php
$pdo = new PDO("sqlite:database/mwu_rpee.sqlite");
$hash = '$2y$12$T1rb1NiuIXAjGmvfpgWdxeIFEhfozMadcmWdr2FGOD/pTD7qXGPny';
$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
$stmt->execute([':hash' => $hash, ':email' => 'test.admin@mwu.edu.et']);
echo "Updated to: $hash";
?>