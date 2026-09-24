<?php

use App\Models\DevOtpLog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dev-only routes
|--------------------------------------------------------------------------
|
| Only ever required by web.php inside an `app()->environment('local')` check — never reachable
| in staging/production, since the route itself doesn't exist there at all (not just gated by
| middleware). See App\Support\Dev\OtpLogger for what writes to dev_otp_logs.
*/

Route::get('/dev/otps', function () {
    $logs = DevOtpLog::orderByDesc('id')->limit(100)->get();

    return view('dev.otps', ['logs' => $logs]);
})->name('dev.otps');
