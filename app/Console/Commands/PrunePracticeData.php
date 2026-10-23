<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

#[Signature('practice:prune')]
#[Description('Delete old orders, stale carts, expired tokens and inactive learner accounts')]
class PrunePracticeData extends Command
{
    public function handle(): int
    {
        $orderCutoff = now()->subDays(config('practice.order_retention_days'));
        $userCutoff = now()->subDays(config('practice.inactive_user_days'));
        $tokenCutoff = now()->subMinutes((int) config('sanctum.expiration', 1440));

        $orders = Order::where('created_at', '<', $orderCutoff)->delete();
        $carts = CartItem::where('updated_at', '<', $orderCutoff)->delete();
        $tokens = PersonalAccessToken::where('created_at', '<', $tokenCutoff)->delete();
        $users = User::where('is_demo', false)
            ->where(fn ($q) => $q->where('last_login_at', '<', $userCutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_login_at')->where('created_at', '<', $userCutoff)))
            ->delete();

        $this->info("Pruned {$orders} orders, {$carts} cart items, {$tokens} tokens, {$users} users.");

        return self::SUCCESS;
    }
}
