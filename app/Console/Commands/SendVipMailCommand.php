<?php

namespace App\Console\Commands;

use App\Mail\VipOfferMail;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;

class SendVipMailCommand extends Command
{
    protected $signature = 'mrspinny:send-vip
                            {--limit=0}
                            {--sleep=0}
                            {--dry}';

    protected $description = 'Send VIP email to customers using resources/views/emails/vip.blade.php';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');
        $dry   = (bool) $this->option('dry');

        $q = Customer::query()
            ->select(['id', 'country', 'language', 'name', 'email', 'phone'])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $total = 0;
        $sent  = 0;

        $q->chunkById(500, function ($customers) use (&$total, &$sent, $dry, $sleep) {
            foreach ($customers as $c) {
                $total++;
                $email = trim((string) $c->email);
                $name  = trim((string) ($c->name ?? ''));

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->warn("[$total] Skipped invalid email: {$email}");
                    continue;
                }

                $this->line("[$total] " . ($name !== '' ? "{$name} " : '') . "<{$email}>" . ($dry ? ' (dry)' : ''));

                if ($dry) {
                    continue;
                }

                try {
                    $to = $name !== '' ? new Address($email, $name) : new Address($email);
                    Mail::to($to)->send(new VipOfferMail($c));
                    $sent++;
                } catch (\Throwable $e) {
                    $this->error("Send failed for {$email}: " . $e->getMessage());
                    Log::error('VIP send failed', [
                        'email' => $email,
                        'err'   => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            }
        });

        $this->info("Completed. Targeted: {$total}. Successfully sent: {$sent}.");
        return self::SUCCESS;
    }
}
