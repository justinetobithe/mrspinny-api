<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Reader as ExcelReader;

class ImportCustomersCommand extends Command
{
    protected $signature = 'mrspinny:import-customers 
                            {path : Relative to storage/app or absolute path} 
                            {--chunk=1000}';
    protected $description = 'Import customers from CSV/XLSX into MySQL';

    public function handle(): int
    {
        $arg = $this->argument('path');
        $full = is_file($arg) ? $arg : storage_path('app/' . ltrim($arg, '/'));
        if (!is_file($full)) {
            $this->error("File not found: $full");
            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $csv = ExcelReader::createFromPath($full)->setHeaderOffset(0);
            foreach ($csv->getRecords() as $r) $rows[] = $this->normalizeRow($r);
        } else {
            $sheets = Excel::toArray(new \stdClass(), $full);
            $sheet = $sheets[0] ?? [];
            if (!count($sheet)) {
                $this->warn('No rows found.');
                return self::SUCCESS;
            }
            $header = array_map(fn($h) => $this->key($h), array_shift($sheet));
            foreach ($sheet as $r) {
                $assoc = [];
                foreach ($header as $i => $k) $assoc[$k] = $r[$i] ?? null;
                $rows[] = $this->normalizeRow($assoc);
            }
        }

        $rows = array_values(array_filter($rows, fn($r) => !empty($r['email'])));
        if (!count($rows)) {
            $this->warn('No usable rows.');
            return self::SUCCESS;
        }

        $chunk = (int)$this->option('chunk');
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach (array_chunk($rows, $chunk) as $part) {
            DB::table('customers')->upsert(
                array_map(function ($r) {
                    return [
                        'country' => $r['country'] ?? null,
                        'language' => $r['language'] ?? null,
                        'name' => $r['name'] ?? null,
                        'email' => $r['email'],
                        'phone' => $r['phone'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
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

    protected function key($v): string
    {
        $v = trim((string)$v);
        $v = strtolower($v);
        $v = str_replace([' ', '-', '.'], '_', $v);
        return $v;
    }

    protected function normalizeRow(array $r): array
    {
        $k = [];
        foreach ($r as $key => $val) $k[$this->key($key)] = $val;

        $email = isset($k['email']) ? strtolower(trim((string)$k['email'])) : null;
        $name = isset($k['name']) ? trim((string)$k['name']) : null;
        $country = isset($k['country']) ? trim((string)$k['country']) : null;
        $language = isset($k['language']) ? trim((string)$k['language']) : null;

        $phone = $k['phone'] ?? null;
        if ($phone !== null) $phone = preg_replace('/\s+/', '', (string)$phone);

        return [
            'country' => $country ?: null,
            'language' => $language ?: null,
            'name' => $name ?: null,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone' => $phone ?: null,
        ];
    }
}
