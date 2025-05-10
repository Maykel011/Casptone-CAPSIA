<?php
$servername = "localhost";
$username = "u871640738_ucgs"; // Typically your cPanel username + _ + db username
$password = "@Capstoneucgs1"; // The password you set for this database user
$dbname = "u871640738_ucgs";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
