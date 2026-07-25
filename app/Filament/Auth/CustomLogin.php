<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin; // <-- المسار الصحيح في Filament v4
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomLogin extends BaseLogin
{
    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('phone_number')
                    ->label('رقم الهاتف')
                    ->placeholder('09XXXXXXXX')
                    ->tel()
                    ->required()
                    ->regex('/^09[0-9]{8}$/')
                    ->validationMessages([
                        'required' => 'يرجى إدخال حقل رقم الهاتف.',
                        'regex'    => 'رقم الهاتف يجب أن يبدأ بـ 09 ويتكون من 10 أرقام فقط.',
                    ])
                    ->autofocus()
                    ->extraInputAttributes(['dir' => 'ltr']),

                TextInput::make('password')
                    ->label('كلمة المرور')
                    ->password()
                    ->revealable()
                    ->required()
                    ->validationMessages([
                        'required' => 'يرجى إدخال كلمة المرور.',
                    ]),

                Checkbox::make('remember')
                    ->label('تذكرني'),
            ])
            ->statePath('data');
    }

    /**
     * ربط حقل رقم الهاتف ببيانات الاعتماد لتسجيل الدخول
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'phone_number' => $data['phone_number'],
            'password'     => $data['password'],
        ];
    }
}