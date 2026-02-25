<?php

namespace Photobooth\Service;

use Photobooth\Enum\FolderEnum;

class PhotoConsentLogService
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = FolderEnum::DATA->absolute() . DIRECTORY_SEPARATOR . 'photo-consent.csv';
    }

    public function addPhotoEntry(string $filename): void
    {
        $this->ensureLogFileExists();

        $handle = fopen($this->logFile, 'a');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open consent log file for appending.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Unable to lock consent log file.');
        }

        $result = fputcsv($handle, [
            date('Y-m-d'),
            date('H:i:s'),
            $filename,
            '',
            '',
        ], ',', '"', '\\');

        flock($handle, LOCK_UN);
        fclose($handle);

        if ($result === false) {
            throw new \RuntimeException('Unable to write photo entry to consent log file.');
        }
    }

    /**
     * @param string[] $recipients
     */
    public function updateMailDetails(string $filename, array $recipients, string $consentStatus): void
    {
        $this->ensureLogFileExists();

        $handle = fopen($this->logFile, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open consent log file for update.');
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new \RuntimeException('Unable to lock consent log file.');
        }

        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        $mailAddress = implode('; ', $recipients);
        $updated = false;

        for ($index = count($rows) - 1; $index >= 1; --$index) {
            if (($rows[$index][2] ?? '') !== $filename) {
                continue;
            }

            $rows[$index][3] = $mailAddress;
            $rows[$index][4] = $consentStatus;
            $updated = true;
            break;
        }

        if (!$updated) {
            $rows[] = [
                date('Y-m-d'),
                date('H:i:s'),
                $filename,
                $mailAddress,
                $consentStatus,
            ];
        }

        ftruncate($handle, 0);
        rewind($handle);

        foreach ($rows as $row) {
            if (fputcsv($handle, $row, ',', '"', '\\') === false) {
                flock($handle, LOCK_UN);
                fclose($handle);
                throw new \RuntimeException('Unable to rewrite consent log file.');
            }
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function ensureLogFileExists(): void
    {
        if (is_file($this->logFile)) {
            return;
        }

        $handle = fopen($this->logFile, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create consent log file.');
        }

        if (fputcsv($handle, ['date', 'time', 'filename', 'mail', 'consent'], ',', '"', '\\') === false) {
            fclose($handle);
            throw new \RuntimeException('Unable to initialize consent log file.');
        }

        fclose($handle);
    }

    public static function getInstance(): self
    {
        if (!isset($GLOBALS[self::class])) {
            $GLOBALS[self::class] = new self();
        }

        return $GLOBALS[self::class];
    }
}
