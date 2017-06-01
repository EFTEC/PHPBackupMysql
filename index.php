<?php
include_once 'backupClass.php';
$backupClass=new backupClass();

$host="localhost";
$user="root";
$password ="";
$dbName="webcam";
$backupName="TestDatabase.sql";
$tables=false; // Backup whole database tables if want specific than comment this line and uncomment the next line
//$tables=array("snapshot");   //backup specific tables only: array("mytable1","mytable2",...)   


echo $backupClass->EXPORT_TABLES($host, $user, $pass, $dbName, $tables, $backupName);

?>