<?php

namespace App\Filament\Gym\Support;

use App\Support\Height;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Height input in the gym's chosen unit. Always saves centimetres to height_cm;
 * in feet mode, two inputs (feet, inches) are converted on the fly.
 */
class HeightField
{
    public static function make(string $label = 'Height', ?string $helperText = null): Group|TextInput
    {
        if (! Height::usesFeet()) {
            return TextInput::make('height_cm')
                ->label($label)
                ->numeric()
                ->minValue(50)
                ->maxValue(250)
                ->suffix('cm')
                ->helperText($helperText);
        }

        $sync = fn (Get $get, Set $set) => $set('height_cm', Height::fromFeet($get('height_feet'), $get('height_inches')));

        return Group::make([
            Hidden::make('height_cm'),
            FusedGroup::make([
                TextInput::make('height_feet')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(8)
                    ->suffix('ft')
                    ->dehydrated(false)
                    ->live(onBlur: true)
                    ->afterStateHydrated(function (TextInput $component, Get $get) {
                        if (filled($get('height_cm'))) {
                            $component->state(Height::toFeet((float) $get('height_cm'))[0]);
                        }
                    })
                    ->afterStateUpdated($sync),
                TextInput::make('height_inches')
                    ->hiddenLabel()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(11.9)
                    ->suffix('in')
                    ->dehydrated(false)
                    ->live(onBlur: true)
                    ->afterStateHydrated(function (TextInput $component, Get $get) {
                        if (filled($get('height_cm'))) {
                            $component->state(Height::toFeet((float) $get('height_cm'))[1]);
                        }
                    })
                    ->afterStateUpdated($sync),
            ])
                ->label($label)
                ->columns(2)
                ->helperText($helperText),
        ])->columnSpan(2);
    }
}
