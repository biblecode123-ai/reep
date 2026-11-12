<?php
$db = new PDO('sqlite:database/mwu_rpee.sqlite');
$sql = file_get_contents('database/migration_appeals.sql');
$db->exec($sql);
echo "Migration applied.";

