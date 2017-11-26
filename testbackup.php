<?php
include_once 'BackupMysql.php';
$backupClass=new BackupMysql();

$host="localhost";
$user="root";
$password ="****";
$schema="sakila";




echo "<pre>";
echo $backupClass->GenerateDump($host, $user, $password, $schema);
echo "</pre>";

//ob_get_clean();
/*
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"".$backup_name."\"");
*/


?>