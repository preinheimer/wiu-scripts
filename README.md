# Where's it Up - Scripts

This repository contains several command line scripts designed to make it easier to integrate [Where's It Up](http://api.wheresitup.com/ "Where's it Up - API") with your existing monitoring infrastructure.

## wheresitup.php
A simple PHP script designed to be called from Nagios. It will hit the specified URL from the requested server, and check for a 200 response code. Upon failure it will
additionally request DNS, Traceroute, and PINGs to the given server, and link to those results.

It requires that PHP have curl installed.

### Usage
1. Edit the file to contain your client id and token
2. Call the script from the command line to test 
e.g. php ./wheresitup.php http://github.com newyork yes
3. Integrate with nagios
