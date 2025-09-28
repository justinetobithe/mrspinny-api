<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

class VipOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public array $payload;

    public function __construct(Customer $customer, array $payload = [])
    {
        $this->customer = $customer;
        $this->payload  = $payload;
    }

    public function build(): self
    {
        $first = $this->firstName($this->customer);

        $ctaParams = array_filter([
            'src'     => 'vip_email',
            'country' => $this->customer->country ?? null,
            'lang'    => $this->customer->language ?? null,
            'email'   => $this->customer->email ?? null,
        ]);

        $ctaUrl = 'https://mrspinny.eu/?' . http_build_query($ctaParams);

        $data = array_merge([
            'first_name'      => $first,
            'cta_url'         => $ctaUrl,
            'whatsapp_url'    => 'https://wa.me/40755967591?text=Hi%20Nathan,%20I%27m%20interested%20in%20VIP%20support',
            'unsubscribe_url' => 'https://mrspinny.eu/unsubscribe?email=' . urlencode((string) $this->customer->email),
            'preferences_url' => 'https://mrspinny.eu/preferences?email=' . urlencode((string) $this->customer->email),
            'year'            => now()->year,
        ], $this->payload);

        $to = $this->customer->name
            ? new Address($this->customer->email, $this->customer->name)
            : new Address($this->customer->email);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->to($to)
            ->replyTo(
                config('mail.reply_to.address', config('mail.from.address')),
                config('mail.reply_to.name',    config('mail.from.name'))
            )
            ->subject("{$first}, your VIP spin is waiting")
            ->view('emails.vip', $data)
            ->withSymfonyMessage(function ($message) use ($data) {
                $h = $message->getHeaders();
                $h->addTextHeader('List-Unsubscribe', '<' . $data['unsubscribe_url'] . '>');
                $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                $h->addTextHeader('X-Entity-Ref-ID', (string) Str::uuid());
            });
    }

    protected function firstName(Customer $c): string
    {
        $name = trim((string)($c->name ?? ''));
        if ($name !== '') {
            $first = Str::of($name)->replaceMatches('/\s+/', ' ')->before(' ')->trim();
            if ($first->isNotEmpty()) return (string) Str::title($first);
        }
        $email = (string) ($c->email ?? '');
        if ($email !== '' && str_contains($email, '@')) {
            $local = Str::of(Str::before($email, '@'))
                ->replace(['.', '_', '-', '+'], ' ')
                ->replaceMatches('/\d+/', '')
                ->replaceMatches('/\s+/', ' ')
                ->trim();
            if ($local->isNotEmpty()) return (string) Str::title($local->before(' '));
        }
        return 'there';
    }
}
