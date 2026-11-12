<?php
require 'config/app.php';
require 'app/autoload.php';
use App\Models\UserModel;

$user = UserModel::findByIdentifier('test.admin@mwu.edu.et');
var_dump($user);
?>
