<?php

namespace App\Http\Controllers;

use App\Enums\StaffRole;
use App\Models\GymDataExport;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloads a prepared gym data ZIP: signed link, and only for the person who asked, the owner or platform support. */
class DataExportController extends Controller
{
    public function __invoke(Request $request, GymDataExport $export): StreamedResponse
    {
        $user = $request->user();
        $allowed = $export->user_id === $user->id
            || AdminAccess::can('gyms.export', $user)
            || $export->gym->users()->whereKey($user->id)->wherePivot('role', StaffRole::Owner->value)->exists();

        abort_unless($allowed, 403);
        abort_unless($export->isReady() && Storage::disk('local')->exists($export->path), 410, 'This download has expired. Ask for a new one.');

        $export->update(['downloaded_at' => now()]);

        return Storage::disk('local')->download($export->path, $export->gym->slug.'-data-'.$export->created_at->format('Y-m-d').'.zip');
    }
}
