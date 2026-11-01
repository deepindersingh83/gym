<?php

namespace App\Filament\Admin\Resources\AdminTeam\Pages;

use App\Filament\Admin\Resources\AdminTeam\AdminTeamResource;
use App\Models\User;
use App\Support\AdminLog;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ManageAdminTeam extends ManageRecords
{
    protected static string $resource = AdminTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add team member')
                ->modalHeading('Add team member')
                ->using(function (array $data): User {
                    $user = User::query()->firstOrNew(['email' => strtolower($data['email'])]);
                    $isNew = ! $user->exists;
                    $user->name = $user->name ?: $data['name'];
                    $user->password = $isNew ? Str::password(32) : $user->password;
                    $user->admin_role = $data['admin_role'];
                    $user->save();

                    AdminLog::record('Added admin team member', $user, ['email' => $user->email, 'role' => $user->admin_role->value]);

                    // They choose their own password, then set up the authenticator app at first sign-in.
                    $panel = Filament::getPanel('admin');
                    Password::broker($panel->getAuthPasswordBroker())->sendResetLink(['email' => $user->email], function (User $user, string $token) use ($panel) {
                        $notification = new ResetPassword($token);
                        $notification->url = $panel->getResetPasswordUrl($token, $user);
                        $user->notify($notification);
                    });

                    Notification::make()->title("Invitation sent to {$user->email}")
                        ->body($isNew ? 'They set a password from the email, then the authenticator app at first sign-in.' : 'This person already had an account; they can now sign in to the admin panel.')
                        ->success()->send();

                    return $user;
                })
                ->successNotification(null),
        ];
    }
}
