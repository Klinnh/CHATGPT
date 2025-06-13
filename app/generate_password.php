<?php
$password = 'password';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash pour le mot de passe 'password':\n";
echo $hash;
?> 