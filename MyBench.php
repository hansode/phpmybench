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
class MyBench {

    function fork_and_work($kids_to_fork, $callback_func) {
	$num_kids = 0;
        $pids     = Array();
        $pipes    = Array();
        $pipe     = true;

        // let the kids die
        pcntl_signal(SIGCHLD, create_function('','return 1;'));
	print "forking: ";

        while ($num_kids < $kids_to_fork) {
            $pipe = tmpfile();   
            $pid = pcntl_fork();
            if ($pid == -1) {
                die('fork failed');
            } else if ($pid) {
                // parent process
                ++$num_kids;
                echo "+";
                $pipes[] = $pipe;
                $pids[]  = $pid;
            } else {
                // child process
                $result = call_user_func($callback_func, $num_kids);
                $lines  = join(" ", $result);
                fwrite($pipe, $lines);
                fclose($pipe);
                exit(0);
            }
        }

	print " ($kids_to_fork)\n";

        // give them a bit of time to setup
        $time = (int)($num_kids / 10) + 1;
        echo "sleeping for $time seconds while kids get ready\n";
        sleep($time);

        echo "waiting: ";

        // get them started
        foreach ($pids as $pid) {
            posix_kill($pid, 1);
        }
        // waitpid
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // collect the results
        $results  = Array();
        foreach ($pipes as $pipe) {
            fseek($pipe, 0);
            $results[] = fread($pipe, 2048);
            fclose($pipe);
            echo "-";
        }

        echo "\n";

	return $results;
    }

    function complete_results($name, $results) {
        $recs = 0;

	$Cnt  = 0;
        $Mins = Array();
        $Maxs = Array();
        $Avg  = 0;
        $Tot  = 0;
        $Qps  = 0;

        foreach ($results as $result) {
            $list = preg_split("/\s+/", $result, 6);

            $Cnt += $list[1]; # $cnt;
            $Avg += $list[4]; # $avg;
            $Tot += $list[5]; # $tot;

            $Mins[] = $list[2]; # $min;
            $Maxs[] = $list[3]; # $max;

            $recs++;
        }
        $Avg = $Avg / $recs;
        $Min = min($Mins);
        $Max = max($Maxs);

        $Qps = $Cnt / ($Tot / $recs);

	echo "$name: $Cnt $Min $max $Avg $Tot $Qps\n";
        echo "  clients : $recs\n";
        echo "  queries : $Cnt\n";
        echo "  fastest : $Min\n";
        echo "  slowest : $Max\n";
        echo "  average : $Avg\n";
        echo "  serial  : $Tot\n";
        echo "  q/sec   : $Qps\n";
    }
}
