<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Replaces the old static public/robots.txt (which had no Disallow rules and no Sitemap directive
 * at all) with a route-driven one, so the Sitemap: line always points at the correct environment's
 * APP_URL instead of needing a manually-edited static file per deploy.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $disallow = [
            '/cart', '/checkout', '/admin', '/vendor', '/login', '/register',
            '/forgot-password', '/reset-password', '/two-factor-challenge',
            '/orders', '/profile', '/notifications', '/account', '/support',
            '/favourites', '/track-order', '/verify-email', '/confirm-password',
            '/admin-impersonate',
            // Ad-tracking redirects, tokenised invoice pages, and JSON-only search endpoints — none
            // are landing pages, and crawling /go/ would also inflate campaign click counts.
            '/go/', '/invoices/', '/search/',
        ];

        $lines = ['User-agent: *'];

        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap.index');

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
