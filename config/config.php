<?php
try{
    //host
    define('HOST', 'localhost');

    //dbname
    define('DBNAME', 'animes');

    //user
    define('USER', 'root');

    //password
    define('PASS', 'password'); // Updated password

    $conn = new PDO("mysql:host=".HOST.";dbname=".DBNAME, USER, PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // if($conn == true) {
    //     echo "db connection is a succes";
    // } else {
    //     echo "db error";
    // }

}  catch (PDOException $e) {
        echo $e->getMessage();
    }