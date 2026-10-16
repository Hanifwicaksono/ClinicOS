<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordPaymentRefundRequest;
use App\Models\Payment;
use App\Services\DokuPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($validated['search'] ?? '');
        $payments = Payment::query()
            ->with('clinic:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('clinic', fn ($clinicQuery) => $clinicQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('subscription.payments.index', compact('payments', 'search') + ['platform' => true]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['clinic', 'events' => fn ($query) => $query->latest('received_at')]);

        return view('subscription.payments.show', ['payment' => $payment, 'platform' => true]);
    }

    public function reconcile(Payment $payment, DokuPaymentService $paymentService): RedirectResponse
    {
        try {
            $paymentService->reconcile($payment);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status pembayaran diperbarui dari DOKU.');
    }

    public function refund(
        RecordPaymentRefundRequest $request,
        Payment $payment,
        DokuPaymentService $paymentService,
    ): RedirectResponse {
        try {
            $paymentService->recordManualRefund($payment, $request->user(), $request->validated('reason'));
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return back()->with('status', 'Refund manual berhasil dicatat tanpa menghapus periode subscription.');
    }
}
