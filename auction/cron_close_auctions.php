<?php
/*
 * cron_close_auctions.php - run from the command line, once a minute:
 *     * * * * *  php /path/to/public/cron_close_auctions.php          (Mac/Linux crontab)
 *     Windows (XAMPP): Task Scheduler -> C:\xampp\php\php.exe cron_close_auctions.php, repeat every 1 minute
 *
 * 1. closes every auction whose end time has passed (sold / reserve not met / no bids)
 * 2. sends any emails that are due (Outcome/Outbid always; optional types only if the user allows it)
 */
require_once __DIR__ . '/outcomes.php';

$closed = close_ended_auctions($connection);
$sent   = send_pending_emails($connection);
echo date('c'), ' closed ', $closed, ' auction(s), emailed ', $sent, "\n";
