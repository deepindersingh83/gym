<?php

namespace App\Filament\Gym\Concerns;

use App\Support\Access;
use App\Support\Tenancy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Authorises a gym-panel resource against the role matrix in App\Support\Access.
 * Writes are blocked when the gym's SaaS trial or plan has lapsed, and creating
 * is blocked when the plan's member / branch / staff limit is reached.
 */
trait ChecksGymAccess
{
    abstract protected static function accessArea(): string;

    /** SaaS plan limit ("members", "branches" or "staff") that applies to creating records. */
    protected static function saasLimit(): ?string
    {
        return null;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $action = $action instanceof UnitEnum ? $action->name : $action;

        $ability = match ($action) {
            'viewAny', 'view' => 'view',
            'create', 'replicate' => 'create',
            'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny' => 'delete',
            default => 'edit',
        };

        if (! Access::can(static::accessArea().'.'.$ability)) {
            return Response::deny();
        }

        $gym = Tenancy::gym();

        if ($ability !== 'view' && $gym && ! $gym->hasActiveSaasSubscription()) {
            return Response::deny('Your trial or plan has ended. Renew it to make changes.');
        }

        if ($ability === 'create' && ($limit = static::saasLimit()) && $gym && ! $gym->withinLimit($limit)) {
            return Response::deny("Your plan's {$limit} limit has been reached. Upgrade to add more.");
        }

        return Response::allow();
    }
}
