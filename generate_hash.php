<?php
// The password you want to hash
$password = "sir123"; // Replace with the real password you want

// Generate the hash
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Display it
echo "Plain password: " . $password . "<br>";
echo "Hashed password: " . $hashedPassword;
?>
