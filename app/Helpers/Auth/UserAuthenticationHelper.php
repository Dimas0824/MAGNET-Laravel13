<?php

namespace App\Helpers\Auth;

use App\Models\UserBase;
use Illuminate\Support\Facades\Auth;

class UserAuthenticationHelper
{
    public static function getUserRole()
    {
        foreach (['admin', 'dosen', 'mahasiswa'] as $role) {
            if (Auth::guard($role)->check()) {

                /**
                 * @var UserBase $user
                 */
                $user = Auth::guard($role)->user();

                return $user->getRoleName();
            }
        }

        return 'guest';
    }
}
