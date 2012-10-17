<?php

define('clientID', 'YOUR CLIENT ID HERE, alternately: sadness');
define('token', 'Auth tokens are tasty, like cookies, but without the calories');

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
    
    curl_setopt($ch, CURLOPT_URL, "http://api.wheresitup.com/v0/retrieve/$jobID");
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
    
    
    if($attemptCount++ == 10)
    {
        //Something may be wrong with the API
        exit(3);
        $done = true;
    }
    
}while(!$done);


function submitJob($uri, $server, $services)
{
    
    $ch = curl_init();

    $data = http_build_query(array('services' => $services, 'source' => array($server), 'uri' => $uri));
    
    curl_setopt($ch, CURLOPT_URL, 'http://api.wheresitup.com/v0/submit');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Auth: Bearer " . clientID . ' ' . token));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $ret = curl_exec($ch);
    
    $results = json_decode($ret, true);
    if(is_null($results))
    {
        echo "API Call error.";
        var_dump($ret);
        exit(3);
    }
    
    if (!isset($results['jobID']))
    {
        echo "API Call error.";
        var_dump($ret);
        var_dump($data);
        exit(3);
    }
    return $results['jobID'];
}

