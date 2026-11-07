<?php
require_once("libs/db_stdlib.php");
require_once("libs/db_conecta.php");
require_once("libs/db_utils.php");
include("libs/db_sessoes.php");
include("libs/db_usuariosonline.php");
include("dbforms/db_funcoes.php");

$hostname = gethostname();
$cmd = shell_exec("cat updatedb/conn | grep -e {$hostname}$");
$rows        = preg_split('/\s+/', $cmd);
$rows = array_filter($rows);
$array_global = array();
$array_interno = array();

foreach ($rows as $row) {
    if (count($array_interno) <= 3) {
        $array_interno[] = $row;
        if (count($array_interno) == 3) {
            array_push($array_global, $array_interno);
            $array_interno = array();
        }
    }
}

foreach ($array_global as $row) {
    system("rm -R /var/www/html/tmp/backup_$row[0]_*");
    system("pg_dump -U dbportal {$row[0]} | bzip2 -c > /var/www/html/tmp/backup_{$row[0]}_" . date("dmY") . ".sql.bz2");
}

echo json_encode("/var/www/html/tmp/backup_{$row[0]}_" . date("dmY") . ".sql.bz2");
