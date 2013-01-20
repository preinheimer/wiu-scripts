<?php

define('clientID', "");
define('token', "");

/*
 *  You're clearly welcome to edit what comes later, but you shouldn't need to.
 *
 *  A few notes:
 *      - The script uses a sleep(5) before submitting the job and checking the first
 *      time, and then before every subsequent check. This is a long time in computing
 *      worlds, but remember that the server has to do a lot of work. Dropping this to
 *      zero is a great way to get throttled.
 *      - http_build_query() wories about escaping for us
 *      - Designed to integrate with Nagios it uses return codes 0, 2, and 3. Which
 *      represent Success, Failure, and Unknown respectively. This simple implementaiton
 *      doesn't present warn.
 *      - When the check fails it immediately requests a second check against that URL,
 *      including Traceroute, Ping, and DNS lookups. While the results of these lookups
 *      aren't available in the echo'd result, the link to them is. This should help
 *      diagnose problems.
 *
 *      Source: https://github.com/preinheimer/wiu-scripts
 *      Integrates with: http://api.wheresitup.com/ 
 */

if(!isset($argv[1]) OR !isset($argv[2]))
{
    echo "Usage: {$argv[0]} <server name> <check source> <maximum acceptable time in ms>\n";
    echo "Example: {$argv[0]} http://example.com newyork 2500\n";
    exit(3);
}

$uri = $argv[1];
$server = $argv[2];
$maxTime = $argv[3];

$jobID = submitJob($uri, $server, array('fast'));

$done = false;
$attemptCount = 0;

do {
    sleep(5);
    unset($ch);
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://adev.wheresitup.com/v2/retrieve/$jobID");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Auth: Bearer " . clientID . ' ' . token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    $results = json_decode($ret, true);
    
    //var_dump($results);
    
    if (isset($results['return']['summary'][$server]['fast']['status']))
    {
        $status = $results['return']['summary'][$server]['fast']['status'];
        $time = $results['return']['summary'][$server]['fast']['time'];
        if ($status != "success")
        {
            echo "[{$jobID}] Page load failure\n";
            return 2;
        }
        
        if ($time > $maxTime)
        {
            echo "[{$jobID}] Maxtime exceeded, limit was {$maxTime}ms, page took {$time}ms\n";
            exit(2);
        }
        
        echo "Success\n";
        exit(0);
    }
    
    if(++$attemptCount == 10)
    {
        //Something may be wrong with the API
        echo "Where's it Up not responding!";
        exit(3);
        $done = true;
    }
    
}while(!$done);


function submitJob($uri, $server, $services)
{
    
    $ch = curl_init();

    $data = http_build_query(array('services' => $services, 'sources' => array($server), 'uri' => $uri));
    
    curl_setopt($ch, CURLOPT_URL, 'http://adev.wheresitup.com/v2/submit');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Auth: Bearer " . clientID . ' ' . token));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    //curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    
    $results = json_decode($ret, true);
    if(is_null($results))
    {
        echo $data;
        echo "API Call error: $ret \n";
        exit(3);
    }
    
    if (!isset($results['jobID']))
    {
        echo "API Call error: $ret \n";
        exit(3);
    }
    return $results['jobID'];
}

