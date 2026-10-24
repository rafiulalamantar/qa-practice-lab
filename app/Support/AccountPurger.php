<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Removes learner accounts together with everything they own.
 *
 * Child rows are deleted explicitly, children before parents, instead of
 * relying on ON DELETE CASCADE: TiDB only enforces foreign keys on newer
 * versions with foreign_key_checks enabled, so a cascade that silently does
 * not fire would leave orphaned rows behind. Each chunk runs in its own
 * transaction, so an account is either removed completely or not at all.
 */
class AccountPurger
{
    private const CHUNK = 200;

    /**
     * Delete the given non-demo accounts and all of their data.
     *
     * @param  iterable<int>  $userIds
     * @return array{users: int, bookings: int, events: int, orders: int, cart_items: int, tokens: int}
     */
    public function purge(iterable $userIds): array
    {
        $counts = ['users' => 0, 'bookings' => 0, 'events' => 0, 'orders' => 0, 'cart_items' => 0, 'tokens' => 0];

        collect($userIds)->chunk(self::CHUNK)->each(function ($chunk) use (&$counts) {
            DB::transaction(function () use ($chunk, &$counts) {
                // The demo account printed on the login pages is never removed.
                $ids = User::whereIn('id', $chunk->values())->where('is_demo', false)->pluck('id');
                $eventIds = Event::whereIn('user_id', $ids)->pluck('id');

                // Bookings on the learner's own events go too, whoever made them.
                $counts['bookings'] += Booking::whereIn('user_id', $ids)->orWhereIn('event_id', $eventIds)->delete();
                $counts['events'] += Event::whereIn('id', $eventIds)->delete();
                $counts['orders'] += Order::whereIn('user_id', $ids)->delete();
                $counts['cart_items'] += CartItem::whereIn('user_id', $ids)->delete();
                $counts['tokens'] += $this->userTokens()->whereIn('tokenable_id', $ids)->delete();
                $counts['users'] += User::whereIn('id', $ids)->delete();
            });
        });

        return $counts;
    }

    /**
     * Delete rows whose owning user (or booked event) no longer exists.
     *
     * A safety net for data left behind by deletes that ran while foreign
     * keys were not enforced. Featured events have no owner and are kept.
     *
     * @return array{events: int, bookings: int, orders: int, cart_items: int, tokens: int}
     */
    public function sweepOrphans(): array
    {
        // Events are swept first so bookings on them are caught as orphans too.
        return DB::transaction(fn () => [
            'events' => Event::whereNotNull('user_id')->whereDoesntHave('user')->delete(),
            'bookings' => Booking::whereDoesntHave('user')->orWhereDoesntHave('event')->delete(),
            'orders' => Order::whereDoesntHave('user')->delete(),
            'cart_items' => CartItem::whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'cart_items.user_id'))->delete(),
            'tokens' => $this->userTokens()->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'personal_access_tokens.tokenable_id'))->delete(),
        ]);
    }

    private function userTokens(): Builder
    {
        return PersonalAccessToken::where('tokenable_type', (new User)->getMorphClass());
    }
}
