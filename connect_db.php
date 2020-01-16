<?php
function conndb() {
 $dbhost = "localhost";
 $dbuser = "root";
 $dbpass = "";
 $db = "usr";
 
 $conn = new mysqli($dbhost, $dbuser, $dbpass,$db) or die("Connect failed: %s\n". $conn -> error);
 $conn->set_charset("utf8");
 return $conn;
}
?>