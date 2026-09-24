<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication (Google Authenticator / Authy compatible) — entirely local, no
 * SMS/email OTP provider involved. A user opts in from account settings; once confirmed, the login
 * flow (see Auth\AuthenticatedSessionController) requires a valid code before completing the
 * session. This is deliberately NOT the same thing as a one-time login code sent by SMS/email —
 * the spec asked explicitly for authenticator-app-based 2FA, not OTP.
 */
class TwoFactorAuthenticationService
{
    public function __construct(private readonly Google2FA $engine = new Google2FA)
    {
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Renders the otpauth:// QR code as inline SVG so the settings page can display it without a
     * round-trip to any external QR-image service.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $otpAuthUrl = $this->engine->getQRCodeUrl(
            config('app.name'),
            $user->email ?? $user->phone,
            $secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($otpAuthUrl);

        // Strip the XML prolog so this can be embedded directly via dangerouslySetInnerHTML.
        return Str::after($svg, "\n");
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code) !== false;
    }

    /**
     * Ten single-use codes shown to the user exactly once, immediately after they confirm 2FA —
     * their fallback if they lose access to their authenticator app.
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 10))
            ->map(fn () => Str::upper(Str::random(4).'-'.Str::random(4)))
            ->all();
    }
}
