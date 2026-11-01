<?php

namespace App\Filament\Member\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/** Member app sign-in with a mobile number or email, plus a link to sign in with a one-time code. */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Mobile number or email')
            ->placeholder('e.g. 98765 43210')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['login']);

        return str_contains($login, '@')
            ? ['email' => strtolower($login), 'password' => $data['password']]
            : ['phone_e164' => User::normalizePhone($login) ?? '-', 'password' => $data['password']];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => 'Wrong mobile number, email or password. You can also sign in with a one-time code.',
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('No password? '.$this->codeLoginAction->toHtml());
    }

    public function codeLoginAction(): Action
    {
        return Action::make('codeLogin')
            ->link()
            ->label('Sign in with a one-time code')
            ->url(route('filament.member.auth.login-code'));
    }
}
