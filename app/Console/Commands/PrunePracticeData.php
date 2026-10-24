<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

#[Signature('practice:prune')]
#[Description('Delete old orders and bookings, stale carts, expired tokens and expired learner accounts')]
class PrunePracticeData extends Command
{
    public function handle(): int
    {
        $orderCutoff = now()->subDays(config('practice.order_retention_days'));
        $userCutoff = now()->subDays(config('practice.account_lifetime_days'));
        $tokenCutoff = now()->subMinutes((int) config('sanctum.expiration', 1440));

        $orders = Order::where('created_at', '<', $orderCutoff)->delete();
        $bookings = Booking::where('created_at', '<', $orderCutoff)->delete();
        $carts = CartItem::where('updated_at', '<', $orderCutoff)->delete();
        $tokens = PersonalAccessToken::where('created_at', '<', $tokenCutoff)->delete();

        // Accounts expire a fixed time after registration; their tokens go too,
        // so an open browser session is logged out on its next request.
        $expired = User::where('is_demo', false)->where('created_at', '<', $userCutoff)->pluck('id');
        $tokens += PersonalAccessToken::where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $expired)->delete();
        $users = User::whereIn('id', $expired)->delete();

        $this->info("Pruned {$orders} orders, {$bookings} bookings, {$carts} cart items, {$tokens} tokens, {$users} users.");

        return self::SUCCESS;
    }
}
