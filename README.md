# mysql-backup
This class will backup whole database or selected tables from the database
For exporting the database .sql file use this function. 
````
echo $backupClass->EXPORT_TABLES($host, $user, $pass, $dbName, $tables, $backupName);
````
