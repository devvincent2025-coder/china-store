<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

// Laravel 11+'s default skeleton ships this class bare — AuthorizesRequests must be added back
// explicitly for the $this->authorize(...) helper used throughout this app's controllers (product/
// coupon/banner ownership checks, order visibility, etc.) to work at all.
abstract class Controller
{
    use AuthorizesRequests;
}
