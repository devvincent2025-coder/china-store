<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesViaPermissions;
use App\Filament\Resources\HomeSectionResource\Pages;
use App\Filament\Resources\HomeSectionResource\RelationManagers\ItemsRelationManager;
use App\Models\HomeSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * "Homepage sections" — every block on the storefront homepage: its name/text (EN/ZH/FR), order,
 * visibility & schedule, and for product sections the hand-picked products with optional real
 * deal prices (managed in ItemsRelationManager on the edit page).
 */
class HomeSectionResource extends Resource
{
    use AuthorizesViaPermissions;

    protected static ?string $model = HomeSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Storefront';

    protected static ?string $navigationLabel = 'Homepage sections';

    protected static ?string $modelLabel = 'homepage section';

    protected static ?int $navigationSort = 1;

    private const LOCALES = ['en' => 'English', 'zh' => 'Chinese', 'fr' => 'French'];

    private const TRUST_ICONS = [
        'shield' => 'Shield (verified)',
        'truck' => 'Truck (delivery)',
        'lock' => 'Lock (secure)',
        'tag' => 'Tag (pricing)',
        'star' => 'Star (quality)',
        'gift' => 'Gift (offers)',
        'headset' => 'Headset (support)',
        'refresh' => 'Arrows (returns)',
    ];

    /** One input per locale for a translatable value stored as {en, zh, fr}. */
    private static function translated(string $path, string $label, bool $textarea = false, bool $requiredEn = false): array
    {
        return collect(self::LOCALES)->map(function ($name, $locale) use ($path, $label, $textarea, $requiredEn) {
            $field = $textarea
                ? Forms\Components\Textarea::make("{$path}.{$locale}")->rows(3)
                : Forms\Components\TextInput::make("{$path}.{$locale}");

            return $field->label("{$label} ({$name})")->required($requiredEn && $locale === 'en');
        })->values()->all();
    }

    private static function isType(Get $get, string ...$types): bool
    {
        return in_array($get('type'), $types, true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Section')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(HomeSection::TYPES)
                        ->required()
                        ->live()
                        ->native(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(fn () => (int) HomeSection::max('sort_order') + 10)
                        ->helperText('Lower numbers appear higher on the page. You can also drag rows in the list.'),
                    Forms\Components\Toggle::make('is_active')->label('Visible on homepage')->default(true)->inline(false),
                ]),

            Forms\Components\Section::make('Name & text')
                ->description('Shown on the homepage. Chinese/French fall back to English when left empty.')
                ->columns(3)
                ->schema([
                    ...self::translated('title', 'Section name', requiredEn: true),
                    Forms\Components\Group::make(self::translated('eyebrow', 'Small label above name'))
                        ->columns(3)->columnSpanFull()
                        ->visible(fn (Get $get) => self::isType($get, 'coupons', 'top_picks', 'product_grid', 'sell_cta', 'faq')),
                    Forms\Components\Group::make(self::translated('subtitle', 'Subtitle / description', textarea: true))
                        ->columns(3)->columnSpanFull()
                        ->visible(fn (Get $get) => self::isType($get, 'hero', 'collection', 'sell_cta')),
                ]),

            Forms\Components\Section::make('Products')
                ->description('Add products (and optional deal prices) in the "Products in this section" table below after saving.')
                ->columns(2)
                ->visible(fn (Get $get) => self::isType($get, ...HomeSection::PRODUCT_TYPES))
                ->schema([
                    Forms\Components\TextInput::make('item_limit')
                        ->label('Number of products to show')
                        ->numeric()->minValue(1)->maxValue(60)->default(8)->required(),
                    Forms\Components\Toggle::make('auto_fill')
                        ->label('Fill remaining slots automatically')
                        ->helperText('When fewer products are hand-picked than the limit, top up with popular / random listed products.')
                        ->default(true)->inline(false),
                ]),

            Forms\Components\Section::make('Schedule')
                ->columns(2)
                ->schema([
                    Forms\Components\DateTimePicker::make('starts_at')->label('Show from')->seconds(false),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label(fn (Get $get) => $get('type') === 'flash_deals' ? 'Deal ends at (countdown target)' : 'Hide after')
                        ->helperText(fn (Get $get) => $get('type') === 'flash_deals'
                            ? 'The countdown runs to this time and the section (and its deal prices) ends then. Leave empty for a countdown to midnight every day.'
                            : 'Leave both empty to always show.')
                        ->after('starts_at')
                        ->seconds(false),
                ]),

            Forms\Components\Section::make('Hero')
                ->visible(fn (Get $get) => self::isType($get, 'hero'))
                ->description('Slides are managed under Catalog → Banners (placement: Homepage). The name/subtitle above are shown when there are no active banners.')
                ->schema([
                    Forms\Components\Toggle::make('settings.show_account_card')->label('Show account card beside the banner (desktop)')->default(true),
                ]),

            Forms\Components\Section::make('Collection card style')
                ->visible(fn (Get $get) => self::isType($get, 'collection'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('settings.theme')->label('Colour theme')->options(HomeSection::COLLECTION_THEMES)->default('rose')->native(false),
                    Forms\Components\TextInput::make('settings.link_url')->label('Card links to')->placeholder('/products')->default('/products'),
                ]),

            Forms\Components\Section::make('Button')
                ->visible(fn (Get $get) => self::isType($get, 'sell_cta'))
                ->columns(3)
                ->schema([
                    ...self::translated('settings.button_label', 'Button text'),
                    Forms\Components\TextInput::make('settings.button_url')
                        ->label('Button links to')->placeholder('/open-shop')
                        ->helperText('Logged-in vendors are always sent to their dashboard instead.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Trust items')
                ->visible(fn (Get $get) => self::isType($get, 'trust'))
                ->schema([
                    Forms\Components\Repeater::make('settings.items')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('icon')->options(self::TRUST_ICONS)->default('shield')->required()->native(false)->columnSpanFull(),
                            ...self::translated('title', 'Title', requiredEn: true),
                            ...self::translated('text', 'Text'),
                        ])
                        ->columns(3)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['title']['en'] ?? null)
                        ->maxItems(8)
                        ->addActionLabel('Add trust item'),
                ]),

