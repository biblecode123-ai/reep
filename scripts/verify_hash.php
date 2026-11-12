<?php
$hash = '$2y$12$UNBwhtooL6Z15BEj19644eSjgdrggtP76VEi0eLHp8VpaEhwBa8hK';
$pass = 'AdminPassword123!';
var_dump(password_verify($pass, $hash));
?>
