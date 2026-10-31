<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Gyms\GymResource;
use App\Filament\Admin\Widgets\GymHealthStats;
use App\Models\Gym;
use App\Services\GymHealth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Which gyms use the software, which have gone quiet, and which may cancel. */
class Usage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $title = 'Gym usage';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.gym.pages.table-page';

    protected function getHeaderWidgets(): array
    {
        return [GymHealthStats::class];
    }

    public static function getNavigationBadge(): ?string
    {
        return count(app(GymHealth::class)->idsWithStatus('at_risk')) ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Gyms at risk of cancelling';
    }

    public function table(Table $table): Table
    {
        $health = app(GymHealth::class);
        $row = fn (Gym $gym) => $health->for($gym);

        return $table
            ->query(Gym::query()->with('saasPlan'))
            ->defaultSort('last_activity_at')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (Gym $gym) => $gym->users->firstWhere('pivot.role.value', 'owner')?->email ?? $gym->email),
                TextColumn::make('health')->label('Health')->badge()
                    ->state(fn (Gym $gym) => GymHealth::LABELS[$row($gym)['status']])
                    ->color(fn (Gym $gym) => GymHealth::COLORS[$row($gym)['status']])
                    ->description(fn (Gym $gym) => implode(' · ', $row($gym)['reasons']) ?: 'Using it regularly')
                    ->wrap(),
                TextColumn::make('last_activity_at')->label('Last staff login')->since()->sortable()->placeholder('Never'),
                TextColumn::make('members')->label('Members')->state(fn (Gym $gym) => $row($gym)['metrics']['members']),
                TextColumn::make('check_ins')->label('Check-ins (30 days)')
                    ->state(fn (Gym $gym) => $row($gym)['metrics']['check_ins_30d'])
                    ->description(function (Gym $gym) use ($row) {
                        $m = $row($gym)['metrics'];

                        if ($m['check_ins_prev_30d'] === 0) {
                            return null;
                        }

                        $change = round(100 * ($m['check_ins_30d'] - $m['check_ins_prev_30d']) / $m['check_ins_prev_30d']);

                        return ($change >= 0 ? '▲ ' : '▼ ').abs($change).'% vs previous 30 days';
                    }),
                TextColumn::make('payments')->label('Payments (30 days)')->toggleable()->state(fn (Gym $gym) => $row($gym)['metrics']['payments_30d']),
                TextColumn::make('saasPlan.name')->label('Plan')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Account')->formatStateUsing(fn ($state) => ucfirst($state))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('users'))
            ->filters([
                SelectFilter::make('health')
                    ->options(GymHealth::LABELS)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereKey($health->idsWithStatus($data['value']))
                        : $query),
            ])
            ->recordActions([
                Action::make('open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Gym $gym) => Filament::getPanel('gym')->getUrl($gym), shouldOpenInNewTab: true),
                Action::make('edit')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                    ->url(fn (Gym $gym) => GymResource::getUrl('edit', ['record' => $gym])),
            ]);
    }
}
