<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\VendorResource\Pages;
use App\Models\Vendor;
use App\Services\Vendor\VendorBankChangeService;
use App\Services\Vendor\VendorIdentityService;
use App\Services\Vendor\VendorOnboardingService;
use App\Services\Vendor\VendorSuspensionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function getNavigationBadge(): ?string
    {
        $count = Vendor::where('bank_change_status', 'awaiting_approval')->count()
            + Vendor::where('identity_verification_status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Business details')
                    ->schema([
                        Forms\Components\TextInput::make('business_name')->disabled(),
                        Forms\Components\TextInput::make('contact_email')->disabled(),
                        Forms\Components\TextInput::make('contact_phone')->disabled(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'rejected' => 'Rejected',
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Verification documents')
                    ->schema([
                        Forms\Components\Placeholder::make('verification_documents')
                            ->label('Documents')
                            ->content(function (?Vendor $record) {
                                $paths = $record?->verification_documents ?? [];

                                if (empty($paths)) {
                                    return 'No documents uploaded.';
                                }

                                // These live in the PRIVATE Supabase bucket — sign a short-lived URL
                                // per admin page load rather than storing/showing a permanent link,
                                // since these are sensitive vendor documents.
                                $storage = app(\App\Services\Storage\SupabaseStorageService::class);

                                return new \Illuminate\Support\HtmlString(
                                    collect($paths)
                                        ->map(fn ($path, $i) => '<a href="'.e($storage->signedUrl($path)).'" target="_blank" rel="noopener" class="underline text-primary-600">Document '.($i + 1).'</a>')
                                        ->implode('<br>')
                                );
                            }),
                        Forms\Components\TextInput::make('documents_status')->disabled(),
                        Forms\Components\Textarea::make('documents_rejection_reason')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Bank & payout')
                    ->schema([
                        Forms\Components\TextInput::make('bank_code')->disabled(),
                        Forms\Components\TextInput::make('bank_name')->disabled(),
                        Forms\Components\TextInput::make('account_number')->disabled(),
                        Forms\Components\TextInput::make('resolved_account_name')->disabled(),
                        Forms\Components\TextInput::make('bank_verification_status')->disabled(),
                        Forms\Components\TextInput::make('korapay_subaccount_id')->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('KYC / Identity Verification')
                    ->description('Separate from the business documents above — this verifies the individual behind the account before any escrow payout is released. See EscrowService::release().')
                    ->schema([
                        Forms\Components\Placeholder::make('email_verified')
                            ->label('Email verified')
                            ->content(fn (?Vendor $record) => $record?->user?->hasVerifiedEmail() ? 'Yes' : 'No'),
                        Forms\Components\Placeholder::make('phone_verified')
                            ->label('Phone verified')
                            ->content(fn (?Vendor $record) => $record?->phone_verified_at ? $record->phone_verified_at->toDateTimeString() : 'No'),
                        Forms\Components\TextInput::make('identity_verification_status')->label('ID document status')->disabled(),
                        Forms\Components\Placeholder::make('identity_document_type')
                            ->label('Document type')
                            ->content(fn (?Vendor $record) => $record?->identity_document_type
                                ? str($record->identity_document_type)->replace('_', ' ')->title()
                                : 'Not submitted.'),
                        Forms\Components\Placeholder::make('identity_document')
                            ->label('ID document')
                            ->content(function (?Vendor $record) {
                                if (! $record?->identity_document_path) {
                                    return 'Not submitted.';
                                }

                                $storage = app(\App\Services\Storage\SupabaseStorageService::class);

                                return new \Illuminate\Support\HtmlString(
                                    '<a href="'.e($storage->signedUrl($record->identity_document_path)).'" target="_blank" rel="noopener" class="underline text-primary-600">View document</a>'
                                );
                            }),
                        Forms\Components\Textarea::make('identity_rejection_reason')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pending bank account change')
                    ->description('Vendor-confirmed via email OTP — call the vendor to verify before approving. Approving copies these into the live bank fields above and re-runs Korapay verification.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_change_status')->disabled(),
                        Forms\Components\TextInput::make('pending_bank_name')->disabled(),
                        Forms\Components\TextInput::make('pending_account_number')->disabled(),
                        Forms\Components\TextInput::make('pending_resolved_account_name')->label('Resolved account name')->disabled(),
                        Forms\Components\TextInput::make('bank_change_requested_at')->disabled(),
                        Forms\Components\Textarea::make('bank_change_rejection_reason')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Vendor $record): bool => $record && $record->bank_change_status !== 'none'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended', 'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('documents_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('bank_verification_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('identity_verification_status')
                    ->label('KYC')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'not_submitted' => 'Not submitted',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('korapay_subaccount_id')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bank_change_status')
                    ->label('Bank change')
                    ->badge()
                    ->visible(fn ($record) => ! $record || $record->bank_change_status !== 'none')
                    ->color(fn (string $state): string => match ($state) {
                        'awaiting_approval' => 'warning',
                        'otp_pending' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'awaiting_approval' => 'Awaiting approval',
                        'otp_pending' => 'OTP pending',
                        default => '—',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('documents_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('identity_verification_status')
                    ->label('KYC')
                    ->options([
                        'not_submitted' => 'Not submitted',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                static::approveDocumentsAction(),
                static::rejectDocumentsAction(),
                static::approveBankChangeAction(),
                static::rejectBankChangeAction(),
                static::approveIdentityAction(),
                static::rejectIdentityAction(),
                static::suspendAction(),
                static::reactivateAction(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function approveDocumentsAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('approveDocuments')
            ->label('Approve Documents')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Vendor $record): bool => in_array($record->documents_status, ['pending', 'rejected'], true))
            ->requiresConfirmation()
            ->action(function (Vendor $record) {
                app(VendorOnboardingService::class)->approveDocuments($record, auth()->user());

                \App\Models\AdminActivityLog::record('vendor.documents_approved', $record, "Approved documents for {$record->business_name}");

                Notification::make()
                    ->title('Vendor documents approved')
                    ->success()
                    ->send();
            });
    }

    public static function rejectDocumentsAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('rejectDocuments')
            ->label('Reject Documents')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Vendor $record): bool => $record->documents_status === 'pending')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Rejection reason')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Vendor $record, array $data) {
                app(VendorOnboardingService::class)->rejectDocuments($record, auth()->user(), $data['reason']);

                \App\Models\AdminActivityLog::record('vendor.documents_rejected', $record, "Rejected documents for {$record->business_name}: {$data['reason']}");

                Notification::make()
                    ->title('Vendor documents rejected')
                    ->danger()
                    ->send();
            });
    }

    public static function approveBankChangeAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('approveBankChange')
            ->label('Approve Bank Change')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Vendor $record): bool => $record->bank_change_status === 'awaiting_approval')
            ->requiresConfirmation()
            ->modalDescription('Confirm only after your support team has called the vendor to verify this change.')
            ->action(function (Vendor $record) {
                app(VendorBankChangeService::class)->approve($record, auth()->user());

                \App\Models\AdminActivityLog::record('vendor.bank_change_approved', $record, "Approved bank change for {$record->business_name}");

                Notification::make()->title('Bank account change approved')->success()->send();
            });
    }

    public static function rejectBankChangeAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('rejectBankChange')
            ->label('Reject Bank Change')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Vendor $record): bool => $record->bank_change_status === 'awaiting_approval')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Rejection reason')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Vendor $record, array $data) {
                app(VendorBankChangeService::class)->reject($record, auth()->user(), $data['reason']);

                \App\Models\AdminActivityLog::record('vendor.bank_change_rejected', $record, "Rejected bank change for {$record->business_name}: {$data['reason']}");

                Notification::make()->title('Bank account change rejected')->danger()->send();
            });
    }

    public static function approveIdentityAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('approveIdentity')
            ->label('Approve KYC')
            ->icon('heroicon-o-identification')
            ->color('success')
            ->visible(fn (Vendor $record): bool => $record->identity_verification_status === 'pending')
            ->requiresConfirmation()
            ->action(function (Vendor $record) {
                app(VendorIdentityService::class)->approveIdentity($record, auth()->user());

                \App\Models\AdminActivityLog::record('vendor.identity_approved', $record, "Approved KYC for {$record->business_name}");

                Notification::make()->title('Identity verification approved')->success()->send();
            });
    }

    public static function rejectIdentityAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('rejectIdentity')
            ->label('Reject KYC')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Vendor $record): bool => $record->identity_verification_status === 'pending')
            ->form([
                Forms\Components\Textarea::make('reason')->label('Rejection reason')->required()->columnSpanFull(),
            ])
            ->action(function (Vendor $record, array $data) {
                app(VendorIdentityService::class)->rejectIdentity($record, auth()->user(), $data['reason']);

                \App\Models\AdminActivityLog::record('vendor.identity_rejected', $record, "Rejected KYC for {$record->business_name}: {$data['reason']}");

                Notification::make()->title('Identity verification rejected')->danger()->send();
            });
    }

    /**
     * Always a deliberate, investigated decision — never fired automatically. Compare
     * App\Services\Product\ProductTakedownService::autoFlagForReports(), which pulls down a single
     * REPEATEDLY-reported product on its own, but explicitly never touches the vendor's account.
     */
    public static function suspendAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('suspend')
            ->label('Suspend Vendor')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Vendor $record): bool => $record->status !== 'suspended')
            ->requiresConfirmation()
            ->modalDescription('The vendor will be notified by email and in-app. Their storefront and dashboard become unreachable immediately.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason (shown to the vendor)')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Vendor $record, array $data) {
                app(VendorSuspensionService::class)->suspend($record, auth()->user(), $data['reason']);

                Notification::make()->title('Vendor suspended')->success()->send();
            });
    }

    public static function reactivateAction(string $actionClass = Tables\Actions\Action::class): Tables\Actions\Action|\Filament\Actions\Action
    {
        return $actionClass::make('reactivate')
            ->label('Reactivate Vendor')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Vendor $record): bool => $record->status === 'suspended')
            ->requiresConfirmation()
            ->action(function (Vendor $record) {
                app(VendorSuspensionService::class)->reactivate($record);

                Notification::make()->title('Vendor reactivated')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'view' => Pages\ViewVendor::route('/{record}'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
