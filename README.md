# WHM Auto Backup

[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=GUV2KKLLSGXES)

WHM Auto Backup is a free and open source script allowing cPanel/WHM resellers (non-root) to back up sub-accounts to remote servers via SCP/SFTP and FTP.

> If you are looking for the older version of WHM Auto Backup, please see the [legacy repo](https://github.com/liamdemafelix/whmautobackup-legacy).

# Features

* Uses the new cPanel UAPI
* Backups are timestamped and arranged per-server on the remote via SFTP/SCP or FTP
* Runs via cron, no cleanup needed
* Supports e-mail alerts via PHPMailer with summary of runtime
* Multi-server support (use one instance on more than one WHM account)
* Free and Open Source, licensed under the MIT License

# Requirements

* A WHM account with backup privileges
    * Some providers disable the built-in cPanel Backup feature in favor of custom backup software like R1Soft or JetBackup. If this is disabled, WHM Auto Backup will fail.
* A remote FTP/SCP server. No support for key authentication via SCP yet.
* A runner server with PHP (`php` and `php-curl`)

# Setup

Clone the repository somewhere, then `cd` to the directory and run `composer install` to grab the dependencies.

Then, copy `config.sample.php` to `config.php` inside the `app/` folder and edit your settings.

# Running

```
/usr/bin/php /path/to/runner.php -d <destination> [-e <api email>] [-s <comma-separated list of servers>]
```

...where

* `-d` or `--destination` - The name of the destination as set in the configuration file. For instance, the name of the destination in the following setup is `scpserver1`:
```
'scpserver1' => [
	'type' => 'scp-pass',
	'username' => 'your_scp_username',
	'password' => 'your_scp_password',
	'host' => 'host.example.com',
	'directory' => 'backups/',
	'port' => 22
]
```

* `-e` or `--email` - The e-mail to pass to the cPanel API. cPanel sends an e-mail to this address for every account that finishes a backup process. This is **not** the e-mail where WHM Auto Backup runners send an e-mail to after running (you specify this in the configuration file). **This parameter is optional.**
* `-s` or `--servers` - A comma-separated list of servers (using the server's key in the array in the configuration file). **This parameter is optional** and by default, WHM Auto Backup runs on all servers specified in the configuration file.

# Sample Output

Upon executing the runner, this is the output. Note that there is no interaction needed and the runner can also run from a cron.

```
WHM AutoBackup v3.0.0
Initializing...
Using path backups/2019-04-25_14-01-08 on remote
### server1 - server1.example.com ###
Attempting to connect to server `server1`...
- Found 4 accounts
- Processing account user1...
        Success! PID 16540
- Processing account user2...
        Success! PID 16908
- Account user3 is suspended. Skipping...
- Processing account user4...
        Success! PID 17927

Server server1 finished.

### server2 - server2.example.com ###
Attempting to connect to server `server2`...
- Found 2 accounts
- Processing account user1...
        Failed.
- Processing account user2...
        Success! PID 17927

Server server2 finished.

Operation complete. Total time: 14 seconds.

-----------------------------------------------
| Server Name | Successful | Failed | Skipped |
-----------------------------------------------
| server1     | 3          | 0      | 1       |
-----------------------------------------------
| server2     | 1          | 1      | 0       |
-----------------------------------------------
```

A tabled summary is also sent on the e-mail alert.

# License

This script is licensed under the MIT Open Source license.
