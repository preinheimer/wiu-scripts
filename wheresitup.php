<?php

define('clientID', "506a121ea7ec61e60605dc4d");
define('token', "99cd47cda39ddb8e91a2cb0606dc7a6a");

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
    echo "Usage: {$argv[0]} <server name> <check source> [accept redirects]\n";
    echo "Example: {$argv[0]} http://example.com newyork yes\n";
    exit(3);
}

$uri = $argv[1];
$server = $argv[2];
$followRedirects = false;

if(isset($argv[3]) and $argv[3] === "yes")
{
    $followRedirects = true;
}

$jobID = submitJob($uri, $server, array('http'));

$done = false;
$attemptCount = 0;

do {
    sleep(5);
    unset($ch);
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://adev.wheresitup.com/v0/retrieve/$jobID");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Auth: Bearer " . clientID . ' ' . token));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    $results = json_decode($ret, true);

    if ($results['return']['summary'][$argv[2]]['http'] == "in progress")
    {
        $attemptCount++;
    }else {
        if ($followRedirects)
        {
            $key = count($results['return']['summary'][$server]['http']) - 1;
        }else {
            $key = 0;
        }
        
        //Redirects will push data into later elements
        if(isset($results['return']['summary'][$server]['http'][$key]['responseCode']) && $results['return']['summary'][$server]['http'][$key]['responseCode'] == 200)
        {
            echo "Success\n";
            exit(0);
        }else {
            if(isset($results['return']['summary'][$server]['http'][$key]['responseCode']))
            {
                echo "Expected Status Code 200, received: " . $results['return']['summary'][$server]['http'][$key]['responseCode'] . "\n";
            }else {
                echo "Expected Status Code 200, none received\n";
            }
            echo implode("\n", $results['return']['raw']['results'][$server]['http'][0]['full']);
            echo "\n\nJob ID: $jobID\n";
            $followUpID = submitJob($uri, $server, array('http', 'trace', 'dig', 'ping'));
            echo "Detailed follow up document: http://wheresitup.com/results/$followUpID\n";
            exit(2);
        }
        $done = true;
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
    
    curl_setopt($ch, CURLOPT_URL, 'http://adev.wheresitup.com/v1/submit');
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

