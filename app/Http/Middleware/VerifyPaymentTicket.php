<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\PaymentReturnTicketService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyPaymentTicket
{
    public function handle(Request $request, Closure $next)
    {
        $ticket = trim((string) $request->input('ticket', $request->header('X-Payment-Ticket', '')));
        if ($ticket === '') {
            return $this->forbidden();
        }

        try {
            $payload = app(PaymentReturnTicketService::class)->verify($ticket);
        } catch (\Throwable $e) {
            Log::warning('Payment return ticket verification failed before payload validation', [
                'error' => $e->getMessage(),
            ]);

            return $this->forbidden();
        }

        if (!$payload) {
            return $this->forbidden();
        }

        if (strtoupper((string) data_get($payload, 'origin', '')) !== 'B') {
            return $this->forbidden();
        }

        $orderNo = (string) data_get($payload, 'order_no', '');
        if ($orderNo === '') {
            return $this->forbidden();
        }

        $order = Order::query()->with('user')->where('no', $orderNo)->first();
        if (!$order || !$request->user() || (int) $order->user_id !== (int) $request->user()->id) {
            return $this->forbidden();
        }

        $request->attributes->set('payment_return_ticket_payload', $payload);
        $request->attributes->set('payment_return_order', $order);

        return $next($request);
    }

    protected function forbidden()
    {
        abort(403, 'Payment ticket is invalid');
    }
}