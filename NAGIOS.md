# Where's it Up - Nagios Integration

To integrate the wheresitup.php script with nagios, just follow these simple steps:

1. Grab the [config file](https://raw.github.com/preinheimer/wiu-scripts/master/nagios_wheresitup.cfg) and place it in your nagios conf.d directory
2. Place the [wheresitup.php](https://raw.github.com/preinheimer/wiu-scripts/master/wheresitup.php) script in /etc/nagios3/scripts or modify the config file to reflect its path
3. Add your servers to the wiu-check-servers hostgroup
4. Tell Nagios to reload its configuration
