<?php

namespace App\Http\Controllers;

use App\Enums\StaffRole;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Access;
use App\Support\Tenancy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** GST invoice and payment receipt PDFs, for gym staff and for the member themselves. */
class DocumentController extends Controller
{
    public function invoice(Request $request, int $subscription): Response
    {
        $subscription = Subscription::withoutGlobalScopes()->with(['member', 'plan', 'payments', 'gym', 'coupon', 'additionalMembers'])->findOrFail($subscription);
        $this->authorizeDocument($request, $subscription, 'subscriptions.view');

        return Pdf::loadView('pdf.invoice', ['subscription' => $subscription, 'gym' => $subscription->gym])
            ->stream("invoice-{$subscription->invoice_number}.pdf");
    }

    public function receipt(Request $request, int $payment): Response
    {
        $payment = Payment::withoutGlobalScopes()->with(['member', 'subscription.plan', 'gym', 'receivedBy'])->findOrFail($payment);
        $this->authorizeDocument($request, $payment, 'payments.view');

        return Pdf::loadView('pdf.receipt', ['payment' => $payment, 'gym' => $payment->gym])
            ->setPaper('a5')
            ->stream("receipt-{$payment->receipt_number}.pdf");
    }

    protected function authorizeDocument(Request $request, Model $record, string $ability): void
    {
        $user = $request->user();

        if ($user->is_super_admin) {
            return;
        }

        $gym = Gym::query()->findOrFail($record->gym_id);

        if ($user->canAccessTenant($gym)) {
            Tenancy::setGym($gym);
            $membership = Tenancy::membership($user);
            abort_unless(Access::can($ability), 403);

            // Staff tied to a branch only see that branch's documents.
            if ($membership->role !== StaffRole::Owner && $membership->branch_id && $record->branch_id && $membership->branch_id !== $record->branch_id) {
                abort(403);
            }

            return;
        }

        // Members can download their own documents from the member app.
        abort_unless($user->memberProfiles()->whereKey($record->member_id)->exists(), 403);
    }
}
