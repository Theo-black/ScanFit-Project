<?php
// hash.php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash . PHP_EOL;


