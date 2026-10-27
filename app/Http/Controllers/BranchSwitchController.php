<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Gym;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Lets an owner view one branch or all branches of their gym. */
class BranchSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gym_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $gym = Gym::query()->findOrFail($data['gym_id']);
        abort_unless($request->user()->canAccessTenant($gym), 403);

        Tenancy::setGym($gym);
        abort_unless(Tenancy::canSwitchBranch(), 403);

        $branchId = $data['branch_id'] ?? null;
        abort_if($branchId && ! Branch::query()->whereKey($branchId)->exists(), 404);

        Tenancy::selectBranch($branchId);

        return back();
    }
}
