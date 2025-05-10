<?php
$servername = "localhost";
$username = "root"; // Typically your cPanel username + _ + db username
$password = ""; // The password you set for this database user
$dbname = "u450075158_ucgs";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
