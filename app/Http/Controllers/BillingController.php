<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Support\Setting;
use App\Support\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Stripe\Webhook;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->isOperator()
            ? $this->operator($request)
            : $this->client($request);
    }

    // ---- Operator: connect Stripe + map plans --------------------------

    private function operator(Request $request)
    {
        return Inertia::render('Billing/Operator', [
            'configured' => StripeGateway::configured(),
            'publishable' => StripeGateway::publishable(),
            'hasSecret' => (bool) StripeGateway::secret(),
            'hasWebhook' => (bool) StripeGateway::webhookSecret(),
            'connection' => Setting::get('stripe.connection'),
            'webhookUrl' => url('/billing/webhook'),
            'plans' => Plan::orderBy('position')->get(['id', 'name', 'price_cents', 'stripe_price_id']),
        ]);
    }

    public function saveKeys(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate([
            'publishable' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('publishable', $data)) {
            Setting::set('stripe.publishable', $data['publishable'] ?: null);
        }
        if (! empty($data['secret'])) {
            Setting::set('stripe.secret', Crypt::encryptString($data['secret']));
            Setting::set('stripe.connection', null);
        }
        if (! empty($data['webhook_secret'])) {
            Setting::set('stripe.webhook_secret', Crypt::encryptString($data['webhook_secret']));
        }

        return back();
    }

    public function testConnection(Request $request)
    {
        abort_unless($request->user()->isOperator(), 403);
        $client = StripeGateway::client();
        if (! $client) {
            Setting::set('stripe.connection', ['ok' => false, 'message' => 'Add a secret key first.']);

            return back();
        }
        try {
            $acct = $client->accounts->retrieve();
            Setting::set('stripe.connection', [
                'ok' => true,
                'message' => 'Connected to '.($acct->business_profile->name ?? $acct->email ?? $acct->id),
                'livemode' => (bool) ($acct->charges_enabled ?? false),
            ]);
        } catch (\Throwable $e) {
            Setting::set('stripe.connection', ['ok' => false, 'message' => $e->getMessage()]);
        }

        return back();
    }

    public function savePlanPrice(Request $request, Plan $plan)
    {
        abort_unless($request->user()->isOperator(), 403);
        $data = $request->validate(['stripe_price_id' => ['nullable', 'string', 'max:191']]);
        $plan->update(['stripe_price_id' => $data['stripe_price_id'] ?: null]);

        return back();
    }

    // ---- Client: subscribe + manage ------------------------------------

    private function client(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Billing/Client', [
            'configured' => StripeGateway::configured(),
            'subscription' => [
                'status' => $user->subscription_status,
                'plan' => $user->plan?->name,
                'renews' => $user->current_period_end?->toFormattedDateString(),
                'active' => $user->subscribed(),
            ],
            'plans' => Plan::whereNotNull('stripe_price_id')->orderBy('position')
                ->get(['id', 'name', 'price_cents', 'sites_limit', 'db_limit', 'email_limit', 'disk_mb']),
            'flash' => ['success' => $request->boolean('success'), 'canceled' => $request->boolean('canceled')],
        ]);
    }

    public function checkout(Request $request, Plan $plan)
    {
        $user = $request->user();
        $client = StripeGateway::client();
        abort_unless($client, 503, 'Billing is not configured.');
        abort_unless($plan->stripe_price_id, 422, 'This plan is not purchasable yet.');

        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $plan->stripe_price_id, 'quantity' => 1]],
            'client_reference_id' => (string) $user->id,
            'success_url' => url('/billing?success=1'),
            'cancel_url' => url('/billing?canceled=1'),
            'metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id],
            'subscription_data' => ['metadata' => ['user_id' => $user->id, 'plan_id' => $plan->id]],
        ];
        if ($user->stripe_customer_id) {
            $params['customer'] = $user->stripe_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }

        $session = $client->checkout->sessions->create($params);

        return Inertia::location($session->url);
    }

    public function portal(Request $request)
    {
        $user = $request->user();
        $client = StripeGateway::client();
        abort_unless($client && $user->stripe_customer_id, 422);

        $session = $client->billingPortal->sessions->create([
            'customer' => $user->stripe_customer_id,
            'return_url' => url('/billing'),
        ]);

        return Inertia::location($session->url);
    }

    // ---- Stripe webhook (signature-verified, CSRF-exempt) --------------

    public function webhook(Request $request)
    {
        $secret = StripeGateway::webhookSecret();
        if (! $secret) {
            return response('webhook secret not set', 400);
        }
        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret);
        } catch (\Throwable $e) {
            return response('invalid signature', 400);
        }

        $obj = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
                $user = User::find($obj->client_reference_id ?? ($obj->metadata->user_id ?? null));
                if ($user) {
                    $user->stripe_customer_id = $obj->customer;
                    $user->stripe_subscription_id = $obj->subscription;
                    if ($pid = ($obj->metadata->plan_id ?? null)) {
                        $user->plan_id = $pid;
                    }
                    $user->subscription_status = 'active';
                    $user->subscribed_at = now();
                    $user->save();
                }
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $user = User::where('stripe_subscription_id', $obj->id)->orWhere('stripe_customer_id', $obj->customer)->first();
                if ($user) {
                    $user->stripe_subscription_id = $obj->id;
                    $user->subscription_status = $obj->status;
                    if (! empty($obj->current_period_end)) {
                        $user->current_period_end = Carbon::createFromTimestamp($obj->current_period_end);
                    }
                    if ($pid = ($obj->metadata->plan_id ?? null)) {
                        $user->plan_id = $pid;
                    }
                    $user->save();
                }
                break;

            case 'customer.subscription.deleted':
                $user = User::where('stripe_subscription_id', $obj->id)->first();
                if ($user) {
                    $user->subscription_status = 'canceled';
                    $user->save();
                }
                break;
        }

        return response('ok', 200);
    }
}
