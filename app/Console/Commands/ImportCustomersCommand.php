<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ImportCustomersCommand extends Command
{
    protected $signature = 'mrspinny:import-customers 
                            {path : Relative to storage/app or absolute path} 
                            {--chunk=1000}';

    protected $description = 'Import customers from CSV/XLSX into MySQL';

    public function handle(): int
    {
        $arg  = $this->argument('path');
        $full = is_file($arg) ? $arg : storage_path('app/' . ltrim($arg, '/'));

        if (!is_file($full)) {
            $this->error("File not found: {$full}");
            return self::FAILURE;
        }

        $rows = $this->readSheet($full);
        if (empty($rows)) {
            $this->warn('No usable rows.');
            return self::SUCCESS;
        }

        $chunk = (int) $this->option('chunk');
        $bar   = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach (array_chunk($rows, $chunk) as $part) {
            DB::table('customers')->upsert(
                array_map(function ($r) {
                    return [
                        'country'     => $r['country'] ?? null,
                        'language'    => $r['language'] ?? null,
                        'name'        => $r['name'] ?? null,
                        'email'       => $r['email'],
                        'phone'       => $r['phone'] ?? null,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }, $part),
                ['email'],
                ['country', 'language', 'name', 'phone', 'updated_at']
            );
            $bar->advance(count($part));
        }

        $bar->finish();
        $this->newLine();
        $this->info('Import complete.');
        return self::SUCCESS;
    }

    protected function readSheet(string $path): array
    {
        $arrays = Excel::toArray(new \stdClass(), $path);
        $sheet  = $arrays[0] ?? [];
        if (!count($sheet)) return [];

        $header = array_map(fn($h) => $this->key($h), (array) array_shift($sheet));

        $rows = [];
        foreach ($sheet as $row) {
            $assoc = [];
            foreach ($header as $i => $k) $assoc[$k] = $row[$i] ?? null;
            $norm = $this->normalizeRow($assoc);
            if (!empty($norm['email'])) $rows[] = $norm;
        }
        return $rows;
    }

    protected function key($v): string
    {
        $v = trim((string) $v);
        $v = strtolower($v);
        return str_replace([' ', '-', '.'], '_', $v);
    }

    protected function normalizeRow(array $r): array
    {
        $k = [];
        foreach ($r as $key => $val) $k[$this->key($key)] = $val;

        $email    = isset($k['email']) ? strtolower(trim((string) $k['email'])) : null;
        $name     = isset($k['name']) ? trim((string) $k['name']) : null;
        $country  = isset($k['country']) ? trim((string) $k['country']) : null;
        $language = isset($k['language']) ? trim((string) $k['language']) : null;

        $phone = $k['phone'] ?? null;
        if ($phone !== null) $phone = preg_replace('/\s+/', '', (string) $phone);

        return [
            'country'  => $country ?: null,
            'language' => $language ?: null,
            'name'     => $name ?: null,
            'email'    => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone'    => $phone ?: null,
        ];
    }
}
