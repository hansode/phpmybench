#!/usr/bin/env php
<?php
/**
 * phpmybench
 *
 * Copyright (C) 2006-2008 axsh Co., LTD.
 * License: GPL v2 or (at your option) any later version
 * Author: Masahito Yoshida <masahito@axsh.net>
 * Blog: http://blog.hansode.org/
 * Version: 0.01
 */

require_once 'MyBench.php';
require_once 'MDB2.php';

$opt_map = getopt('n:r:d:u:p:P:h:H:');

$num_kids = array_key_exists('n', $opt_map) ? $opt_map{n} : 10;
$num_runs = array_key_exists('r', $opt_map) ? $opt_map{r} : 100;
$db       = array_key_exists('d', $opt_map) ? $opt_map{d} : 'mysql';
$user     = array_key_exists('u', $opt_map) ? $opt_map{u} : 'root';
$pass     = array_key_exists('p', $opt_map) ? $opt_map{p} : '';
$port     = array_key_exists('P', $opt_map) ? $opt_map{P} : 3306;
$host_map = Array(
    master => array_key_exists('h',  $opt_map) ? $opt_map{h}  : 'localhost',
    );
if (array_key_exists('H', $opt_map)) {
    if (is_array($opt_map{H})) {
        foreach ($opt_map{H} as $slave) {
            $host_map[slave][] = $slave;
        }
    }
    else {
        $host_map[slave][] = $opt_map{H};
    }
}
$dsn_map = Array(
    master => sprintf("mysql://%s:%s@%s:%s/%s",
                      $user, $pass, $host_map[master],  $port, $db),
    );
if (is_array($host_map[slave])) {
    foreach ($host_map{slave} as $host_slave) {
        $dsn_map[slave][]
            = sprintf("mysql://%s:%s@%s:%s/%s",
                      $user, $pass, $host_slave,  $port, $db);
    }
}

//------------------------------
function callback($id) {
    global $num_runs;
    global $dsn_map;

    $db_map = Array();
    $db_map[master] =& MDB2::connect($dsn_map[master]);
    if (PEAR::isERROR($db_map[master])) {
        die($db_map[master]->getMessage());
    }
    if (is_array($dsn_map[slave])) {
        foreach ($dsn_map[slave] as $dsn_slave) {
            $db_slave =& MDB2::connect($dsn_slave);
            if (PEAR::isERROR($db_slave)) {
                die($db_slave->getMessage());
            }
            $db_map[slave][] = $db_slave;
        }
    }

    $cnt   = 0;
    $times = Array();

    // wait for the parent to HUP me
    pcntl_signal(SIGHUP, create_function('','return 1;'));
    sleep(600);

    while ($cnt < $num_runs) {
        $t0 = microtime(true);
        do_task($db_map);
        $t1 = microtime(true) - $t0;
        $times[] = $t1;
        $cnt++;
    }

    // cleanup
    $db_map[master]->disconnect;
    if (is_array($db_map[slave])) {
        foreach ($db_map[slave] as $db_slave) {
            $db_slave->disconnect;
        }
    }

    $num = count($times);
    $tot = array_sum($times);
    $avg = $tot / $num;
    $r = Array($id, $num, min($times), max($times), $avg, $tot);
    return $r;
}

//------------------------------
function do_task($db_map) {
    $db_master = $db_map[master];
    if (is_array($db_map[slave])) {
        $offset     = rand() % sizeof($db_map[slave]);
        $db_slave = $db_map[slave][$offset];
    }
    else {
        $db_slave = $db_map[master];
    }

    // SQLs
    $res = $db_slave->queryAll('select * from user');

    $sth = $db_slave->prepare('select * from user where user = ?');
    $res = $sth->execute(Array('root'));
    $sth->free();
}

//------------------------------
$results = MyBench::fork_and_work($num_kids, 'callback');
MyBench::complete_results('test', $results);

exit(0);
