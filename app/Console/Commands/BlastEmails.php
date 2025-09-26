<?php

namespace App\Console\Commands;

use App\Jobs\SendBlastEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class BlastEmails extends Command
{
    protected $signature = 'blast:emails {file : CSV path} {--subject=} {--html=} {--html-file=} {--from=} {--name=}';
    protected $description = 'Queue a bulk email blast from a CSV file';

    public function handle(): int
    {
        $file = $this->argument('file');
        $subject = (string)$this->option('subject');
        $html = (string)$this->option('html');
        $htmlFile = (string)$this->option('html-file');
        $from = (string)$this->option('from');
        $fromName = (string)$this->option('name');

        if (!$subject) {
            $this->error('Missing --subject');
            return self::FAILURE;
        }

        if (!$html && $htmlFile) {
            if (!is_readable($htmlFile)) {
                $this->error('Cannot read --html-file');
                return self::FAILURE;
            }
            $html = file_get_contents($htmlFile);
        }

        if (!$html) {
            $this->error('Provide --html or --html-file');
            return self::FAILURE;
        }

        if (!is_readable($file)) {
            $this->error('CSV file not found');
            return self::FAILURE;
        }

        $rows = $this->parseCsv($file);
        if (empty($rows)) {
            $this->warn('No recipients found');
            return self::SUCCESS;
        }

        $batch = Bus::batch([])->dispatch();
        $count = 0;

        foreach ($rows as $r) {
            $payload = [
                'email' => $r['email'],
                'name' => $r['name'] ?? null,
                'subject' => $subject,
                'html' => $html,
                'from_email' => $from ?: null,
                'from_name' => $fromName ?: null,
            ];
            $batch->add(new SendBlastEmail($payload));
            $count++;
        }

        $this->info("Queued: {$count} | Batch: {$batch->id}");
        return self::SUCCESS;
    }

    private function parseCsv(string $path): array
    {
        $out = [];
        $h = fopen($path, 'r');
        if (!$h) return $out;

        $headers = [];
        if (($row = fgetcsv($h, 0, ',')) !== false) {
            $headers = array_map(fn($x) => strtolower(trim($x)), $row);
        }

        while (($row = fgetcsv($h, 0, ',')) !== false) {
            if (count($row) === 1 && filter_var($row[0], FILTER_VALIDATE_EMAIL)) {
                $out[] = ['email' => trim($row[0])];
                continue;
            }
            $rec = [];
            foreach ($row as $i => $val) {
                $key = $headers[$i] ?? "col{$i}";
                $rec[$key] = trim((string)$val);
            }
            $email = $rec['email'] ?? $rec['e-mail'] ?? null;
            if (!$email) continue;
            $name = $rec['name'] ?? trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? ''));
            $out[] = ['email' => $email, 'name' => $name ?: null];
        }
        fclose($h);
        return $out;
    }
}
