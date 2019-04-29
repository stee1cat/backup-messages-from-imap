<?php

use Ddeboer\Imap\Exception\InvalidHeadersException;
use function stee1cat\ImapBackupTool\compressDirectory;
use function stee1cat\ImapBackupTool\generateFilePath;
use function stee1cat\ImapBackupTool\loadConfig;
use function stee1cat\ImapBackupTool\loadMailboxes;
use function stee1cat\ImapBackupTool\makeDirectoryIfNotExists;
use function stee1cat\ImapBackupTool\normalizeMailboxDirectory;
use function stee1cat\ImapBackupTool\removeDirectory;
use stee1cat\ImapBackupTool\MessageDateNotExistException;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'common.php';

loadConfig();

$mailboxes = loadMailboxes();
$currentDate = date('Y-m-d_H-i');
$outputDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'output';
$backupDirectory = $outputDirectory . DIRECTORY_SEPARATOR . $currentDate;
$archiveName = $outputDirectory . DIRECTORY_SEPARATOR . sprintf('%s.zip', $currentDate);

makeDirectoryIfNotExists($backupDirectory);

foreach ($mailboxes as $mailbox) {
    if ($mailbox->getAttributes() & \LATT_NOSELECT || $mailbox->count() === 0) {
        continue;
    }

    printf('Mailbox "%s" has %s messages' . PHP_EOL, $mailbox->getName(), $mailbox->count());

    $mailboxDirectory = $backupDirectory . DIRECTORY_SEPARATOR . normalizeMailboxDirectory($mailbox);
    makeDirectoryIfNotExists($mailboxDirectory);

    $countOfSavedMessages = 0;
    foreach ($mailbox->getMessages() as $message) {
        try {
            $filePath = generateFilePath($message, $mailboxDirectory);

            file_put_contents($filePath, $message->getRawMessage());
            $countOfSavedMessages++;
        } catch (MessageDateNotExistException | InvalidHeadersException $e) {
            echo $e->getMessage() . PHP_EOL;

            continue 2;
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;

            exit(1);
        }
    }

    printf('%d messages has been saved' . PHP_EOL, $countOfSavedMessages);
}

compressDirectory($backupDirectory, $archiveName);
removeDirectory($backupDirectory);
