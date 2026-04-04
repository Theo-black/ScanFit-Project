<?php
$password = 'admin123'; // choose your admin password
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
