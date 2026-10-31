<?php

namespace App\Filament\Admin\Resources\Partners\Pages;

use App\Filament\Admin\Resources\Partners\PartnerResource;
use App\Models\Partner;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Creates the partner and their portal login. */
class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $password = $this->form->getRawState()['password'] ?? null;
        $email = strtolower($data['email']);
        $user = User::query()->where('email', $email)->first();
        $isNew = ! $user;
        $user ??= User::create([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'password' => filled($password) ? $password : Str::password(32),
        ]);

        $partner = Partner::create([...$data, 'email' => $email, 'user_id' => $user->id]);

        if ($isNew && blank($password)) {
            $panel = Filament::getPanel('partner');
            Password::broker($panel->getAuthPasswordBroker())->sendResetLink(['email' => $email], function (User $user, string $token) use ($panel) {
                $notification = new ResetPassword($token);
                $notification->url = $panel->getResetPasswordUrl($token, $user);
                $user->notify($notification);
            });
        }

        Notification::make()
            ->title('Partner added')
            ->body('Referral link: '.$partner->referralUrl())
            ->success()
            ->persistent()
            ->send();

        return $partner;
    }
}
