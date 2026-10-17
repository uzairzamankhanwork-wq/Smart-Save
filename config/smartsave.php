<?php

return [

    // Hard cap on registered accounts (admins included).
    'max_users' => (int) env('SMARTSAVE_MAX_USERS', 3),

    // Minutes of inactivity before a signed-in user is logged out.
    'idle_timeout' => (int) env('SMARTSAVE_IDLE_TIMEOUT', 30),

    // Supported account currencies. Amounts are never converted between them.
    'currencies' => [
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'Rs'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR'],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$'],
    ],

    'goal_categories' => [
        'emergency' => ['label' => 'Emergency Fund', 'icon' => 'shield', 'color' => 'rose'],
        'car' => ['label' => 'Car', 'icon' => 'car', 'color' => 'sky'],
        'house' => ['label' => 'House', 'icon' => 'home', 'color' => 'amber'],
        'education' => ['label' => 'Education', 'icon' => 'academic', 'color' => 'violet'],
        'travel' => ['label' => 'Travel', 'icon' => 'plane', 'color' => 'cyan'],
        'electronics' => ['label' => 'Electronics', 'icon' => 'device', 'color' => 'indigo'],
        'wedding' => ['label' => 'Wedding', 'icon' => 'heart', 'color' => 'pink'],
        'other' => ['label' => 'Other', 'icon' => 'piggy-bank', 'color' => 'emerald'],
    ],

    'goal_icons' => ['piggy-bank', 'shield', 'car', 'home', 'academic', 'plane', 'device', 'heart', 'gift', 'star', 'briefcase', 'sparkles'],

    'goal_colors' => ['emerald', 'teal', 'sky', 'indigo', 'violet', 'pink', 'rose', 'amber', 'cyan', 'slate'],

    'expense_categories' => [
        'food' => 'Food',
        'transport' => 'Transport',
        'shopping' => 'Shopping',
        'bills' => 'Bills',
        'health' => 'Health',
        'entertainment' => 'Entertainment',
        'family' => 'Family',
        'other' => 'Other',
    ],

    'frequencies' => [
        'weekly' => 'Weekly',
        'biweekly' => 'Every 2 weeks',
        'monthly' => 'Monthly',
    ],

    // Largest single amount accepted anywhere (fits DECIMAL(15,2) with headroom).
    'max_amount' => '999999999999.99',

    'milestones' => [25, 50, 75, 100],

    // Reminder defaults; each user can override these on their profile.
    'notification_defaults' => [
        'deadline' => true,
        'monthly_target' => true,
        'milestone' => true,
        'budget' => true,
        'inactive_goal' => true,
        'goal_completed' => true,
        'email' => false,
    ],
];
