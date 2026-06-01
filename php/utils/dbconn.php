<?php

header('Access-Control-Allow-Origin: *');

    $servername = "localhost";
    $username = "alv";
    $password = "Mauropelucchi5!";
    $dbname = "my_alv";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $charset = "utf8mb4";
    $conn->set_charset($charset);

?>