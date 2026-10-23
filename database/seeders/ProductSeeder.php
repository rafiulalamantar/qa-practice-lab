<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public const PRODUCTS = [
        ['AURORA SHELL JACKET', 'fashion', 'shirts', 'women', 3150, 'Water-resistant shell jacket with taped seams', 'jacket-aurora'],
        ['STRIDE RUNNER 2', 'fashion', 'shoes', 'men', 4799, 'Lightweight running shoe with foam midsole', 'shoe-stride'],
        ['CLASSIC CREW TEE', 'fashion', 't-shirts', 'unisex', 599, 'Organic cotton crew-neck t-shirt', 'tee-classic'],
        ['OXFORD BUTTON SHIRT', 'fashion', 'shirts', 'men', 1899, 'Slim-fit oxford shirt for office wear', 'shirt-oxford'],
        ['TRAIL HIKER MID', 'fashion', 'shoes', 'women', 5599, 'Ankle-support hiking boot with grippy outsole', 'shoe-hiker'],
        ['PIXEL NOVA 9', 'electronics', 'mobiles', 'unisex', 54999, '6.4 inch OLED phone with 128 GB storage', 'phone-nova'],
        ['ORBIT LITE 5G', 'electronics', 'mobiles', 'unisex', 18999, 'Budget 5G phone with 5000 mAh battery', 'phone-orbit'],
        ['FORGEBOOK PRO 14', 'electronics', 'laptops', 'unisex', 89999, '14 inch laptop, 16 GB RAM, 512 GB SSD', 'laptop-forge'],
        ['STUDYBOOK AIR', 'electronics', 'laptops', 'unisex', 42999, 'Thin and light laptop for students', 'laptop-air'],
        ['STEEL CHEF KETTLE', 'household', 'kitchen', 'unisex', 1299, '1.7 litre stainless steel electric kettle', 'kettle-steel'],
        ['BREW MASTER PRESS', 'household', 'kitchen', 'unisex', 999, 'Borosilicate glass coffee press, 600 ml', 'press-brew'],
        ['GRAPHIC LOGO TEE', 'fashion', 't-shirts', 'women', 799, 'Relaxed-fit tee with printed graphic', 'tee-graphic'],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as [$name, $category, $sub, $for, $price, $description, $image]) {
            Product::updateOrCreate(['name' => $name], [
                'category' => $category,
                'sub_category' => $sub,
                'product_for' => $for,
                'price' => $price,
                'description' => $description,
                'image' => "images/products/{$image}.svg",
            ]);
        }
    }
}
