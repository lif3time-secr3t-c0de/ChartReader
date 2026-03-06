<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Stripe\StripeClient;

class StripeProvider {
    private $stripe;

    public function __construct($secretKey) {
        $this->stripe = new StripeClient($secretKey);
    }

    public function createCheckoutSession($userId, $customerEmail, $priceId, $successUrl, $cancelUrl, $trialDays = 7) {
        try {
            $payload = [
                'customer_email' => $customerEmail,
                'client_reference_id' => (string) $userId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'user_id' => $userId
                ]
            ];

            $trialDays = (int) $trialDays;
            if ($trialDays > 0) {
                $payload['subscription_data'] = [
                    'trial_period_days' => $trialDays,
                ];
            }

            return $this->stripe->checkout->sessions->create($payload);
        } catch (Throwable $e) {
            error_log("Stripe Session Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function createCustomerPortalSession($customerId, $returnUrl) {
        try {
            return $this->stripe->billingPortal->sessions->create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);
        } catch (Throwable $e) {
            error_log("Stripe Portal Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function verifyWebhook($payload, $sigHeader, $webhookSecret) {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            return ['error' => 'Invalid payload'];
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return ['error' => 'Invalid signature'];
        }
    }
}
