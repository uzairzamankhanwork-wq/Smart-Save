<?php

namespace App\Support;

/**
 * Tailwind class maps for goal colours and tones. Class names are written out
 * in full so Tailwind's scanner can find them (app/Support is a @source).
 */
final class Ui
{
    private const GOAL = [
        'emerald' => ['soft' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', 'bar' => 'bg-emerald-500', 'ring' => 'ring-emerald-500/30'],
        'teal' => ['soft' => 'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300', 'bar' => 'bg-teal-500', 'ring' => 'ring-teal-500/30'],
        'sky' => ['soft' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'bar' => 'bg-sky-500', 'ring' => 'ring-sky-500/30'],
        'indigo' => ['soft' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300', 'bar' => 'bg-indigo-500', 'ring' => 'ring-indigo-500/30'],
        'violet' => ['soft' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300', 'bar' => 'bg-violet-500', 'ring' => 'ring-violet-500/30'],
        'pink' => ['soft' => 'bg-pink-100 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300', 'bar' => 'bg-pink-500', 'ring' => 'ring-pink-500/30'],
        'rose' => ['soft' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', 'bar' => 'bg-rose-500', 'ring' => 'ring-rose-500/30'],
        'amber' => ['soft' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300', 'bar' => 'bg-amber-500', 'ring' => 'ring-amber-500/30'],
        'cyan' => ['soft' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300', 'bar' => 'bg-cyan-500', 'ring' => 'ring-cyan-500/30'],
        'slate' => ['soft' => 'bg-slate-200 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300', 'bar' => 'bg-slate-500', 'ring' => 'ring-slate-500/30'],
    ];

    private const STATUS = [
        'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'paused' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'completed' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'archived' => 'bg-slate-200 text-slate-600 dark:bg-slate-700/60 dark:text-slate-300',
    ];

    private const TONE = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200',
    ];

    public static function goal(?string $color, string $part = 'soft'): string
    {
        return (self::GOAL[$color] ?? self::GOAL['emerald'])[$part];
    }

    public static function status(string $status): string
    {
        return self::STATUS[$status] ?? self::STATUS['archived'];
    }

    public static function tone(string $tone): string
    {
        return self::TONE[$tone] ?? self::TONE['info'];
    }

    /** Short encouraging line based on progress. Plain, never pushy. */
    public static function encouragement(float $percent): string
    {
        return match (true) {
            $percent >= 100 => 'Target reached. Brilliant work!',
            $percent >= 75 => 'The finish line is in sight.',
            $percent >= 50 => 'Over halfway there.',
            $percent >= 25 => 'Great momentum. Keep going.',
            $percent > 0 => 'A solid start.',
            default => 'Every journey starts with the first contribution.',
        };
    }
}
