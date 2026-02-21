<?php

namespace App\Policies;

use App\Models\User;

class CmsAccessPolicy
{
    /**
     * Determine if the user can access the CMS.
     * Only authenticated users from the 'users' table (admins) can access.
     */
    public function accessCms(?User $user): bool
    {
        // Only authenticated users (admins) can access CMS
        return $user !== null;
    }

    /**
     * Determine if the user can manage community playlists.
     */
    public function managePlaylists(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * Determine if the user can manage content (audio, video, book, topics).
     */
    public function manageContent(?User $user): bool
    {
        return $user !== null;
    }
}
