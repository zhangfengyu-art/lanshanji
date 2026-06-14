<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrongAdminPassword implements Rule
{
    protected $minLength;

    public function __construct()
    {
        $this->minLength = (int) config('admin_security.password_min_length', 16);
    }

    public function passes($attribute, $value)
    {
        $password = (string) $value;
        if (strlen($password) < $this->minLength) {
            return false;
        }

        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        return true;
    }

    public function message()
    {
        return '密码至少 '.$this->minLength.' 位，且须包含大写字母、小写字母和数字。';
    }
}
