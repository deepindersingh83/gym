<?php

namespace App\Filament\Gym\Resources\Broadcasts;

use App\Enums\MessageChannel;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Broadcasts\Pages\ManageBroadcasts;
use App\Models\Broadcast;
use App\Services\Credits;
use App\Services\Messaging\Messenger;
use App\Support\GymOptions;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** One-off announcements: holiday closures, new batches, offers. */
class BroadcastResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Broadcast::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 1;

    public const AUDIENCES = ['active' => 'Members with an active membership', 'expired' => 'Members whose membership has expired', 'all' => 'All members'];

    protected static function accessArea(): string
    {
        return 'messaging';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(120)->columnSpanFull()->helperText('Also the email subject.'),
            Textarea::make('body')->label('Message')->rows(5)->required()->maxLength(1000)->columnSpanFull()
                ->helperText('You can use {name} and {gym}.'),
            Radio::make('audience')->options(self::AUDIENCES)->default('active')->required(),
            CheckboxList::make('channels')->options(MessageChannel::class)->default(['whatsapp'])->required(),
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())->placeholder('All branches')
                ->default(fn () => Tenancy::branchId()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->description(fn (Broadcast $record) => str($record->body)->limit(60)),
                TextColumn::make('audience')->formatStateUsing(fn ($state) => self::AUDIENCES[$state] ?? $state)->wrap(),
                TextColumn::make('channels')->badge()->color('gray'),
                TextColumn::make('sent_at')->dateTime('d M Y h:i A')->placeholder('Draft'),
                TextColumn::make('recipients_count')->label('Sent to'),
            ])
            ->recordActions([
                Action::make('send')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->hidden(fn (Broadcast $record) => $record->sent_at !== null)
                    ->requiresConfirmation()
                    ->modalDescription(function (Broadcast $record) {
                        $people = app(Messenger::class)->broadcastAudience($record)->count();
                        $credits = app(Credits::class);
                        $gym = Tenancy::gym();
                        $cost = $people * collect($record->channels)->sum(fn ($c) => $credits->costFor($gym, MessageChannel::from($c)));

                        return "Send now to {$people} members?".($cost ? ' This uses up to '.number_format($cost).' message credits (you have '.number_format($gym->fresh()->message_credits).').' : '');
                    })
                    ->action(function (Broadcast $record): void {
                        $sent = app(Messenger::class)->sendBroadcast($record);
                        Notification::make()->title("Sent to {$sent} members")->success()->send();
                    }),
                EditAction::make()->hidden(fn (Broadcast $record) => $record->sent_at !== null),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBroadcasts::route('/')];
    }
}
