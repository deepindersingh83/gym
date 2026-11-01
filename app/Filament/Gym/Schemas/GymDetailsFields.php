<?php

namespace App\Filament\Gym\Schemas;

use App\Models\Gym;
use App\Support\Country;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/** Form fields shared by the "register your gym" wizard and the gym settings page. */
class GymDetailsFields
{
    public static function basics(): array
    {
        return [
            TextInput::make('name')->label('Gym name')->required()->maxLength(120),
            Grid::make(2)->schema([
                TextInput::make('phone')->tel()->required()->maxLength(20),
                TextInput::make('whatsapp')->label('WhatsApp number')->tel()->maxLength(20),
                TextInput::make('email')->email()->maxLength(120),
            ]),
        ];
    }

    public static function location(): array
    {
        $isAu = fn (Get $get) => $get('country') === 'AU';

        return [
            Grid::make(2)->schema([
                Select::make('country')
                    ->options(Country::options())
                    ->default('IN')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('timezone', array_key_first(Country::get($state, 'timezones')))),
                Select::make('timezone')
                    ->label('Time zone')
                    ->options(fn (Get $get) => Country::get($get('country'), 'timezones'))
                    ->default('Asia/Kolkata')
                    ->required(),
            ]),
            Textarea::make('address')->rows(2),
            Grid::make(3)->schema([
                TextInput::make('city')->label(fn (Get $get) => $isAu($get) ? 'Suburb / city' : 'City'),
                TextInput::make('state'),
                TextInput::make('pincode')->label(fn (Get $get) => $isAu($get) ? 'Postcode' : 'PIN code')->maxLength(10),
            ]),
            // India: GSTIN, GST added to prices and split into CGST + SGST.
            Grid::make(2)->visible(fn (Get $get) => ! $isAu($get))->schema([
                TextInput::make('gstin')
                    ->label('GSTIN')
                    ->helperText('Leave empty if the gym is not GST registered. Invoices then show no GST.')
                    ->length(15)
                    ->regex('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/')
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : null),
                TextInput::make('state_code')
                    ->label('GST state code')
                    ->helperText('First two digits of the GSTIN, e.g. 27 for Maharashtra.')
                    ->maxLength(2),
            ]),
            // Australia: ABN; when registered for GST, prices include 10% GST.
            Grid::make(2)->visible($isAu)->schema([
                TextInput::make('abn')
                    ->label('ABN')
                    ->helperText('11 digits, shown on your invoices.')
                    ->rule('regex:/^\d{2}\s?\d{3}\s?\d{3}\s?\d{3}$/')
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/\D/', '', $state) : null),
                Toggle::make('gst_registered')
                    ->label('Registered for GST')
                    ->helperText('On: membership prices include 10% GST and invoices are tax invoices.')
                    ->inline(false),
            ]),
        ];
    }

    public static function openingHours(): array
    {
        return [
            Repeater::make('opening_hours')
                ->label('Opening hours')
                ->schema([
                    Select::make('day')->options(array_combine(Gym::DAYS, array_map('ucfirst', Gym::DAYS)))->required()->distinct(),
                    Toggle::make('closed')->inline(false)->live(),
                    TimePicker::make('opens')->seconds(false)->hidden(fn ($get) => $get('closed')),
                    TimePicker::make('closes')->seconds(false)->hidden(fn ($get) => $get('closed')),
                ])
                ->columns(4)
                ->default(static::defaultHours())
                ->addActionLabel('Add day')
                ->reorderable(false),
        ];
    }

    public static function branding(): array
    {
        return [
            FileUpload::make('logo_path')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory('gym-logos')
                ->visibility('public')
                ->maxSize(2048),
            ColorPicker::make('primary_color')->label('Brand colour')->default('#f59e0b'),
        ];
    }

    /** @return list<array{day: string, closed: bool, opens: ?string, closes: ?string}> */
    public static function defaultHours(): array
    {
        return array_map(fn (string $day) => [
            'day' => $day,
            'closed' => $day === 'sunday',
            'opens' => '05:30',
            'closes' => '22:00',
        ], Gym::DAYS);
    }
}
