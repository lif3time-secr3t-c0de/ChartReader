<?php
require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
global $env;

$db = getDB();
$userModel = new User($db);

if ($action === 'webhook') {
    if ($requestMethod !== 'POST') {
        Response::error('Method not allowed', 405);
    }

    if (empty($env['STRIPE_WEBHOOK_SECRET'])) {
        Response::error('Stripe webhook is not configured', 503);
    }

    if (empty($env['STRIPE_SECRET_KEY'])) {
        Response::error('Stripe is not configured', 503);
    }

    $stripe = new StripeProvider($env['STRIPE_SECRET_KEY']);
    $payload = @file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    $event = $stripe->verifyWebhook($payload, $sigHeader, $env['STRIPE_WEBHOOK_SECRET']);
    if (isset($event['error'])) {
        Response::error($event['error'], 400);
    }

    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $userId = isset($session->metadata->user_id) ? (int) $session->metadata->user_id : 0;
            $customerId = (string) ($session->customer ?? '');

            if ($userId > 0) {
                $userModel->updateSubscription($userId, 'active', 'premium', $customerId);

                $stmt = $db->prepare(
                    "INSERT OR IGNORE INTO payments (user_id, stripe_payment_id, amount, currency, status) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    (string) ($session->payment_intent ?? $session->id ?? ''),
                    (int) ($session->amount_total ?? 0),
                    (string) ($session->currency ?? 'usd'),
                    'succeeded'
                ]);
            }
            break;

        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $stmt = $db->prepare("UPDATE users SET subscription_status = 'canceled', subscription_plan = 'free' WHERE stripe_customer_id = ?");
            $stmt->execute([(string) ($subscription->customer ?? '')]);
            break;
    }

    Response::success('Webhook handled');
}

if (!in_array($action, ['create-checkout', 'portal'], true)) {
    Response::error('Unknown action', 404);
}

Auth::requireLogin();

if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

Security::verifyCsrf();

if (empty($env['STRIPE_SECRET_KEY'])) {
    Response::error('Stripe is not configured', 503);
}

$userId = Auth::getUserId();
$user = $userModel->findById($userId);
if (!$user) {
    Response::error('User not found', 404);
}

$stripe = new StripeProvider($env['STRIPE_SECRET_KEY']);

if ($action === 'create-checkout') {
    $priceId = envValue('PREMIUM_PRICE_ID', '');
    if ($priceId === '') {
        Response::error('Premium price ID is not configured', 503);
    }
    $trialDays = (int) envValue('TRIAL_PERIOD_DAYS', 7);

    $session = $stripe->createCheckoutSession(
        $userId,
        $user['email'],
        $priceId,
        BASE_URL . '/dashboard.html?success=true',
        BASE_URL . '/dashboard.html?cancel=true',
        $trialDays
    );

    if (isset($session['error'])) {
        Response::error('Failed to create checkout session: ' . $session['error'], 502);
    }

    Response::success('Checkout session created', ['url' => $session->url]);
}

if (empty($user['stripe_customer_id'])) {
    Response::error('No active subscription found');
}

$session = $stripe->createCustomerPortalSession($user['stripe_customer_id'], BASE_URL . '/dashboard.html');
if (isset($session['error'])) {
    Response::error('Failed to create portal session: ' . $session['error'], 502);
}

Response::success('Portal session created', ['url' => $session->url]);
