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

if(!isset($argv[1]) or !isset($argv[2]))
{
    echo "Usage: {$argv[0]} <server name> <source>|all [accept redirects]\n";
    echo "Example: {$argv[0]} http://example.com NewYork yes\n";
    exit(3);
}

$uri = $argv[1];
$server = $argv[2];
$followRedirects = false;

if(isset($argv[3]) and $argv[3] === "yes")
{
    $followRedirects = true;
}

if($server == "all")
{
	$insrc = getSources();

	foreach ($insrc as $k)
	{
		$sources[] = $k['name'];
	}

	#echo "Checking ".count($sources)." sites ...\n";
} else {
	$sources = array($server);
}

$jobID = submitJob($uri, array('http'), $sources);
$attemptCount = 0;

while(True) {
    sleep(5);

	$results = getJobReport($jobID);

	if ($server == "all") {
		$return = array();
		$return['success'] = 0;
		$return['failed'] = 0;

		foreach ($results['return']['summary'] as $key => $value)
		{
			$index = count($value['http']) - 1;
			if($value['http'] == "in progress") {
				$attemptCount++;
				break;
			}

			$rsp = $value['http'][$index]['responseCode'];

			if ($rsp == 200) {
				$return['success']++;
			}else {
				$return['fld'][] = $key;
				$return['failed']++;
			}

			$return[$key] = $rsp;
			unset($rsp);
		}

		if ($return['failed'] + $return['success'] == count($sources))
		{
			if ($return['failed'] == 0)
			{
				echo "Big Success\n";
	            exit(0);
			} else if ($return['failed'] <= 2) {
				echo "WARNING: < 2 sites failed. ".implode(", ", $return['fld']);
				exit(1);
			} else if ($return['failed'] > 2){
				echo "Two or more checks failed. ".implode(", ", $return['fld']);
				exit(2);
			}
		}
	} else {
    	if ($results['return']['summary'][$server]['http'] == "in progress")
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
	            $followUpID = submitJob($uri, array('http', 'trace', 'dig', 'ping'), $sources);
	            echo "Detailed follow up document: http://wheresitup.com/results/$followUpID\n";
	            exit(2);
	        }
	    }
	}

    if(++$attemptCount == 10)
    {
        //Something may be wrong with the API
        echo "Where's it Up not responding!";
        exit(3);
    }
}

function curlRequest ($endpoint = '/v2/submit/', $data = array()) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, 'http://api.wheresitup.com'.$endpoint);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Auth: Bearer " . clientID . ' ' . token));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	if (count($data) > 0) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}

	$ret = curl_exec($ch);

	$results = json_decode($ret, true);
	if(is_null($results))
	{
		echo "API Call error: $ret \n";
		exit(3);
	}

	return $results;
}

function getJobReport($jobID) {
	return curlRequest("/v2/retrieve/$jobID");
}

function getSources() {
	$result = curlRequest('/v2/sources/');
	return $result['sources'];
}

function submitJob($uri, $services, $sources)
{
	$data = http_build_query(array('services' => $services, 'sources' => $sources, 'uri' => $uri));
	$results =  curlRequest ('/v2/submit/', $data);

	if (!isset($results['jobID']))
	{
		echo "API Call error: $results \n";
		exit(3);
	}

	return $results['jobID'];
}