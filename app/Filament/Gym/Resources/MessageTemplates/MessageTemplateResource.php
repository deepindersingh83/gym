<?php

namespace App\Filament\Gym\Resources\MessageTemplates;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\MessageTemplates\Pages\ManageMessageTemplates;
use App\Models\MessageTemplate;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class MessageTemplateResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = MessageTemplate::class;

    protected static ?string $navigationLabel = 'Message templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 2;

    public const PLACEHOLDERS = '{name}, {gym}, {plan}, {end_date}, {days_left}, {due}, {amount}, {receipt}, {currency}, {member_code}, {phone}, {review_link}, {visits}';

    protected static function accessArea(): string
    {
        return 'messaging';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('event')->options(collect(MessageEvent::cases())->reject(fn ($e) => $e === MessageEvent::Broadcast)->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()]))
                ->required()->live()
                ->afterStateUpdated(fn ($state, Set $set, Get $get) => $state && blank($get('body')) ? $set('body', MessageEvent::from($state)->defaultBody()) : null),
            Select::make('channel')->options(MessageChannel::class)->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('gym_id', Tenancy::gymId())->where('event', $get('event')))
                ->validationMessages(['unique' => 'A template for this event and channel already exists.']),
            TextInput::make('subject')->helperText('Email only.')->columnSpanFull(),
            Textarea::make('body')->rows(4)->required()->columnSpanFull()
                ->helperText('Placeholders: '.self::PLACEHOLDERS),
            TextInput::make('provider_template')
                ->label('Approved template name / flow ID')
                ->helperText('WhatsApp and Indian SMS (DLT) need a pre-approved template. Enter its name here; placeholders are sent as its variables in the order they appear.')
                ->columnSpanFull(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->badge(),
                TextColumn::make('channel')->badge()->color('gray'),
                TextColumn::make('body')->limit(70)->wrap(),
                ToggleColumn::make('is_active')->label('On'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateDescription('Without a template, the built-in default text is used.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMessageTemplates::route('/')];
    }
}
