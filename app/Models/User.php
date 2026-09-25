<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Implements OAuthenticatable (Passport's HasApiTokens trait requires it) so a buyer can go
 * through a real OAuth2 login/consent screen with an AI tool (see routes/oauth.php doc comment
 * and docs/ai-ordering/) instead of ever pasting a token into it.
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, HasRoles, Notifiable, HasPushSubscriptions, MustVerifyEmailTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'otp_hash',
        'otp_expires_at',
        'otp_last_sent_at',
        'requires_phone_verification',
        'locale',
        'is_suspended',
        'suspension_reason',
        'suspended_at',
        'marketing_opt_out',
        'avatar_path',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_last_sent_at' => 'datetime',
            'requires_phone_verification' => 'boolean',
            'password' => 'hashed',
            // Encrypted at rest — these are effectively a second password, so they're never stored
            // or logged in plaintext even though the whole 2FA scheme is otherwise self-hosted.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'marketing_opt_out' => 'boolean',
        ];
    }

    /**
     * super_admin always gets in (see the Gate::before bypass in AppServiceProvider::boot()); every
     * other role — including any new sub-admin role created via Filament\Resources\RoleResource —
     * needs the 'access_admin_panel' permission checked on it to reach /admin at all. Vendors and
     * buyers have neither, so they're locked out and must use their own Inertia dashboards.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return ! $this->is_suspended
            && ($this->hasRole('super_admin', 'web') || $this->checkPermissionTo('access_admin_panel', 'web'));
    }

    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(ShippingAddress::class)->orderByDesc('is_default')->latest();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function isVendor(): bool
    {
        return $this->hasRole('vendor');
    }

    /**
     * True only for a buyer created via the new OTP signup flow (see BuyerSignupService) who
     * hasn't finished the phone-verification step yet — never true for accounts that existed
     * before that flow shipped, since `requires_phone_verification` defaults to false for them.
     */
    public function needsPhoneVerification(): bool
    {
        return $this->requires_phone_verification && ! $this->phone_verified_at;
    }

    /**
     * Vendor-facing notifications are sent via $vendor->user->notify() (not $vendor->notify()) so
     * every in-app notification — buyer or vendor — is stored under the same `users` notifiable
     * type and shows up in the one shared NotificationBell.jsx / /notifications page. Mail still
     * goes to the vendor's own contact_email (which a vendor can set independently of their login
     * email in Settings) rather than silently falling back to the account email.
     */
    public function routeNotificationForMail(): string
    {
        return $this->vendor?->contact_email ?? $this->email;
    }

    /**
     * Where to send this user right after they authenticate — used by both the plain
     * email/phone+password flow and the post-2FA-challenge flow (Auth\AuthenticatedSessionController
     * and Auth\TwoFactorChallengeController), so the two never drift into inconsistent behavior.
     * A vendor lands on their own dashboard, not the buyer storefront; the vendor.active middleware
     * takes it from there and bounces them to onboarding first if their store isn't live yet.
     */
    public function postLoginRoute(): string
    {
        return $this->isVendor() ? route('vendor.dashboard') : route('home');
    }

    /**
     * True only once a generated secret has actually been confirmed with a valid code — a secret
     * can exist on the record mid-setup without this being true, and login must not be gated on an
     * unconfirmed secret (the user might abandon setup partway through).
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}
