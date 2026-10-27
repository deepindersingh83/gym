<?php

namespace App\Filament\Gym\Support;

use App\Enums\PaymentMethod;
use App\Models\Coupon;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\SubscriptionService;
use App\Support\GymOptions;
use App\Support\Money;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;

/** Form fields for selling or renewing a membership plan, with a live price summary. */
class MembershipFields
{
    /**
     * @param  bool  $withAdmissionFee  Whether the admission fee applies (first membership only).
     * @param  ?callable  $defaultStart  Returns the default start date.
     */
    public static function make(bool $withAdmissionFee = true, ?callable $defaultStart = null, bool $required = true): array
    {
        $refreshTotal = function (Get $get, Set $set) use ($withAdmissionFee): void {
            $quote = static::quote($get, $withAdmissionFee);
            $set('amount_paid', $quote ? $quote['total_amount'] : null);
        };

        return [
            Grid::make(3)->schema([
                Select::make('membership_plan_id')
                    ->label('Plan')
                    ->options(fn () => GymOptions::plans())
                    ->required($required)
                    ->live()
                    ->afterStateUpdated($refreshTotal),
                DatePicker::make('start_date')
                    ->default($defaultStart ?? today())
                    ->required($required),
                Select::make('coupon_id')
                    ->label('Coupon / offer')
                    ->options(fn () => GymOptions::usableCoupons())
                    ->live()
                    ->afterStateUpdated($refreshTotal),
            ]),
            Select::make('additional_member_ids')
                ->label('Other people on this plan')
                ->helperText('For couple and family plans.')
                ->multiple()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Member::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->limit(20)->pluck('name', 'id'))
                ->getOptionLabelsUsing(fn (array $values) => Member::query()->whereIn('id', $values)->pluck('name', 'id'))
                ->visible(fn (Get $get) => (static::plan($get)?->max_persons ?? 1) > 1),
            Text::make(fn (Get $get) => static::summary($get, $withAdmissionFee)),
            Grid::make(3)->schema([
                TextInput::make('amount_paid')
                    ->label('Amount received now')
                    ->numeric()
                    ->minValue(0)
                    ->prefix(Money::symbol())
                    ->helperText('Enter less than the total for a part payment.'),
                Select::make('payment_method')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash->value)
                    ->required(fn (Get $get) => (float) $get('amount_paid') > 0),
                TextInput::make('payment_reference')
                    ->label('UPI / transaction ref.')
                    ->maxLength(100),
            ]),
        ];
    }

    protected static function plan(Get $get): ?MembershipPlan
    {
        return $get('membership_plan_id') ? MembershipPlan::query()->find($get('membership_plan_id')) : null;
    }

    /** @return ?array<string, float> */
    protected static function quote(Get $get, bool $withAdmissionFee): ?array
    {
        $plan = static::plan($get);

        if (! $plan) {
            return null;
        }

        $coupon = $get('coupon_id') ? Coupon::query()->find($get('coupon_id')) : null;

        return app(SubscriptionService::class)->quote(Tenancy::gym(), $plan, $coupon, $withAdmissionFee);
    }

    protected static function summary(Get $get, bool $withAdmissionFee): string
    {
        $quote = static::quote($get, $withAdmissionFee);

        if (! $quote) {
            return 'Pick a plan to see the price.';
        }

        $parts = ['Plan '.Money::format($quote['plan_price'])];

        if ($quote['admission_fee'] > 0) {
            $parts[] = '+ admission '.Money::format($quote['admission_fee']);
        }

        if ($quote['discount_amount'] > 0) {
            $parts[] = '− discount '.Money::format($quote['discount_amount']);
        }

        if ($quote['gst_rate'] > 0) {
            $parts[] = '+ GST '.(float) $quote['gst_rate'].'% '.Money::format($quote['cgst_amount'] + $quote['sgst_amount']);
        }

        $end = static::plan($get)->endDateFrom(Carbon::parse($get('start_date') ?? today()));

        return implode(' ', $parts).' = Total '.Money::format($quote['total_amount']).' · Valid until '.$end->format('d M Y');
    }
}
