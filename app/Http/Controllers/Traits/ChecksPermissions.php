<?php

namespace App\Http\Controllers\Traits;

trait ChecksPermissions
{
    protected function checkOwnerPermission($user)
    {
        if ($user->role !== 'pemilik') {
            abort(403, 'Unauthorized');
        }
    }
}