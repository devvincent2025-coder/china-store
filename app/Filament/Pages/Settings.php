<?php

namespace App\Filament\Pages;

use App\Models\AdminActivityLog;
use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Platform-wide configuration an admin can change without a redeploy. Every value here has a
 * config()/DB-column fallback (see the `default` on each field) so the platform behaves sensibly
 * even before an admin ever visits this page.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.settings';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->checkPermissionTo('manage_settings_page', 'web');
    }

    public ?array $data = [];

    private const CADENCE_OPTIONS = [
        'off' => 'Off',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ];

    public function mount(): void
    {
        $tiers = json_decode((string) Setting::get('commission_tiers', '[]'), true) ?: [];

        $this->form->fill([
            'default_commission_rate' => Setting::get('default_commission_rate', '10.00'),
            'default_vat_rate' => Setting::get('default_vat_rate', '7.50'),
            'escrow_return_window_days' => Setting::get('escrow_return_window_days', (string) config('services.escrow.return_window_days')),
            'support_email' => Setting::get('support_email', 'support@chinainnigeria.com'),
            'commission_tiers' => collect($tiers)->map(fn ($tier) => [
                'min_amount' => $tier['min_amount'] / 100,
                'max_amount' => $tier['max_amount'] !== null ? $tier['max_amount'] / 100 : null,
                'rate' => $tier['rate'],
            ])->all(),
            'notify_shop_reminder_cadence' => Setting::get('notify_shop_reminder_cadence', 'off'),
            'notify_new_arrivals_cadence' => Setting::get('notify_new_arrivals_cadence', 'off'),
            'notify_vendor_engagement_cadence' => Setting::get('notify_vendor_engagement_cadence', 'off'),
            'notify_new_stores_cadence' => Setting::get('notify_new_stores_cadence', 'off'),
            'ad_cost_per_day' => ((int) Setting::get('ad_cost_per_day_kobo', 500000)) / 100,
            'ad_min_days' => Setting::get('ad_min_days', 3),
            'ad_max_days' => Setting::get('ad_max_days', 30),
            'product_report_flag_threshold' => Setting::get('product_report_flag_threshold', 5),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Vendor defaults')
                    ->description('Applied to new vendors when they sign up. Existing vendors keep their own configured rates.')
                    ->schema([
                        TextInput::make('default_commission_rate')
                            ->label('Default commission rate (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100),
                        TextInput::make('default_vat_rate')
                            ->label('VAT rate (%)')
                            ->helperText('Applied to every order platform-wide — vendors can no longer set their own.')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->columns(2),

                Section::make('Commission tiers')
                    ->description('Optional volume-based commission brackets applied per order, by that order\'s own goods subtotal (₦) — the lowest matching bracket wins. Leave empty to just use each vendor\'s flat commission rate above. Example: ₦0–50,000 at 10%, ₦50,000–200,000 at 8%, ₦200,000+ at 5%.')
                    ->schema([
                        Repeater::make('commission_tiers')
                            ->label('Tiers')
                            ->schema([
                                TextInput::make('min_amount')->label('From (₦)')->numeric()->required()->minValue(0),
                                TextInput::make('max_amount')->label('To (₦, blank = no limit)')->numeric()->minValue(0),
                                TextInput::make('rate')->label('Rate (%)')->numeric()->required()->minValue(0)->maxValue(100),
                            ])
                            ->columns(3)
                            ->reorderable(false)
                            ->addActionLabel('+ Add Tier')
                            ->defaultItems(0),
                    ]),

                Section::make('Escrow')
                    ->description('How long a buyer has to confirm receipt or report a problem before a delivered order auto-releases to the vendor.')
                    ->schema([
                        TextInput::make('escrow_return_window_days')
                            ->label('Return window (days)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(90),
                    ]),

                Section::make('Support')
                    ->schema([
                        TextInput::make('support_email')
                            ->label('Support email')
                            ->email()
                            ->required(),
                    ]),

                Section::make('Product moderation')
                    ->description('Once an active product reaches this many OPEN reports, it is automatically taken down for buyer safety and admins are alerted to investigate — see App\Services\Product\ProductTakedownService::autoFlagForReports(). This never suspends the vendor by itself; that stays a manual decision after investigating.')
                    ->schema([
                        TextInput::make('product_report_flag_threshold')
                            ->label('Open reports before auto-flag')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ]),

                Section::make('Vendor store boosts (ads)')
                    ->description('What a vendor pays to run their store as a "Top Picks" placement on the homepage and other buyer-facing pages — see Services\Ads\AdCampaignService.')
                    ->schema([
                        TextInput::make('ad_cost_per_day')
                            ->label('Cost per day (₦)')
                            ->numeric()
                            ->required()
                            ->minValue(0),
                        TextInput::make('ad_min_days')
                            ->label('Minimum duration (days)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        TextInput::make('ad_max_days')
                            ->label('Maximum duration (days)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ])
                    ->columns(3),

                Section::make('Automated email notifications')
                    ->description('How often each of these goes out. Each email includes a button that takes the recipient straight to the relevant page.')
                    ->schema([
                        Select::make('notify_shop_reminder_cadence')
                            ->label('"Come back and shop" reminder to buyers')
                            ->options(self::CADENCE_OPTIONS)
                            ->required(),
                        Select::make('notify_new_arrivals_cadence')
                            ->label('New arrivals from a store a buyer bought from before')
                            ->options(self::CADENCE_OPTIONS)
                            ->required(),
                        Select::make('notify_vendor_engagement_cadence')
                            ->label('Vendor reminder — add products / run a discount')
                            ->options(self::CADENCE_OPTIONS)
                            ->required(),
                        Select::make('notify_new_stores_cadence')
                            ->label('New store announcement to buyers')
                            ->options(self::CADENCE_OPTIONS)
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tiers = collect($data['commission_tiers'] ?? [])
            ->filter(fn ($tier) => filled($tier['min_amount']) && filled($tier['rate']))
            ->map(fn ($tier) => [
                'min_amount' => (int) round(((float) $tier['min_amount']) * 100),
                'max_amount' => filled($tier['max_amount']) ? (int) round(((float) $tier['max_amount']) * 100) : null,
                'rate' => (float) $tier['rate'],
            ])
            ->values()
            ->all();

        unset($data['commission_tiers']);
        $data['commission_tiers'] = json_encode($tiers);

        if (isset($data['ad_cost_per_day'])) {
            $data['ad_cost_per_day_kobo'] = (int) round(((float) $data['ad_cost_per_day']) * 100);
            unset($data['ad_cost_per_day']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        AdminActivityLog::record('settings.updated', null, 'Updated platform settings: '.implode(', ', array_keys($data)));

        Notification::make()->title('Settings saved')->success()->send();
    }
}
