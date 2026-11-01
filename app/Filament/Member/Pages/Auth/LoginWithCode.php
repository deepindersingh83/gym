<?php

namespace App\Filament\Member\Pages\Auth;

use App\Models\User;
use App\Services\LoginCodes;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/** Member sign-in with a 6-digit code sent to their mobile by WhatsApp or SMS. */
class LoginWithCode extends SimplePage
{
    protected string $view = 'filament.member.auth.login-with-code';

    public ?array $data = [];

    public bool $codeSent = false;

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());
        }

        if (! LoginCodes::isOffered()) {
            $this->redirect(Filament::getLoginUrl());
        }

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Sign in with a code';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Sign in with a code';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->codeSent
            ? 'If '.$this->data['phone'].' is registered with your gym, a 6-digit code is on its way by WhatsApp or SMS.'
            : 'Enter the mobile number your gym has for you.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->tel()
                    ->required()
                    ->autocomplete('tel')
                    ->disabled(fn () => $this->codeSent)
                    ->dehydrated(),
                TextInput::make('code')
                    ->label('6-digit code')
                    ->visible(fn () => $this->codeSent)
                    ->required(fn () => $this->codeSent)
                    ->length(6)
                    ->numeric()
                    ->autocomplete('one-time-code')
                    ->extraInputAttributes(['inputmode' => 'numeric']),
            ]);
    }

    public function sendCode(): void
    {
        $phone = $this->form->getState()['phone'];

        if (! User::normalizePhone($phone)) {
            throw ValidationException::withMessages(['data.phone' => 'Enter a valid mobile number.']);
        }

        if (! app(LoginCodes::class)->send($phone, request()->ip())) {
            throw ValidationException::withMessages(['data.phone' => 'Too many codes requested. Please wait 10 minutes and try again.']);
        }

        $this->codeSent = true;
    }

    public function verify()
    {
        $data = $this->form->getState();
        $user = app(LoginCodes::class)->verify($data['phone'], (string) $data['code']);

        if (! $user || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            throw ValidationException::withMessages(['data.code' => 'That code is wrong or has expired. Check it, or request a new one.']);
        }

        Filament::auth()->login($user, remember: true);
        session()->regenerate();
        // Lets them set a password without knowing the old one (see EditProfile).
        session([EditProfile::SESSION_SIGNED_IN_WITH_CODE => true]);

        return redirect()->intended(Filament::getUrl());
    }

    public function startOver(): void
    {
        $this->codeSent = false;
        $this->data['code'] = null;
    }

    public function hasLogo(): bool
    {
        return true;
    }
}
