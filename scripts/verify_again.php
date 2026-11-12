<?php
$hash = '$2y$12$T1rb1NiuIXAjGmvfpgWdxeIFEhfozMadcmWdr2FGOD/pTD7qXGPny';
$pass = 'AdminPassword123!';
var_dump(password_verify($pass, $hash));
?>
