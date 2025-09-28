<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailSmtpTestCommand extends Command
{
    protected $signature = 'mrspinny:mail-test {to}';
    protected $description = 'Send a minimal SMTP test email';

    public function handle(): int
    {
        $to = $this->argument('to');
        $this->line('Mailer config: ' . json_encode([
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'enc'  => config('mail.mailers.smtp.encryption'),
            'user' => config('mail.mailers.smtp.username'),
        ]));

        try {
            Mail::raw('SMTP test from MrSpinny.', function ($m) use ($to) {
                $m->from(config('mail.from.address'), config('mail.from.name'))
                    ->to($to)
                    ->subject('SMTP test');
            });
            $this->info('Sent. Check inbox/spam.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('SMTP failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
