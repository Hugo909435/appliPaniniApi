<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MoneyPackage;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoneyController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $packages = MoneyPackage::where('is_active', true)
            ->orderBy('price', 'asc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'amount' => $p->amount,
                'bonus' => $p->bonus,
                'total_money' => $p->total_money,
                'price' => $p->price,
                'price_euros' => $p->price_in_euros,
                'is_popular' => $p->is_popular,
            ]);

        return response()->json([
            'packages' => $packages,
            'stripe_key' => config('cashier.key'),
        ]);
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $request->validate(['package_id' => 'required|exists:money_packages,id']);
        $result = $this->paymentService->createPaymentIntent($request->user(), $request->input('package_id'));
        return response()->json($result);
    }

    public function processPayment(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => 'required|exists:money_packages,id',
            'payment_method_id' => 'required|string',
        ]);

        try {
            $user = $request->user();
            $transaction = $this->paymentService->processPayment($user, $request->input('package_id'), $request->input('payment_method_id'));
            $user->refresh();

            return response()->json([
                'success' => true,
                'transaction' => $transaction,
                'new_money' => $user->money,
                'message' => 'Paiement réussi !',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->moneyTransactions()
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['transactions' => $transactions]);
    }
}
