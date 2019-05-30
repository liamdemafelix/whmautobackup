<?php

/**
 * WHM Auto Backup
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @version 3.0.1
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

/**
 * This is the basic runner.
 * This is useful for non-timestamped backups in the event
 * that your lifecycle policies would take care of backup rotation,
 * such as in the case of S3 or Backblaze B2.
 */

define('BASEPATH', __DIR__ . DIRECTORY_SEPARATOR);

/**
 * Require the boot script.
 * The boot script initializes all pre-execution operations for WHM Auto Backup.
 * This is useful for setting globals, overriding configurations and the like.
 */
require BASEPATH . "app/boot.php";

/**
 * Have a handler of all status counters.
 * This makes it easy to make logs that can be sent to admins via e-mail.
 */
$status = [];

/**
 * Define the starting time.
 * This is used in the e-mail alert.
 */
$startTime = date('Y-m-d H:i:s');

/**
 * Begin.
 */
$cli->arguments->add([
    'destination' => [
        'prefix' => 'd',
        'longPrefix' => 'destination',
        'description' => 'The name of the remote destination as specified in the configuration file.',
        'required' => true
    ],
    'servers' => [
        'prefix' => 's',
        'longPrefix' => 'servers',
        'description' => 'A comma-separated list of server names that should make a backup. Leave empty to make backups for all servers.',
        'required' => true,
        'defaultValue' => '*'
    ],
    'email' => [
        'prefix' => 'e',
        'longPrefix' => 'email',
        'description' => 'Used in the cPanel API. cPanel will send an e-mail to this address for every account that finishes a backup.',
        'required' => false,
    ]
]);

// Parse the arguments
try {
    $cli->arguments->parse();
} catch (\League\CLImate\Exceptions\InvalidArgumentException $e) {
    $cli->error($e->getMessage());
    $cli->usage();
    exit(1);
}

// Check if the destination exists in the config file
$destination = $cli->arguments->get('destination');
if (!array_key_exists($destination, $config['destinations'])) {
    $cli->error("Destination `{$destination}` does not exist.");
    exit(1);
}
$destinationData = $config['destinations'][$destination];

// Loop through the servers
$servers = [];
if ($cli->arguments->defined('servers')) {
    // Get the servers defined in the command
    $argServers = $cli->arguments->get('servers');
    foreach (explode(",", $argServers) as $server) {
        $servers[] = $server;
    }
} else {
    foreach ($config['servers'] as $serverName => $serverDetails) {
        $servers[] = $serverName;
    }
}
foreach ($servers as $server) {
    if (!array_key_exists($server, $config['servers'])) {
        $cli->error("Server `{$server}` does not exist.");
        exit(1);
    }
}

$cli->backgroundGreen('WHM AutoBackup v3.0.1');
$cli->out("Initializing...");

// Prepare destination directory if SCP/FTP
if ($destinationData['type'] == 'scp-pass') {
    try {
        $scp = new \Classes\Scp($destinationData);
        $path = "/";
        if (!$path) {
            $cli->error("Failed to create backup destination directory via SCP.");
            exit(1);
        }
        $cli->green("Using path {$path} on remote");
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        exit(1);
    }
} elseif ($destinationData['type'] == 'ftp') {
    try {
        $ftp = new \Classes\Ftp($destinationData);
        $path = "/";
        if (!$path) {
            $cli->error("Failed to create backup destination directory via SCP.");
            exit(1);
        }
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        exit(1);
    }
}

