<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * Neutralise spreadsheet formula injection: text starting with = + - @ or a
     * tab/CR is prefixed with an apostrophe. Plain numbers (e.g. "-150.00") are
     * left alone so they stay numeric.
     */
    public static function safe(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '' || preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @param iterable<array> $rows */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Rs/€ correctly
            fputcsv($out, $header, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row), escape: '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
