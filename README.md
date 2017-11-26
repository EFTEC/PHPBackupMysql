# mysql-backup
This class will backup whole database or selected tables from the database


````
$backupClass=new BackupMysql();
echo $backupClass->GenerateDump($host, $user, $password, $schema);
````
