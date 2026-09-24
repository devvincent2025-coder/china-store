<?php

namespace App\Support;

/**
 * Consistent ₦ formatting for server-rendered output (PDF/Excel exports) — the frontend has its
 * own formatNaira() in resources/js/utils/money.js; this is the PHP-side equivalent for anything
 * that never touches React.
 */
class Money
{
    public static function naira(?int $kobo): string
    {
        return '₦'.number_format(($kobo ?? 0) / 100, 2);
    }
}
