<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductSeeder::class);

        foreach ([['PRACTICE10', 10], ['QA25', 25], ['FREESHIP', 5]] as [$code, $pct]) {
            Coupon::updateOrCreate(['code' => $code], ['discount_percent' => $pct, 'active' => true]);
        }
        Coupon::updateOrCreate(['code' => 'EXPIRED50'], ['discount_percent' => 50, 'active' => false]);

        // Shared demo account so learners can start without registering. It is
        // never pruned, but its orders are (see practice:prune).
        $demo = User::updateOrCreate(['email' => config('practice.demo_email')], [
            'first_name' => 'Demo',
            'last_name' => 'Learner',
            'mobile' => '9876543210',
            'occupation' => 'Engineer',
            'gender' => 'Female',
            'password' => config('practice.demo_password'),
        ]);
        $demo->forceFill(['is_demo' => true])->save();
    }
}
