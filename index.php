<?php

use Ddeboer\Imap\Exception\InvalidHeadersException;

require_once './common.php';

loadConfig();

$mailboxes = loadMailboxes();
$currentDate = date('Y-m-d_H-i');
$outputDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'output';
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
            break;
        }
        catch (MessageDateNotExistException $e) {
            echo $e->getMessage() . PHP_EOL;

            continue 2;
        }
        catch (InvalidHeadersException $e) {
            echo $e->getMessage() . PHP_EOL;

            continue 2;
        }
        catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;

            exit(1);
        }
    }

    printf('%d messages has been saved' . PHP_EOL, $countOfSavedMessages);
}

compressDirectory($backupDirectory, $archiveName);
removeDirectory($backupDirectory);