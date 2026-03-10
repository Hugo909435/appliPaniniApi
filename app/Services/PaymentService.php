<?php

namespace App\Services;

use App\Models\MoneyPackage;
use App\Models\MoneyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentService
{
    public function createPaymentIntent(User $user, int $packageId): array
    {
        $package = MoneyPackage::findOrFail($packageId);

        $paymentIntent = $user->createSetupIntent();

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'package' => $package,
        ];
    }

    public function processPayment(User $user, int $packageId, string $paymentMethodId): MoneyTransaction
    {
        $package = MoneyPackage::findOrFail($packageId);

        return DB::transaction(function () use ($user, $package, $paymentMethodId) {
            // ✅ Configuration Stripe
            Stripe::setApiKey(config('cashier.secret'));

            // ✅ Créer le PaymentIntent manuellement avec les bons paramètres
            $paymentIntent = PaymentIntent::create([
                'amount' => $package->price, // Montant en centimes
                'currency' => $package->currency,
                'payment_method' => $paymentMethodId,
                'customer' => $user->stripe_id ?? $user->createAsStripeCustomer()->id,
                'confirm' => true,
                'description' => "Achat de {$package->name}",
                'metadata' => [
                    'package_id' => $package->id,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ],
                // ✅ SOLUTION : Désactiver les méthodes de paiement avec redirection
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never', // ✅ Pas de redirections
                ],
            ]);

            // Vérifier si le paiement a réussi
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Le paiement a échoué');
            }

            // Ajouter la money à l'utilisateur
            $totalMoney = $package->total_money;
            $user->money += $totalMoney;
            $user->save();

            // Enregistrer la transaction
            $transaction = MoneyTransaction::create([
                'user_id' => $user->id,
                'money_package_id' => $package->id,
                'type' => 'purchase',
                'amount' => $totalMoney,
                'price' => $package->price,
                'stripe_payment_id' => $paymentIntent->id,
                'status' => 'completed',
                'description' => "Achat de {$package->name}",
            ]);

            return $transaction;
        });
    }

    public function refundTransaction(MoneyTransaction $transaction): bool
    {
        if ($transaction->status !== 'completed' || !$transaction->stripe_payment_id) {
            return false;
        }

        try {
            // Configuration Stripe
            Stripe::setApiKey(config('cashier.secret'));

            // Créer le remboursement
            \Stripe\Refund::create([
                'payment_intent' => $transaction->stripe_payment_id,
            ]);

            $user = $transaction->user;

            // Retirer la money
            $user->money -= $transaction->amount;
            $user->save();

            // Mettre à jour la transaction
            $transaction->update(['status' => 'refunded']);

            // Créer une transaction de remboursement
            MoneyTransaction::create([
                'user_id' => $user->id,
                'type' => 'refund',
                'amount' => -$transaction->amount,
                'price' => -$transaction->price,
                'description' => 'Remboursement',
                'status' => 'completed',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error('Refund failed: ' . $e->getMessage());
            return false;
        }
    }
}
