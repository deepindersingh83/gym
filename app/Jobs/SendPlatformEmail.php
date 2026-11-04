<?php

namespace App\Jobs;

use App\Models\SaasPayment;
use App\Models\SaasRefund;
use App\Services\Billing\GymBilling;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Emails from the platform (invoices, credit notes, renewal reminders, requests to gym owners),
 * sent by the queue worker so pages never wait on the mail server. Retried if sending fails.
 */
class SendPlatformEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var list<int> seconds between attempts */
    public array $backoff = [60, 300, 1800];

    /**
     * @param  array{type: 'invoice'|'credit_note', id: int}|null  $attach  a platform document rendered when the email is sent
     */
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?array $attach = null,
        public ?string $replyTo = null,
    ) {}

    public function handle(GymBilling $billing): void
    {
        [$pdf, $filename] = $this->attachment($billing);

        Mail::raw($this->body, function (Message $message) use ($pdf, $filename) {
            $message->to($this->to)->subject($this->subject);

            if ($this->replyTo) {
                $message->replyTo($this->replyTo);
            }

            if ($pdf !== null) {
                $message->attachData($pdf, $filename, ['mime' => 'application/pdf']);
            }
        });
    }

    /** @return array{0: ?string, 1: ?string} */
    protected function attachment(GymBilling $billing): array
    {
        return match ($this->attach['type'] ?? null) {
            'invoice' => ($p = SaasPayment::query()->find($this->attach['id'])) && $p->invoice_number
                ? [$billing->invoicePdf($p)->output(), $p->invoice_number.'.pdf'] : [null, null],
            'credit_note' => ($r = SaasRefund::query()->find($this->attach['id']))
                ? [$billing->creditNotePdf($r)->output(), $r->credit_note_number.'.pdf'] : [null, null],
            default => [null, null],
        };
    }
}
