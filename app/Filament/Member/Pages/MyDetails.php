<?php

namespace App\Filament\Member\Pages;

use App\Filament\Forms\Components\CameraPhoto;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Filament\Gym\Support\HeightField;
use App\Models\Member;
use App\Services\Compliance\MemberData;
use App\Support\CurrentMember;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

/** The member's own details: view everything, update what is theirs to change. */
class MyDetails extends Page
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadMyData')
                ->label('Download my data')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    $member = CurrentMember::get();
                    $json = json_encode(app(MemberData::class)->export($member), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    return response()->streamDownload(fn () => print ($json), 'my-data.json', ['Content-Type' => 'application/json']);
                }),
            Action::make('requestDeletion')
                ->label('Ask to delete my account')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('We will ask the gym to erase your personal details. They keep anonymous payment records that tax law requires.')
                ->action(function () {
                    $member = CurrentMember::get();
                    $gym = $member->gym;
                    $to = $gym->users()->wherePivot('role', 'owner')->first()?->email ?? $gym->email;

                    activity()->performedOn($member)->causedBy(auth()->user())->tap(fn ($a) => $a->gym_id = $gym->id)->log('Member asked for their data to be erased');

                    if ($to) {
                        rescue(fn () => Mail::raw(
                            "{$member->name} ({$member->member_code}) asked for their personal data to be erased.\n\nOpen their profile in ".config('app.name').' and use "Erase personal data".',
                            fn ($m) => $m->to($to)->subject("Data erasure request: {$member->name}")
                        ), report: true);
                    }

                    Notification::make()->title('Request sent to '.$gym->name)->body('The gym will erase your details. Contact them if you have questions.')->success()->send();
                }),
        ];
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $title = 'My details';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.member.my-details';

    /** Fields a member may change themselves; the rest only the gym can edit. */
    public const EDITABLE = [
        'photo_path', 'date_of_birth', 'anniversary_date', 'address', 'occupation', 'blood_group',
        'emergency_contact_name', 'emergency_contact_phone', 'height_cm', 'goal', 'target_weight_kg',
        'health_questionnaire', 'medical_conditions',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->member()->only(self::EDITABLE));
    }

    protected function member(): Member
    {
        return CurrentMember::get();
    }

    public function form(Schema $schema): Schema
    {
        $member = $this->member();

        return $schema
            ->statePath('data')
            ->model($member)
            ->components([
                Section::make('Your membership details')
                    ->description('To change these, please ask the front desk.')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')->state($member->name),
                        TextEntry::make('member_code')->label('Member ID')->state($member->member_code),
                        TextEntry::make('phone')->label('Mobile')->state($member->phone),
                        TextEntry::make('email')->state($member->email)->placeholder('—'),
                        TextEntry::make('branch')->state($member->branch?->name)->placeholder('—'),
                        TextEntry::make('trainer')->state($member->trainer?->name)->placeholder('—'),
                        TextEntry::make('joined_on')->label('Member since')->state($member->joined_on?->format('d M Y')),
                        TextEntry::make('weight')->label('Weight / BMI')
                            ->state($member->weight_kg ? (float) $member->weight_kg.' kg'.($member->bmi ? ' · BMI '.$member->bmi : '') : null)
                            ->placeholder('Recorded by your trainer'),
                    ]),
                Section::make('Photo')->schema([
                    CameraPhoto::make('photo_path')->hiddenLabel()->directory('members/'.$member->gym_id),
                ]),
                Section::make('About you')->columns(3)->schema([
                    DatePicker::make('date_of_birth')->maxDate(today()),
                    DatePicker::make('anniversary_date'),
                    Select::make('blood_group')->options(array_combine($g = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], $g)),
                    TextInput::make('occupation')->maxLength(120),
                    Textarea::make('address')->rows(2)->columnSpan(2),
                ]),
                Section::make('Goals')->columns(4)->schema([
                    HeightField::make(),
                    Select::make('goal')->options(MemberResource::GOALS),
                    TextInput::make('target_weight_kg')->label('Target weight (kg)')->numeric()->minValue(20)->maxValue(400),
                ]),
                Section::make('Health and emergency contact')
                    ->description('Helps your trainer keep you safe. Only gym staff can see this.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('emergency_contact_name')->maxLength(120),
                        TextInput::make('emergency_contact_phone')->tel()->maxLength(20),
                        CheckboxList::make('health_questionnaire')
                            ->label('Tick any that apply')
                            ->options(MemberResource::HEALTH_QUESTIONS)
                            ->columns(2)
                            ->columnSpanFull(),
                        Textarea::make('medical_conditions')->label('Injuries or anything else we should know')->rows(2)->maxLength(1000)->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = collect($this->form->getState())->only(self::EDITABLE)->all();

        $this->member()->update($data);
        CurrentMember::set(null);

        Notification::make()->title('Your details are saved')->success()->send();
    }
}
