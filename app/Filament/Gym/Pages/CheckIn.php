<?php

namespace App\Filament\Gym\Pages;

use App\Models\Attendance;
use App\Models\Member;
use App\Support\Access;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Front-desk check-in. A USB/Bluetooth QR scanner types the member's QR code
 * into the box and presses Enter; staff can also type a member ID or phone.
 */
class CheckIn extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Check-in desk';

    protected string $view = 'filament.gym.pages.check-in';

    public string $code = '';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return Access::can('attendance.create');
    }

    public function checkIn(): void
    {
        $code = trim($this->code);
        $method = str_starts_with($code, 'GYM:') ? 'qr' : 'manual';
        $this->code = '';

        if ($code === '') {
            return;
        }

        // QR codes look like "GYM:{gym id}:{member code}".
        if (preg_match('/^GYM:(\d+):(.+)$/', $code, $m)) {
            if ((int) $m[1] !== Tenancy::gymId()) {
                $this->result = ['ok' => false, 'message' => 'This QR code belongs to a different gym.'];

                return;
            }
            $code = $m[2];
        }

        $member = Member::query()
            ->where(fn ($q) => $q->where('member_code', $code)->orWhere('phone', $code))
            ->with('currentSubscription.plan')
            ->first();

        if (! $member) {
            $this->result = ['ok' => false, 'message' => "No member found for \"{$code}\"."];

            return;
        }

        $subscription = $member->currentSubscription;
        $valid = $subscription && $subscription->end_date->gte(today()) && $subscription->start_date->lte(today());

        if ($valid) {
            Attendance::create([
                'member_id' => $member->id,
                'branch_id' => Tenancy::branchId() ?? $member->branch_id,
                'checked_in_at' => now(),
                'method' => $method,
            ]);
        }

        $this->result = [
            'ok' => $valid,
            'name' => $member->name,
            'code' => $member->member_code,
            'photo' => $member->photo_url,
            'plan' => $subscription?->plan?->name,
            'end_date' => $subscription?->end_date?->format('d M Y'),
            'days_left' => $subscription?->days_left,
            'due' => $member->balance_due,
            'message' => $valid ? 'Checked in' : 'No active membership — please renew at the desk',
        ];
    }
}
