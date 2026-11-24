<?php

namespace App\Filament\Resources\HomeSectionResource\RelationManagers;

use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Hand-picked products for a product-type homepage section, in display order, each with an
 * optional deal price (₦ in the form, stored as kobo). A live deal price is the price buyers are
 * actually charged — see App\Services\Pricing\DealPricing.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Products in this section';

    protected static ?string $modelLabel = 'product';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof HomeSection && $ownerRecord->hasProducts();
    }

    private static function naira(?int $kobo): string
    {
        return $kobo === null ? '—' : '₦'.number_format($kobo / 100, 2);
    }

    private static function productLabel(Product $product): string
    {
        $vendor = $product->vendor?->business_name;

        return trim($product->getTranslation('title', 'en').($vendor ? " · {$vendor}" : '').' · '.self::naira($product->price));
    }

    public function form(Form $form): Form
    {
        return $form->columns(2)->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->required()
                ->searchable()
                ->live()
                ->columnSpanFull()
                ->helperText('Only products that are publicly listed (published, vendor verified) appear on the homepage.')
                ->getSearchResultsUsing(fn (string $search) => Product::query()
                    ->with('vendor')
                    ->where('status', 'published')
                    ->where(fn ($q) => $q->where('title->en', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                    ->orderBy('title->en')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Product $p) => [$p->id => self::productLabel($p)])
                    ->all())
                ->getOptionLabelUsing(fn ($value) => ($p = Product::with('vendor')->find($value)) ? self::productLabel($p) : null)
                ->unique(
                    table: HomeSectionItem::class,
                    column: 'product_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('home_section_id', $this->getOwnerRecord()->getKey()),
                )
                ->validationMessages(['unique' => 'This product is already in this section.']),

            Forms\Components\Placeholder::make('normal_price')
                ->label('Normal price')
                ->content(function (Get $get) {
                    $product = Product::with('variants')->find($get('product_id'));
                    if (! $product) {
                        return 'Select a product';
                    }
                    $note = $product->variants->whereNotNull('price')->isNotEmpty()
                        ? ' — note: variants with their own price are not discounted by a deal price.'
                        : '';

                    return self::naira($product->price).$note;
                }),

            Forms\Components\TextInput::make('deal_price')
                ->label('Deal price (₦)')
                ->numeric()
                ->minValue(1)
                ->step('0.01')
                ->prefix('₦')
                ->helperText('Optional. Must be below the normal price. While this item and section are live, buyers are charged this price in cart and checkout (vendor payout is based on it). Leave empty to show the normal price.')
                ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
                ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round(((float) $state) * 100) : null)
                ->rule(fn (Get $get) => function (string $attribute, $value, Closure $fail) use ($get) {
                    $product = Product::find($get('product_id'));
                    if ($product && filled($value) && (int) round(((float) $value) * 100) >= $product->price) {
                        $fail('The deal price must be lower than the normal price ('.self::naira($product->price).').');
                    }
                }),

            Forms\Components\DateTimePicker::make('starts_at')->label('Deal / placement starts')->seconds(false),
            Forms\Components\DateTimePicker::make('ends_at')->label('Deal / placement ends')->seconds(false)->after('starts_at'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with(['product.images', 'product.vendor']))
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(fn (HomeSectionItem $record) => ($img = $record->product?->images->firstWhere('is_primary', true) ?? $record->product?->images->first())
                        ? ($img->enhanced_url ?? $img->original_url)
                        : null)
                    ->square()
                    ->size(44),
                Tables\Columns\TextColumn::make('product.title')
                    ->label('Product')
                    ->formatStateUsing(fn (HomeSectionItem $record) => $record->product?->getTranslation('title', 'en') ?? '—')
                    ->description(fn (HomeSectionItem $record) => $record->product?->vendor?->business_name)
                    ->wrap(),
                Tables\Columns\TextColumn::make('normal_price')
                    ->label('Normal price')
                    ->getStateUsing(fn (HomeSectionItem $record) => self::naira($record->product?->price)),
                Tables\Columns\TextColumn::make('deal_price')
                    ->label('Deal price')
                    ->formatStateUsing(fn ($state) => self::naira($state))
                    ->placeholder('—')
                    ->color('success')
                    ->weight('bold')
                    ->description(fn (HomeSectionItem $record) => $record->deal_price && $record->product?->price
                        ? round((1 - $record->deal_price / $record->product->price) * 100).'% off'
                        : null),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(function (HomeSectionItem $record) {
                        if ($record->product?->status !== 'published') {
                            return 'Hidden (product unpublished)';
                        }
                        if ($record->starts_at && $record->starts_at->isFuture()) {
                            return 'Scheduled';
                        }
                        if ($record->ends_at && $record->ends_at->isPast()) {
                            return 'Ended';
                        }

                        return 'Live';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Live' => 'success',
                        'Scheduled' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('ends_at')->label('Ends')->dateTime()->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add product')
                    ->mutateFormDataUsing(fn (array $data) => $data + [
                        'sort_order' => (int) $this->getOwnerRecord()->items()->max('sort_order') + 1,
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->label('Remove'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Remove selected'),
                ]),
            ])
            ->emptyStateHeading('No hand-picked products yet')
            ->emptyStateDescription('Add products to control exactly what appears here. With automatic fill on, empty slots are filled with popular products.');
    }
}
