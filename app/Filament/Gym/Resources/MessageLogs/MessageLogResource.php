<?php

namespace App\Filament\Gym\Resources\MessageLogs;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\MessageLogs\Pages\ListMessageLogs;
use App\Models\MessageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MessageLogResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = MessageLog::class;

    protected static ?string $navigationLabel = 'Sent messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 3;

    protected static function accessArea(): string
    {
        return 'messaging';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime('d M Y h:i A')->sortable(),
                TextColumn::make('member.name')->searchable()->placeholder('—'),
                TextColumn::make('event')->badge(),
                TextColumn::make('channel')->badge()->color('gray'),
                TextColumn::make('recipient'),
                TextColumn::make('body')->limit(60)->wrap()->toggleable(),
                TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'sent' => 'success', 'failed' => 'danger', default => 'gray'
                    })
                    ->tooltip(fn (MessageLog $record) => $record->error),
            ])
            ->filters([
                SelectFilter::make('event')->options(MessageEvent::class),
                SelectFilter::make('channel')->options(MessageChannel::class),
                SelectFilter::make('status')->options(['sent' => 'Sent', 'failed' => 'Failed', 'logged' => 'Logged only (no provider set up)']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMessageLogs::route('/')];
    }
}
