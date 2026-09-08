<?php

namespace App\Services\NeoFeeder;

use Illuminate\Support\Facades\Crypt;

class NeoFeederCredentialVault
{
    public function encryptPassword(?string $password): ?string
    {
        if ($password === null || $password === '') {
            return null;
        }

        return Crypt::encryptString($password);
    }

    public function decryptPassword(?string $encryptedPassword): ?string
    {
        if ($encryptedPassword === null || $encryptedPassword === '') {
            return null;
        }

        return Crypt::decryptString($encryptedPassword);
    }
}
