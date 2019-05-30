<?php

/**
 * WHM Auto Backup
 * Lists and backs up cPanel accounts owned by a WHM reseller.
 *
 * @author Liam Demafelix <liamdemafelix.n@gmail.com>
 * @url https://github.com/liamdemafelix/whmautobackup
 */

if (!defined('BASEPATH')) {
    echo "Please do not execute this file directly.";
    exit(1);
}

return [
    /**
     * E-mail Alerts
     *
     * If this is set to true, WHM AutoBackup will send an e-mail after a runner has
     * completed its tasks. This is different from the cPanel API e-mail (where cPanel sends
     * an e-mail to an address after every backup run), which can be specified during runtime.
     *
     * To disable this feature, set this to false.
     */
    'email' => false,

    /**
     * Email Settings
     *
     * If the 'email' key is not set to false (you wish to receive an alert for a backup),
     * enter the settings for PHPMailer below.
     */
    'phpmailer' => [
        // Your SMTP Host
        'host' => 'smtp1.example.com',
        // Use SMTP Auth?
        'auth' => true,
        // SMTP Username
        'username' => 'email@example.com',
        // SMTP Password
        'password' => 'smtp-password',
        // Security setting: ssl/tls/false
        'security' => 'tls',
        // Port for SMTP
        'port' => 587,
        // From name and e-mail. Array must only contain two values in the order: name, email
        'from' => ['WHM AutoBackup', 'noreply@example.com'],
        // Destinations. Specify as much as you like.
        'to' => [
            'First Contact' => 'contact1@example.com',
            'Second Contact' => 'contact2@example.com',
        ],
        // CC contacts. Specify as much as you like.
        'cc' => [
            'First CC' => 'cc1@example.com',
            'Second CC' => 'cc2@example.com',
        ],
        // BCC contacts. Specify as much as you like.
        'bcc' => [
            'First BCC' => 'bcc1@example.com',
            'Second BCC' => 'bcc2@example.com',
        ],
        'html' => true,
        'subject' => 'WHM AutoBackup Runner Finished'
    ],

    /**
     * Specify backup destinations. Each runtime can use one (1) destination
     * that can be specified as an argument to the runner. No more than one (1) destination
     * is allowed.
     *
     * Supported configuration types: ftp, scp-pass
     */
    'destinations' => [
        'ftpserver1' => [
            'type' => 'ftp',
            'variant' => 'active', // Accepted values: active or passive (a.k.a. FTP Transfer Mode)
            'username' => 'ftpuser',
            'password' => 'ftppassword',
            'host' => 'ftp.example.com',
            'directory' => '~/target/directory/',
            'port' => 21
        ],
        'scpserver1' => [
            'type' => 'scp-pass',
            'username' => 'your_scp_username',
            'password' => 'your_scp_password',
            'host' => 'host.example.com',
            'directory' => 'backups/',
            'port' => 22
        ]
    ],

    /**
     * Server Index
     */
    'servers' => [
        'server1' => [
            'host' => 'server1.hostname.com',
            'username' => 'username',
            'token' => 'api-token',
            'port' => 2087
        ],
        'server2' => [
            'host' => 'server2.hostname.com',
            'username' => 'username',
            'token' => 'api-token',
            'port' => 2087
        ],
    ]
];