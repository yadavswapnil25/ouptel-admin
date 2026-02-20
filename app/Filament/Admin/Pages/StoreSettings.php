<?php

namespace App\Filament\Admin\Pages;

use App\Models\StoreSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Filament\Admin\Concerns\HasPageAccess;

class StoreSettings extends Page implements HasForms
{
    use HasPageAccess;

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Store Settings';
    protected static ?string $title = 'Store Settings';
    protected static ?string $navigationGroup = 'Store';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'store_system' => StoreSetting::getValue(StoreSetting::STORE_ENABLED, 'on'),
            'store_commission' => StoreSetting::getValue(StoreSetting::COMMISSION_RATE, '0'),
            'store_review_system' => StoreSetting::getValue(StoreSetting::REVIEW_SYSTEM, 'off'),
            'product_visibility' => StoreSetting::getValue(StoreSetting::PRODUCT_VISIBILITY, '0'),
            'order_posts_by' => StoreSetting::getValue(StoreSetting::ORDER_POSTS_BY, '0'),
            'market_request' => StoreSetting::getValue(StoreSetting::MARKET_REQUEST, 'all'),
            'nearby_shop_system' => StoreSetting::getValue(StoreSetting::NEARBY_SHOP_SYSTEM, '0'),
            'store_currency' => StoreSetting::getValue(StoreSetting::CURRENCY, 'USD'),
            'store_min_order_amount' => StoreSetting::getValue(StoreSetting::MIN_ORDER_AMOUNT, '0'),
            'store_max_order_amount' => StoreSetting::getValue(StoreSetting::MAX_ORDER_AMOUNT, '10000'),
            'store_shipping_enabled' => StoreSetting::getValue(StoreSetting::SHIPPING_ENABLED, '1'),
            'store_shipping_cost' => StoreSetting::getValue(StoreSetting::SHIPPING_COST, '10'),
            'store_free_shipping_threshold' => StoreSetting::getValue(StoreSetting::FREE_SHIPPING_THRESHOLD, '100'),
            'store_payment_methods' => StoreSetting::getValue(StoreSetting::PAYMENT_METHODS, 'paypal,stripe'),
            'store_refund_policy' => StoreSetting::getValue(StoreSetting::REFUND_POLICY, '30 days refund policy'),
            'store_terms_conditions' => StoreSetting::getValue(StoreSetting::TERMS_CONDITIONS, 'Standard terms and conditions'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Settings')
                    ->schema([
                        Select::make('store_system')
                            ->label('Store System')
                            ->options([
                                'on' => 'Enabled',
                                'off' => 'Disabled',
                            ])
                            ->required(),

                        TextInput::make('store_commission')
                            ->label('Commission Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),

                        Select::make('store_review_system')
                            ->label('Review System')
                            ->options([
                                'on' => 'Enabled',
                                'off' => 'Disabled',
                            ])
                            ->required(),

                        Select::make('product_visibility')
                            ->label('Product Visibility')
                            ->options([
                                '0' => 'Public',
                                '1' => 'Private',
                            ])
                            ->required(),

                        Select::make('order_posts_by')
                            ->label('Order Posts By')
                            ->options([
                                '0' => 'Latest First',
                                '1' => 'Oldest First',
                            ])
                            ->required(),

                        Select::make('market_request')
                            ->label('Market Request')
                            ->options([
                                'all' => 'All Users',
                                'verified' => 'Verified Users Only',
                            ])
                            ->required(),

                        Select::make('nearby_shop_system')
                            ->label('Nearby Shop System')
                            ->options([
                                '0' => 'Disabled',
                                '1' => 'Enabled',
                            ])
                            ->required(),

                        Select::make('store_currency')
                            ->label('Default Currency')
                            ->options([
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                                'JPY' => 'JPY - Japanese Yen',
                                'CAD' => 'CAD - Canadian Dollar',
                                'AUD' => 'AUD - Australian Dollar',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Order Settings')
                    ->schema([
                        TextInput::make('store_min_order_amount')
                            ->label('Minimum Order Amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),

                        TextInput::make('store_max_order_amount')
                            ->label('Maximum Order Amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Shipping Settings')
                    ->schema([
                        Select::make('store_shipping_enabled')
                            ->label('Enable Shipping')
                            ->options([
                                '1' => 'Enabled',
                                '0' => 'Disabled',
                            ])
                            ->required(),

                        TextInput::make('store_shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),

                        TextInput::make('store_free_shipping_threshold')
                            ->label('Free Shipping Threshold')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Payment & Policies')
                    ->schema([
                        TextInput::make('store_payment_methods')
                            ->label('Payment Methods')
                            ->placeholder('paypal,stripe,credit_card')
                            ->helperText('Comma-separated list of payment methods')
                            ->required(),

                        Textarea::make('store_refund_policy')
                            ->label('Refund Policy')
                            ->rows(3)
                            ->required(),

                        Textarea::make('store_terms_conditions')
                            ->label('Terms & Conditions')
                            ->rows(3)
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            StoreSetting::setValue($key, $value);
        }

        Notification::make()
            ->title('Store settings saved successfully!')
            ->success()
            ->send();
    }
}
