<?php

namespace App\Filament\Admin\Resources\Partners;

use App\Filament\Admin\Resources\Partners\Pages\CreatePartner;
use App\Filament\Admin\Resources\Partners\Pages\EditPartner;
use App\Filament\Admin\Resources\Partners\Pages\ListPartners;
use App\Models\Partner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Resellers and affiliates who refer gyms for a commission. */
class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Partner')->columns(3)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('company')->maxLength(120),
                TextInput::make('email')->email()->required()->maxLength(120)
                    ->helperText('Signs in to the partner portal at /partner with this email.'),
                TextInput::make('phone')->tel()->maxLength(20),
                TextInput::make('code')->label('Referral code')->alphaNum()->maxLength(20)->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : null)
                    ->helperText('Leave empty to create one.'),
                TextInput::make('commission_rate')->label('Commission')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(20)->required()
                    ->helperText('Of every software payment by gyms they refer.'),
                Textarea::make('payout_details')->label('Payout details (bank / UPI)')->rows(2)->columnSpan(2),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false),
            ]),
            Section::make('Portal login')
                ->visibleOn('create')
                ->description('Leave the password empty to email the partner a link to set one.')
                ->schema([
                    TextInput::make('password')->password()->revealable()->minLength(8)->dehydrated(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('gyms')
                ->withSum(['commissions as pending_total' => fn ($q) => $q->where('status', 'pending')], 'amount')
                ->withSum(['commissions as paid_total' => fn ($q) => $q->where('status', 'paid')], 'amount'))
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Partner $record) => $record->company),
                TextColumn::make('email')->searchable(),
                TextColumn::make('code')->label('Code')->badge()->copyable()
                    ->copyableState(fn (Partner $record) => $record->referralUrl())->tooltip('Click to copy the referral link'),
                TextColumn::make('commission_rate')->label('Rate')->suffix('%'),
                TextColumn::make('gyms_count')->label('Gyms'),
                TextColumn::make('pending_total')->label('To pay')->money('INR')->placeholder('—')->color('warning'),
                TextColumn::make('paid_total')->label('Paid')->money('INR')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
            'create' => CreatePartner::route('/create'),
            'edit' => EditPartner::route('/{record}/edit'),
        ];
    }
}
