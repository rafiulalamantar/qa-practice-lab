<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Support\AccountPurger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

#[Signature('practice:prune')]
#[Description('Delete old orders and bookings, stale carts, expired tokens, expired learner accounts and orphaned rows')]
class PrunePracticeData extends Command
{
    public function handle(AccountPurger $purger): int
    {
        $orderCutoff = now()->subDays(config('practice.order_retention_days'));
        $userCutoff = now()->subDays(config('practice.account_lifetime_days'));
        $tokenCutoff = now()->subMinutes((int) config('sanctum.expiration', 1440));

        $orders = Order::where('created_at', '<', $orderCutoff)->delete();
        $bookings = Booking::where('created_at', '<', $orderCutoff)->delete();
        $carts = CartItem::where('updated_at', '<', $orderCutoff)->delete();
        $tokens = PersonalAccessToken::where('created_at', '<', $tokenCutoff)->delete();

        // Accounts expire a fixed time after registration and take all of their
        // data with them; deleting their tokens logs out any open session.
        $expired = User::where('is_demo', false)->where('created_at', '<', $userCutoff)->pluck('id');
        $purged = $purger->purge($expired);
        $orphans = $purger->sweepOrphans();

        $this->info("Pruned {$orders} orders, {$bookings} bookings, {$carts} cart items, {$tokens} tokens.");
        $this->info('Expired accounts: '.json_encode($purged).'. Orphans swept: '.json_encode($orphans).'.');

        return self::SUCCESS;
    }
}
