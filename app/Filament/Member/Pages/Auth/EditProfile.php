<?php

namespace App\Filament\Member\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Member login details. Email is optional (many members only have a mobile),
 * and someone who just signed in with a one-time code can set a password
 * without knowing the old one; the code already proved the phone is theirs.
 */
class EditProfile extends BaseEditProfile
{
    public const SESSION_SIGNED_IN_WITH_CODE = 'member.signed_in_with_code';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            TextInput::make('phone')
                ->label('Mobile number (used to sign in)')
                ->disabled()
                ->dehydrated(false)
                ->helperText('To change it, ask the front desk.'),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email (optional)')
            ->email()
            ->nullable()
            ->maxLength(255)
            ->unique(ignoreRecord: true);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->visible(fn (Get $get): bool => ! session(self::SESSION_SIGNED_IN_WITH_CODE)
                && (filled($get('password')) || ($get('email') !== $this->getUser()->getAttributeValue('email'))));
    }
}
