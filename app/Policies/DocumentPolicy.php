<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function download(User $user, Document $document): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('user') && $user->id === $document->user_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('user') && $user->id === $document->user_id);
    }
}
