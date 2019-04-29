<?php

namespace stee1cat\ImapBackupTool;

use Ddeboer\Imap\MailboxInterface;
use Ddeboer\Imap\MessageInterface;
use Ddeboer\Imap\Server;
use Dotenv\Dotenv;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

const ENV_PREFIX = 'FE_';
const REQUIRED_ENVIRONMENTS = [
    'HOSTNAME',
    'PORT',
    'FLAGS',
    'USERNAME',
    'PASSWORD',
];

function loadConfig(): void
{
    $config = Dotenv::create(dirname(__DIR__));
    $config->load();

    $config->required(array_map(function ($item) {
        return ENV_PREFIX . $item;
    }, REQUIRED_ENVIRONMENTS));
    $config->required(ENV_PREFIX . 'PORT')->isInteger();
}

/**
 * @param string $varName
 * @return array|false|string
 */
function env(string $varName)
{
    return getenv(ENV_PREFIX . $varName);
}

/**
 * @param string $outputDirectory
 */
function makeDirectoryIfNotExists(string $outputDirectory): void
{
    if (!is_dir($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }
}

function normalizeMailboxDirectory(MailboxInterface $mailbox): string
{
    $segments = explode($mailbox->getDelimiter(), $mailbox->getName());

    return implode(DIRECTORY_SEPARATOR, $segments);
}

/**
 * @param MessageInterface $message
 * @param string $outputDirectory
 * @return string
 * @throws Exception
 */
function generateFilePath(MessageInterface $message, string $outputDirectory): string
{
    $filenameFormat = '%s_%d.msg';
    $messageIdx = 1;

    if ($message->getDate()) {
        $messageDate = $message->getDate()->format('Y-m-d_H-i-s');
        $filename = sprintf($filenameFormat, $messageDate, $messageIdx);
        $filePath = $outputDirectory . DIRECTORY_SEPARATOR . $filename;

        while (file_exists($filePath)) {
            $messageIdx += 1;
            if ($messageIdx > 100) {
                throw new Exception('Limit count of messages');
            }

            $filename = sprintf($filenameFormat, $messageDate, $messageIdx);
            $filePath = $outputDirectory . DIRECTORY_SEPARATOR . $filename;
        }

        return $filePath;
    }

    throw new MessageDateNotExistException(sprintf('Date not exist in message ID: %s', $message->getId()));
}

/**
 * @return MailboxInterface[]
 */
function loadMailboxes(): array
{
    $server = new Server(env('HOSTNAME'), env('PORT'), env('FLAGS'));
    $connection = $server->authenticate(env('USERNAME'), env('PASSWORD'));

    return $connection->getMailboxes();
}

/**
 * @param string $path
 * @param string $archive
 */
function compressDirectory(string $path, string $archive): void
{
    $rootPath = realpath($path);
    $zip = new ZipArchive();
    $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            $zip->addFile(
                str_replace(DIRECTORY_SEPARATOR, '/', $filePath),
                str_replace(DIRECTORY_SEPARATOR, '/', $relativePath)
            );
        }
    }

    $zip->close();
}

/**
 * @param string $src
 */
function removeDirectory(string $src): void
{
    $directory = opendir($src);

    while (false !== ($file = readdir($directory))) {
        if (!in_array($file, ['.', '..'])) {
            $full = $src . DIRECTORY_SEPARATOR . $file;

            if (is_dir($full)) {
                removeDirectory($full);
            } else {
                unlink($full);
            }
        }
    }

    closedir($directory);
    rmdir($src);
}