// Begin transactions
foreach ($servers as $server) {
    $meta = $config['servers'][$server];
    $requiredArrayKeys = ['host', 'username', 'token', 'port'];
    foreach ($requiredArrayKeys as $requiredKey) {
        if (!array_key_exists($requiredKey, $meta) || empty($meta[$requiredKey])) {
            $cli->error("Bad config for `{$requiredKey}` in `{$server}`.");
            exit(1);
        }
    }
    $cli->flank($server . ' - ' . $meta['host']);
    $status[$server] = [
        'name' => $server,
        'success' => 0,
        'fail' => 0,
        'skip' => 0
    ];
    // Attempt to connect
    $cli->yellow("Attempting to connect to server `{$server}`...");
    try {
        $cpanel = new \Gufy\CpanelPhp\Cpanel([
            'host'        =>  "https://{$meta['host']}:{$meta['port']}",
            'username'    =>  $meta['username'],
            'auth_type'   =>  'hash', // set 'hash' or 'password'
            'password'    =>  $meta['token']
        ]);
        $cpanel->setTimeout(180);
        $cpanel->setConnectionTimeout(180);
        $accounts = $cpanel->listAccounts();
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        continue;
    }

    $accountsJSON = @json_decode($accounts);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $cli->error('Server error: ' . substr($accounts, 0, strpos($accounts, 'response:')));
        $cli->error($accounts);
        continue;
    }

    $accountObjects = $accountsJSON->acct;
    $accountObjectsCount = count($accountObjects);

    // Count accounts
    $cli->line("- Found {$accountObjectsCount} accounts");
    foreach ($accountObjects as $object) {
        if ($object->suspended) {
            $cli->red("- Account {$object->user} is suspended. Skipping...");
            $status[$server]['skip']++;
            continue;
        }
        $cli->out("- Processing account {$object->user}...");
        if ($destinationData['type'] == 'scp-pass') {
            $dd = [
                'host' => $destinationData['host'],
                'port' => $destinationData['port'],
                'username' => $destinationData['username'],
                'password' => $destinationData['password'],
                'directory' => $path,
                'cpanel_jsonapi_user' => $object->user
            ];
            if ($cli->arguments->defined('email')) {
                $email = $cli->arguments->get('email');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cli->error("Invalid e-mail address specified on runtime for alerts. Skipping e-mail...");
                } else {
                    $dd['email'] = $cli->arguments->get('email');
                }
            }
            $action = $cpanel->execute_action('3', 'Backup', 'fullbackup_to_scp_with_password', $object->user, $dd);
            $actionJSON = @json_decode($action);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cli->error('Server error: ' . substr($accounts, 0, strpos($accounts, 'response:')));
                $status[$server]['fail']++;
                $cli->error($accounts);
                continue;
            }
            if ($actionJSON->result->status) {
                $cli->green("\tSuccess! PID {$actionJSON->result->data->pid}");
                $status[$server]['success']++;
            } else {
                $cli->red("\tFailed.");
                $status[$server]['fail']++;
            }
        } elseif ($destinationData['type'] === 'ftp') {
            $dd = [
                'host' => $destinationData['host'],
                'port' => $destinationData['port'],
                'username' => $destinationData['username'],
                'password' => $destinationData['password'],
                'variant' => $destinationData['variant'],
                'directory' => $path,
                'cpanel_jsonapi_user' => $object->user
            ];
            if ($cli->arguments->defined('email')) {
                $email = $cli->arguments->get('email');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $cli->error("Invalid e-mail address specified on runtime for alerts. Skipping e-mail...");
                } else {
                    $dd['email'] = $cli->arguments->get('email');
                }
            }
            $action = $cpanel->execute_action('3', 'Backup', 'fullbackup_to_ftp', $object->user, $dd);
            $actionJSON = @json_decode($action);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $cli->error('Server error: ' . substr($accounts, 0, strpos($accounts, 'response:')));
                $status[$server]['fail']++;
                $cli->error($accounts);
                continue;
            }
            if ($actionJSON->result->status) {
                $cli->green("\tSuccess! PID {$actionJSON->result->data->pid}");
                $status[$server]['success']++;
            } else {
                $cli->red("\tFailed.");
                $fp = fopen('test.txt', 'a+');
                fwrite($fp, $action . PHP_EOL);
                fclose($fp);
                $status[$server]['fail']++;
            }
        }
    }
    $cli->br();
    $cli->green("Server {$server} finished.");
    $cli->br();
}

// Done
$endTime = date('Y-m-d H:i:s');
$cli->br();
$d1 = new DateTime($startTime);
$d2 = new DateTime($endTime);
$totalTime = $d2->getTimestamp() - $d1->getTimestamp();
$cli->green("Operation complete. Total time: " . $totalTime . " seconds.");
$cli->br();

$tableData = [
    [
        'Server Name', 'Successful', 'Failed', 'Skipped'
    ]
];
foreach ($status as $kServer) {
    $tableData[] = [
        $kServer['name'], $kServer['success'], $kServer['fail'], $kServer['skip']
    ];
}

$cli->table($tableData);

// Send e-mail
if ($config['email']) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mc = $config['phpmailer'];

    $htmlTable = <<<EOF
<table style="border-collapse: collapse; width: 100%;">
    <tr>
        <th style="text-align: center; border: 1px solid #ccc;">Server</th>
        <th style="text-align: center; border: 1px solid #ccc;">Successful</th>
        <th style="text-align: center; border: 1px solid #ccc;">Failed</th>
        <th style="text-align: center; border: 1px solid #ccc;">Skipped</th>
    </tr>
EOF;

    foreach ($status as $kServer) {
        $htmlTable .= <<<EOF
<tr>
    <td style="text-align: center; border: 1px solid #ccc;">{$kServer['name']}</td>
    <td style="text-align: center; border: 1px solid #ccc;">{$kServer['success']}</td>
    <td style="text-align: center; border: 1px solid #ccc;">{$kServer['fail']}</td>
    <td style="text-align: center; border: 1px solid #ccc;">{$kServer['skip']}</td>
</tr>
EOF;
    }

    $htmlTable .= "</table>";
    try {
        $mail->isSMTP();
        $mail->Host = $mc['host'];
        $mail->SMTPAuth = $mc['auth'];
        $mail->Username = $mc['username'];
        $mail->Password = $mc['password'];
        $mail->SMTPSecure = $mc['security'];
        $mail->Port = $mc['port'];
        $mail->setFrom($mc['from'][1], $mc['from'][0]);
        foreach ($mc['to'] as $name => $email) {
            $mail->addAddress($email, $name);
        }
        foreach ($mc['cc'] as $name => $email) {
            $mail->addCC($email, $name);
        }
        foreach ($mc['bcc'] as $name => $email) {
            $mail->addBCC($email, $name);
        }
        $mail->isHTML($mc['html']);
        $mail->Subject = $mc['subject'];
        $mail->Body = <<<EOF
<p>The WHM Auto Backup runner has completed its tasks. Please see the details below:</p>
<ul>
    <li>Destination: {$destination} ({$destinationData['type']})</li>
    <li>Path on Remote: {$path}</li>
    <li>Start Time: {$startTime}</li>
    <li>Runner End Time: {$endTime}</li>
    <li>Total Running Time: {$totalTime} seconds</li>
</ul>
{$htmlTable}
EOF;
        $mail->send();
    } catch (Exception $e) {
        $cli->error($e->getMessage());
        $cli->info("The runner has finished successfully, but we failed to send the e-mail.");
    }
}