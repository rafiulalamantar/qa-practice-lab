<?php

namespace App\Support;

/**
 * Static fixtures for the stateless practice modules. Kept in code rather
 * than the database so those pages render with zero queries.
 */
class PracticeData
{
    public const COURSES = [
        ['QA Lab Mentor', 'Playwright with TypeScript from Scratch', 30],
        ['QA Lab Mentor', 'Selenium WebDriver with Java: Basics to Frameworks', 25],
        ['QA Lab Mentor', 'Cypress End-to-End Testing Bootcamp', 30],
        ['QA Lab Mentor', 'REST API Testing with Postman and RestAssured', 20],
        ['QA Lab Mentor', 'Performance Testing with JMeter and k6', 25],
        ['QA Lab Mentor', 'Mobile Automation with Appium', 35],
        ['QA Lab Mentor', 'SQL for Testers: Database Validation', 25],
        ['QA Lab Mentor', 'CI/CD for Test Automation with GitHub Actions', 25],
        ['QA Lab Mentor', 'Page Object Model and Test Design Patterns', 20],
        ['QA Lab Mentor', 'Writing a QA Resume That Gets Interviews', 0],
    ];

    public const STAFF = [
        ['Alex', 'Engineer', 'Dhaka', 28],
        ['Ben', 'Mechanic', 'Chattogram', 23],
        ['Dwayne', 'Manager', 'Sylhet', 48],
        ['Ivory', 'Receptionist', 'Dhaka', 18],
        ['Jack', 'Engineer', 'Khulna', 32],
        ['Joe', 'Postman', 'Dhaka', 46],
        ['Raymond', 'Businessman', 'Rajshahi', 37],
        ['Ronaldo', 'Sportsman', 'Dhaka', 31],
        ['Smith', 'Cricketer', 'Barishal', 33],
    ];

    /** @return list<array<string, mixed>> */
    public static function protoshopProducts(): array
    {
        return array_map(fn (array $p) => $p + ['imageUrl' => asset($p['image'])], [
            ['id' => 1, 'name' => 'Pixel Nova 9', 'brand' => 'Nova Labs', 'price' => 799.99, 'image' => 'images/products/phone-nova.svg', 'description' => '6.4" OLED, 128 GB'],
            ['id' => 2, 'name' => 'Orbit Lite 5G', 'brand' => 'Orbit', 'price' => 299.99, 'image' => 'images/products/phone-orbit.svg', 'description' => 'Big battery, 5G ready'],
            ['id' => 3, 'name' => 'Forgebook Pro 14', 'brand' => 'Forge', 'price' => 1299.00, 'image' => 'images/products/laptop-forge.svg', 'description' => '16 GB RAM, 512 GB SSD'],
            ['id' => 4, 'name' => 'Studybook Air', 'brand' => 'Forge', 'price' => 649.50, 'image' => 'images/products/laptop-air.svg', 'description' => 'Thin and light'],
        ]);
    }
}
