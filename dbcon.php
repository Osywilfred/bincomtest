<?php

$hostname = "sql107.infinityfree.com";
$username = "if0_39199822";
$password = "lpqzinBCv61Br";
$dbname = "bincomtest";

$con = new mysqli($hostname, $username, $password, $dbname);

if($con->connect_error){
    die("Connection Error").mysqli_connect_error();
}
//$query = "SELECT * FROM `users_db`";

//$result = $con->query($query);

//var_dump($result);



?>