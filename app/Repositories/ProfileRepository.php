<?php

namespace App\Repositories;

use App\Models\Profile;
use App\Repositories\Base\BaseRepository;

class ProfileRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return Profile::class;
    }
}
