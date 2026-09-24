<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\VendorOrderDisputeResource\Pages;
use App\Models\VendorOrderDispute;
use App\Notifications\DisputeResolved;
use App\Services\Escrow\EscrowService;
use App\Services\Storage\SupabaseStorageException;
use App\Services\Storage\SupabaseStorageService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Where an admin arbitrates a buyer's "Report a Problem" — approving a refund to the buyer, a
 * release to the vendor, or (when the vendor genuinely ships a replacement) a release under a
 * distinct "replace" label. This is the impartial third party Jumia's own escrow model relies on
 * (support staff decide based on evidence from both sides, not the two sides negotiating directly),
 * and per the buyer-protection brief, the decision here is final.
 */
class VendorOrderDisputeResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = VendorOrderDispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Marketplace';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', 'open')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::where('status', 'open')
            ->where('vendor_response_deadline', '<', now())
            ->whereNull('vendor_responded_at')
            ->exists() ? 'danger' : 'warning';
    }

    private static function evidenceLinks(?array $paths): HtmlString
    {
        $paths = $paths ?? [];

        if (empty($paths)) {
            return new HtmlString('No photos submitted.');
        }

        // These live in the PRIVATE Supabase bucket — sign a short-lived URL per admin page load
        // rather than storing/showing a permanent link, since dispute photos are only meant to be
        // seen by the buyer, the vendor, and staff investigating the case. A signing failure must
        // not break the whole page — it just leaves that one photo unavailable.
        $storage = app(SupabaseStorageService::class);

        return new HtmlString(
            collect($paths)
                ->map(function ($path, $i) use ($storage) {
                    try {
                        return '<a href="'.e($storage->signedUrl($path)).'" target="_blank" rel="noopener" class="underline text-primary-600">Photo '.($i + 1).'</a>';
                    } catch (SupabaseStorageException) {
                        return 'Photo '.($i + 1).' (unavailable)';
                    }
                })
                ->implode('<br>')
        );
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('The order')
                    ->schema([
                        Forms\Components\TextInput::make('vendorOrder.order.order_number')->label('Order #')->disabled(),
                        Forms\Components\TextInput::make('vendorOrder.vendor.business_name')->label('Vendor')->disabled(),
                        Forms\Components\TextInput::make('status')->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make("Buyer's report")
                    ->schema([
                        Forms\Components\TextInput::make('reason')->disabled(),
                        Forms\Components\Textarea::make('description')->disabled()->columnSpanFull(),
                        Forms\Components\Placeholder::make('evidence')
                            ->label('Buyer photos')
                            ->content(fn (?VendorOrderDispute $record) => self::evidenceLinks($record?->evidence)),
                    ])
                    ->columns(2),

                Forms\Components\Section::make("Vendor's response")
                    ->schema([
                        Forms\Components\Placeholder::make('vendor_response_deadline')
                            ->label('Response due by')
                            ->content(function (?VendorOrderDispute $record) {
                                if (! $record?->vendor_response_deadline) {
                                    return '—';
                                }

                                return $record->vendor_response_deadline->format('d M Y, g:i a')
                                    .($record->vendorResponseOverdue() ? ' — OVERDUE, no response yet' : '');
                            }),
                        Forms\Components\Placeholder::make('vendor_responded_at')
                            ->label('Responded at')
                            ->content(fn (?VendorOrderDispute $record) => $record?->vendor_responded_at?->format('d M Y, g:i a') ?? 'Not yet responded.'),
                        Forms\Components\Textarea::make('vendor_response')->label('Vendor\'s side')->disabled()->columnSpanFull(),
                        Forms\Components\Placeholder::make('vendor_evidence')
                            ->label('Vendor photos')
                            ->content(fn (?VendorOrderDispute $record) => self::evidenceLinks($record?->vendor_evidence)),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Resolution')
                    ->schema([
                        // Honest label — this note is actually emailed to the buyer verbatim (see
                        // DisputeResolved), not kept internal, so admins should write it that way.
                        Forms\Components\Textarea::make('resolution_note')->label('Note (sent to the buyer)')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('resolvedBy.name')->label('Resolved by')->disabled(),
                        Forms\Components\Placeholder::make('resolved_at')
                            ->label('Resolved at')
                            ->content(fn (?VendorOrderDispute $record) => $record?->resolved_at?->format('d M Y, g:i a') ?? '—'),
                    ])
                    ->columns(3)
                    ->visible(fn (?VendorOrderDispute $record) => $record && $record->status !== 'open'),
            ])
            ->disabled();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('vendorOrder.order.order_number')->label('Order #')->searchable(),
                Tables\Columns\TextColumn::make('vendorOrder.vendor.business_name')->label('Vendor')->searchable(),
                Tables\Columns\TextColumn::make('reason')->badge(),
                Tables\Columns\TextColumn::make('description')->limit(60),
                Tables\Columns\IconColumn::make('vendor_responded_at')
                    ->label('Vendor responded')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon(fn (VendorOrderDispute $record) => $record->vendorResponseOverdue() ? 'heroicon-o-exclamation-circle' : 'heroicon-o-clock')
                    ->falseColor(fn (VendorOrderDispute $record) => $record->vendorResponseOverdue() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'approved_refund' => 'danger',
                        'approved_replace' => 'info',
                        'approved_release' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'approved_refund' => 'Approved — Refund',
                        'approved_replace' => 'Approved — Replace',
                        'approved_release' => 'Approved — Release (denied)',
                        'closed' => 'Closed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve_refund')
                    ->label('Approve Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The buyer gets a full refund and the vendor is not paid for this order — use this when the item was damaged, wrong, or fake.')
                    ->visible(fn (VendorOrderDispute $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('resolution_note')->label('Note (this is emailed to the buyer)')->required(),
                    ])
                    ->action(function (VendorOrderDispute $record, array $data) {
                        self::resolve($record, 'approved_refund', $data['resolution_note'], fn (EscrowService $escrow) => $escrow->refund($record->vendorOrder, reason: $record->reason));
                    }),
                Tables\Actions\Action::make('approve_replace')
                    ->label('Approve Replace')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('The vendor ships a genuine replacement instead of refunding — escrow still releases to the vendor since they are fulfilling the order, just via a replacement rather than the original item.')
                    ->visible(fn (VendorOrderDispute $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('resolution_note')->label('Note (this is emailed to the buyer)')->required(),
                    ])
                    ->action(function (VendorOrderDispute $record, array $data) {
                        self::resolve($record, 'approved_replace', $data['resolution_note'], function (EscrowService $escrow) use ($record, $data) {
                            $escrow->release($record->vendorOrder);
                            $record->vendorOrder->order->user->notify(new DisputeResolved($record, $data['resolution_note']));
                        });
                    }),
                Tables\Actions\Action::make('approve_release')
                    ->label('Deny — Release to Vendor')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription("The buyer's dispute is denied and the vendor is paid out as normal — use this when the order was fulfilled correctly.")
                    ->visible(fn (VendorOrderDispute $record) => $record->status === 'open')
                    ->form([
                        Forms\Components\Textarea::make('resolution_note')->label('Note (this is emailed to the buyer)')->required(),
                    ])
                    ->action(function (VendorOrderDispute $record, array $data) {
                        self::resolve($record, 'approved_release', $data['resolution_note'], function (EscrowService $escrow) use ($record, $data) {
                            $escrow->release($record->vendorOrder);
                            // The refund/replace paths already notify the buyer (either from the
                            // refund webhook via OrderRefunded, or directly above) — a plain denial
                            // has no other buyer-facing signal, so it's sent here instead. The note
                            // is passed directly rather than read off $record, since resolve() below
                            // doesn't persist resolution_note until AFTER this closure returns.
                            $record->vendorOrder->order->user->notify(new DisputeResolved($record, $data['resolution_note']));
                        });
                    }),
            ])
            ->bulkActions([]);
    }

    private static function resolve(VendorOrderDispute $record, string $status, string $note, \Closure $action): void
    {
        // The status is persisted BEFORE running the money-moving action, not after — a release
        // (approve_release/approve_replace) checks VendorOrder::hasOpenDispute(), which runs a fresh
        // query against this very row. Leaving it 'open' until after release() succeeds would mean
        // release() always sees its own still-open dispute and refuses. If the action throws, the
        // status is rolled back so nothing is left "resolved" without the money having moved.
        $record->update([
            'status' => $status,
            'resolution_note' => $note,
            'resolved_at' => now(),
            'resolved_by' => Filament::auth()->id(),
        ]);

        try {
            $action(app(EscrowService::class));

            \App\Models\AdminActivityLog::record("dispute.{$status}", $record, "Resolved dispute #{$record->id}: {$note}");

            Notification::make()->title('Dispute resolved')->success()->send();
        } catch (ValidationException $e) {
            $record->update([
                'status' => 'open',
                'resolution_note' => null,
                'resolved_at' => null,
                'resolved_by' => null,
            ]);

            Notification::make()->title('Could not resolve dispute')->body($e->validator->errors()->first())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorOrderDisputes::route('/'),
            'view' => Pages\ViewVendorOrderDispute::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
