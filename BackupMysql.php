<?php

/**
 * Generates a backup of tables and rows of mysql.
 * It sorts the tables prior the dump, so it avoid to add a table if it lacks of a dependency.
 * For example, if we have the table : City and Country. City depends in Country, so we dump Country first, then City.
 * This class takes some bits of code from https://github.com/VivekMoyal28/mysql-backup
 * Its also based in MysqlDump.
 * Class BackupMysql
 * @version 1.0 First version
 * @copyright Jorge Castro Castillo MIT License.
 */
class BackupMysql
{
    const VERSION = '1.0';

    var $lockTable = true; //Lock all tables before dumping them. The tables are locked with READ.
    var $dropTable = true; //drop a table if exists.
    var $filterTables = array(); // Indicates if we should dump all tables or only specific ones
    var $insertEvery=500; // insert query every "n" number of rows.

    /**
     * @param $host
     * @param $user
     * @param $password
     * @param $schema
     * @return string
     */
    public function GenerateDump($host, $user, $password, $schema)
    {
        set_time_limit(60 * 60 * 3); // 3 hours.
        $mysqli = new mysqli($host, $user, $password, $schema);
        $mysqli->select_db($schema);
        $mysqli->query("SET NAMES 'utf8'");
        $queryTables = $mysqli->query('show full tables where Table_Type = \'BASE TABLE\'');
        $target_tables = array();
        while ($row = $queryTables->fetch_row()) {
            $target_tables[] = $row[0];
        }

        if (count($this->filterTables)) {
            $target_tables = array_intersect($target_tables, $this->filterTables);
        }

        $content = $this->header($schema, $host, $mysqli);
        $target_tables = $this->tableSort($target_tables, $mysqli, $schema);


        foreach ($target_tables as $table) {
            if (empty($table)) {
                continue;
            }
            $rowData = $mysqli->query('SELECT * FROM `' . $table . '`');
            $fieldsCount = $rowData->field_count;
            $rows_num = $mysqli->affected_rows;
            $tableStructure = $mysqli->query('SHOW CREATE TABLE ' . $table);
            $TableMLine = $tableStructure->fetch_row();
            $tcontent = "\n--\n-- Table structure for table `$table`\n--\n";
            if ($this->dropTable) $tcontent .= "DROP TABLE IF EXISTS `$table`;\n";
            $tcontent .= "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n";
            $tcontent .= "/*!40101 SET character_set_client = utf8 */;";
            $tcontent .= "\n\n" . $TableMLine[1] . ";\n\n";
            $tcontent .= "/*!40101 SET character_set_client = @saved_cs_client */;\n";


            $tcontent .= "\n--\n-- Dumping data for table `$table`\n--\n";
            if ($this->lockTable) $tcontent .= "LOCK TABLES `$table` WRITE;\n";
            $tcontent .= "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;";

            for ($i = 0, $st_counter = 0; $i < $fieldsCount; $i++, $st_counter = 0) {
                while ($row = $rowData->fetch_row()) {
                    if ($st_counter % $this->insertEvery == 0 || $st_counter == 0) {
                        $tcontent .= "\nINSERT INTO " . $table . " VALUES";
                    }
                    $tcontent .= "\n(";
                    for ($j = 0; $j < $fieldsCount; $j++) {

                        //$row[$j] = str_replace("\n", "\\n", addslashes($row[$j]));
                        $row[$j] = ($row[$j] === null) ? "null" : "'" . str_replace("\n", "\\n", $mysqli->real_escape_string($row[$j])) . "'";
                    }
                    $tcontent .= implode(',', $row);
                    $tcontent .= ")";
                    if ((($st_counter + 1) % $this->insertEvery == 0 && $st_counter != 0) || $st_counter + 1 == $rows_num) {
                        $tcontent .= ";";
                    } else {
                        $tcontent .= ",";
                    }
                    $st_counter = $st_counter + 1;
                }
            }
            $tcontent .= "\n";
            $tcontent .= "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n";
            if ($this->lockTable) $tcontent .= "UNLOCK TABLES;\n";

            $content .= $tcontent;
        }
        $content .= $this->footer();

        return $content;
    }


    function header($schema, $host, $mysqli)
    {
        $txt = "-- BackupMysql " . self::VERSION . "
--
-- Host: $host    Database: $schema
-- ------------------------------------------------------
-- Server version	" . $mysqli->server_info . "

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
";
        return $txt;
    }

    function footer()
    {
        $now = new DateTime();
        $txt = "
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on " . $now->format("Y-m-d H:i:s") . "\n";
        return $txt;
    }


    /**
     * Sort of the tables. It works in the next fashion:
     *      We have an array where we add each table only if all the dependencies of the table are present in the array.
     *      If not then, we repeat it over and over up to 5 times. We don't want to use a while because it could exists a circular reference.
     * @param string[] $allTables array with all tables
     * @param mysqli $mysqli
     * @param string $schema schema name
     * @param bool $debug True if you want to debug the order
     * @return string[]
     */
    function tableSort($allTables, $mysqli, $schema, $debug = false)
    {
        $usedTable = array();
        // We find dependencies for each table
        foreach ($allTables as $table) {
            $sql = "SELECT REFERENCED_TABLE_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE
                  TABLE_NAME = '$table'
                  and constraint_schema='$schema'
                  and REFERENCED_TABLE_NAME<>TABLE_NAME"; //REFERENCED_TABLE_NAME = '$table'
            $myQuery = $mysqli->query($sql);
            $usedTable[$table] = array();
            while ($row = $myQuery->fetch_row()) {
                $usedTable[$table][] = $row[0];
            }
        }
        if ($debug) {
            echo "<hr>Pre Order:<hr>";
            foreach ($allTables as $table) {
                echo $table . " = " . implode(',', $usedTable[$table]) . "<br>";
            }
        }
        // we start with the tables with no dependencies
        $tableOrder = array();
        $tableFlag = array(); // if the table requires to process.
        foreach ($allTables as $table) {
            if (count($usedTable[$table]) == 0) {
                $tableOrder[] = $table;
                $tableFlag[$table] = false;
            } else {
                $tableFlag[$table] = true;
            }
        }
        $pending = array();
        // second, we add tables after the last dependency.
        for ($i = 0; $i < 5; $i++) {
            foreach ($allTables as $table) {
                if ($tableFlag[$table]) {
                    $amount = count($usedTable[$table]);
                    $foundAll = true;
                    for ($e = 0; $e < $amount; $e++) {
                        $position = array_search($usedTable[$table][$e], $tableOrder);
                        if ($position === false) {
                            $foundAll = false; // this table is still missing some dependencies.
                            break;
                        }
                    }
                    if ($foundAll === true) {
                        // we found all dependencies, so we add at the bottom.
                        $tableOrder[] = $table;
                        $tableFlag[$table] = false; // done.
                    }
                }
            }
        }
        if ($debug) {
            echo "<hr>Order:<hr>";
            foreach ($tableOrder as $table) {
                echo $table . " = " . implode(',', $usedTable[$table]) . "<br>";
            }
        }
        return $tableOrder;
    }
}
