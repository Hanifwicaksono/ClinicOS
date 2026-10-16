<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\DokuPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->where('clinic_id', $request->user()->clinic_id)
            ->latest()
            ->paginate(15);

        return view('subscription.payments.index', ['payments' => $payments, 'platform' => false]);
    }

    public function show(Request $request, Payment $payment): View
    {
        $this->authorizeOwnerPayment($request, $payment);
        $payment->load(['clinic', 'events' => fn ($query) => $query->latest('received_at')]);

        return view('subscription.payments.show', ['payment' => $payment, 'platform' => false]);
    }

    public function reconcile(
        Request $request,
        Payment $payment,
        DokuPaymentService $paymentService,
    ): RedirectResponse {
        $this->authorizeOwnerPayment($request, $payment);

        try {
            $paymentService->reconcile($payment);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status pembayaran diperbarui dari DOKU.');
    }

    private function authorizeOwnerPayment(Request $request, Payment $payment): void
    {
        abort_unless($request->user()->clinic_id === $payment->clinic_id, 404);
    }
}
