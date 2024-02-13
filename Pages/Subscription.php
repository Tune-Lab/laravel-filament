<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;

class Subscription extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Purchases';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.subscription';

    public ?array $data = [];

    public function mount(): void
    {
        $product = Setting::stripeProduct();
        $price = Setting::stripePrice($product->default_price);

        $this->form->fill([
            'product_id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price_id' => $price->id,
            'unit_amount' => floatval($price->unit_amount / 100),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $stripe = Cashier::stripe();
        $price = Setting::stripePrice($state['price_id']);

        $unitAmount = intval($state['unit_amount'] * 100);

        try {
            if ($price->unit_amount !== $unitAmount) {
                $newPrice = $stripe->prices->create([
                    'product' => $state['product_id'],
                    'unit_amount' => $unitAmount,
                    'currency' => 'usd',
                    'recurring' => ['interval' => 'year'],
                ]);

                $stripe->products->update($state['product_id'], [
                    'name' => $state['name'],
                    'description' => $state['description'],
                    'default_price' => $newPrice->id,
                ]);

                $stripe->prices->update($state['price_id'], [
                    'active' => false,
                ]);

                Setting::getData('stripe', 'price')->update(['value' => $newPrice->id]);

            } else {
                $stripe->products->update($state['product_id'], [
                    'name' => $state['name'],
                    'description' => $state['description'],
                ]);
            }

            Notification::make()->title('Your subscription properties has been updated.')->success()->send();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            Notification::make()->title('Subscription update failed.')->body($exception->getMessage())->danger()->send();
        }
    }

    public function getCancelButtonUrlProperty(): string
    {
        return static::getUrl();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Product information')
                ->collapsible()
                ->schema([
                    Hidden::make('product_id'),
                    TextInput::make('name')->required()->maxLength(50),
                    Textarea::make('description')->maxLength(255),
                ]),
            Section::make('Price information')
                ->collapsible()
                ->schema([
                    Hidden::make('price_id'),
                    TextInput::make('unit_amount')->required()->numeric()->minValue(0)->prefix('$'),
                ]),
        ])->statePath('data');
    }
}
