<?php

namespace App\Filament\Gym\Pages\Tenancy;

use App\Enums\PlanType;
use App\Filament\Gym\Schemas\GymDetailsFields;
use App\Models\Gym;
use App\Services\GymProvisioner;
use Filament\Forms\Components\CheckboxList;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** Onboarding wizard a new gym owner goes through after signing up. */
class RegisterGym extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Set up your gym';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Gym')->description('Name and contact')->schema(GymDetailsFields::basics()),
                Step::make('Location')->description('Address and GST')->schema(GymDetailsFields::location()),
                Step::make('Hours')->description('When you are open')->schema(GymDetailsFields::openingHours()),
                Step::make('Branding')->description('Logo and colour')->schema([
                    ...GymDetailsFields::branding(),
                    CheckboxList::make('starter_plans')
                        ->label('Create these membership plans for me')
                        ->options([
                            PlanType::Monthly->value => 'Monthly',
                            PlanType::Quarterly->value => '3 months',
                            PlanType::HalfYearly->value => '6 months',
                            PlanType::Yearly->value => 'Yearly',
                        ])
                        ->default([PlanType::Monthly->value, PlanType::Quarterly->value, PlanType::Yearly->value])
                        ->helperText('You can change prices later under Plans.')
                        ->dehydrated(false),
                ]),
            ])->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit">Create my gym</x-filament::button>'))),
        ]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function handleRegistration(array $data): Model
    {
        return app(GymProvisioner::class)->provision(
            $data,
            auth()->user(),
            $this->form->getRawState()['starter_plans'] ?? [],
        );
    }
}
