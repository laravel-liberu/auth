<?php

namespace LaravelLiberu\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use LaravelLiberu\Auth\Rules\DistinctPassword;
use LaravelLiberu\Users\Models\User;

class ValidatePassword extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email' => 'exists:users,email',
            'password' => [
                'nullable',
                'confirmed',
                Password::defaults(),
                $this->distinctPassword(),
            ],
        ];
    }

    protected function currentUser()
    {
        return $this->route('user')
            ?? User::whereEmail($this->get('email'))->first();
    }

    private function distinctPassword(): ?DistinctPassword
    {
        $user = $this->currentUser();

        return $user
            ? new DistinctPassword($this->currentUser())
            : null;
    }
}