            Forms\Components\Section::make('Questions')
                ->visible(fn (Get $get) => self::isType($get, 'faq'))
                ->description('Also published to search engines as FAQ structured data.')
                ->schema([
                    Forms\Components\Repeater::make('settings.faqs')
                        ->label('')
                        ->schema([
                            ...self::translated('question', 'Question', requiredEn: true),
                            ...self::translated('answer', 'Answer', textarea: true, requiredEn: true),
                        ])
                        ->columns(3)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['question']['en'] ?? null)
                        ->addActionLabel('Add question'),
                ]),

            Forms\Components\Placeholder::make('managed_elsewhere')
                ->hiddenLabel()
                ->columnSpanFull()
                ->visible(fn (Get $get) => self::isType($get, 'categories', 'coupons', 'top_picks'))
                ->content(fn (Get $get) => match ($get('type')) {
                    'categories' => 'Tiles come from Catalog → Categories (active top-level categories, in their sort order).',
                    'coupons' => 'Coupons come from Coupons (active platform-wide coupons).',
                    'top_picks' => 'Stores come from Ad Campaigns (active paid placements).',
                    default => '',
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Section name')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('title', 'en') ?: '—')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => HomeSection::TYPES[$state] ?? $state)
                    ->color(fn ($state) => in_array($state, HomeSection::PRODUCT_TYPES, true) ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Hand-picked products')
                    ->formatStateUsing(fn ($state, $record) => $record->hasProducts() ? $state : '—'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Visible'),
                Tables\Columns\TextColumn::make('ends_at')->label('Ends')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(HomeSection::TYPES),
            ])
            ->headerActions([
                Tables\Actions\Action::make('preview')
                    ->label('View homepage')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url('/', shouldOpenInNewTab: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeSections::route('/'),
            'create' => Pages\CreateHomeSection::route('/create'),
            'edit' => Pages\EditHomeSection::route('/{record}/edit'),
        ];
    }
}
