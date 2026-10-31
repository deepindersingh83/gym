<?php

namespace App\Filament\Admin\Resources\Gyms\Pages;

use App\Filament\Admin\Resources\Gyms\GymResource;
use App\Models\User;
use App\Services\GymProvisioner;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Super admin adds a gym together with its owner's login. */
class CreateGym extends CreateRecord
{
    protected static string $resource = GymResource::class;

    protected static ?string $title = 'Add gym';

    protected function handleRecordCreation(array $data): Model
    {
        $ownerData = $data['owner'];
        $starterPlans = $data['starter_plans'] ?? [];
        unset($data['owner'], $data['starter_plans']);

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        $email = strtolower($ownerData['email']);
        $owner = User::query()->where('email', $email)->first();
        $isNewOwner = ! $owner;
        $owner ??= User::create([
            'name' => $ownerData['name'],
            'email' => $email,
            'phone' => $ownerData['phone'] ?? null,
            'password' => filled($ownerData['password'] ?? null) ? $ownerData['password'] : Str::password(32),
        ]);

        $gym = app(GymProvisioner::class)->provision($data, $owner, $starterPlans);

        if ($isNewOwner && blank($ownerData['password'] ?? null)) {
            $this->sendSetPasswordLink($owner);
        } else {
            Notification::make()
                ->title($isNewOwner ? 'Owner login created' : 'Existing account added as owner')
                ->body("{$owner->email} can sign in at ".Filament::getPanel('gym')->getLoginUrl())
                ->success()
                ->persistent()
                ->send();
        }

        return $gym;
    }

    protected function sendSetPasswordLink(User $owner): void
    {
        $panel = Filament::getPanel('gym');
        $status = Password::broker($panel->getAuthPasswordBroker())->sendResetLink(
            ['email' => $owner->email],
            function (User $user, string $token) use ($panel) {
                $notification = new ResetPassword($token);
                $notification->url = $panel->getResetPasswordUrl($token, $user);
                $user->notify($notification);
            },
        );

        Notification::make()
            ->title($status === Password::RESET_LINK_SENT ? 'Set-password email sent to '.$owner->email : 'Gym created, but the email could not be sent')
            ->body($status === Password::RESET_LINK_SENT
                ? 'It is delivered by the queue worker. If it does not arrive, set a password by editing the owner later or use "Forgot password" on the login page.'
                : __($status))
            ->status($status === Password::RESET_LINK_SENT ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
