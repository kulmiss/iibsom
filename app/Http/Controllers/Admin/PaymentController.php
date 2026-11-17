<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Admin\Activity;
use App\Support\Money\Boosts;
use App\Support\Money\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/** Boost payments: check the receipt, approve (writes the ledger) or reject. */
class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';
        $page = Payment::query()->with(['user:id,name,email,phone', 'ad:id,title,slug,category_id,city_id', 'reviewer:id,name'])
            ->where('status', $status)
            ->orderBy($status === 'pending' ? 'created_at' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Payment $p) => [
                'id' => $p->id,
                'status' => $p->status,
                'package' => $p->package,
                'method' => $p->method,
                'method_label' => (string) config('boosts.methods.'.$p->method),
                'amount_cents' => $p->amount_cents,
                'amount' => Payment::money($p->amount_cents),
                'reference' => $p->reference,
                'is_test' => $p->is_test,
                'has_receipt' => $p->receipt_path !== null,
                'reason' => $p->reason,
                'created_at' => $p->created_at?->toIso8601String(),
                'reviewed_at' => $p->reviewed_at?->toIso8601String(),
                'reviewer' => $p->reviewer?->name,
                'ad' => $p->ad ? ['id' => $p->ad->id, 'title' => $p->ad->title] : null,
                'user' => ['id' => $p->user->id, 'name' => $p->user->name, 'phone' => $p->user->phone, 'email' => $p->user->email],
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
            'pending' => Payment::where('status', 'pending')->count(),
            'test_mode' => (bool) setting('payments.test_mode'),
        ]);
    }

    /** Balances per account, real money and test money apart. */
    public function summary(): JsonResponse
    {
        return response()->json([
            'real' => Ledger::balances(false),
            'test' => Ledger::balances(true),
            'unbalanced' => count(Ledger::unbalanced()),
        ]);
    }

    public function receipt(Payment $payment): Response
    {
        $bytes = $payment->receiptBytes();
        abort_if($bytes === null, 404);
        Activity::log('payment.receipt_viewed', "Looked at the receipt of payment #{$payment->id}", $payment->user);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex']);
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        try {
            Boosts::approve($payment, $this->authUser($request)->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            Boosts::reject($payment, $this->authUser($request)->id, strip_tags($data['reason']));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }
}
