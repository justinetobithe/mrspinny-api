<?php

namespace App\Jobs;

use App\Mail\BlastMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBlastEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $vars = $this->payload;
        $html = $this->interpolate($vars['html'], $vars);
        $mailable = new BlastMail($vars['subject'], $html);

        if (!empty($vars['from_email'])) {
            $mailable->from($vars['from_email'], $vars['from_name'] ?? null);
        }

        $to = [$vars['email'] => $vars['name'] ?? null];
        Mail::to($to)->send($mailable);
    }

    private function interpolate(string $tpl, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($vars) {
            $key = $m[1];
            return isset($vars[$key]) ? (string) $vars[$key] : '';
        }, $tpl);
    }
}
