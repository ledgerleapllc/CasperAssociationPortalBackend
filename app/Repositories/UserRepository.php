<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Base\BaseRepository;

class UserRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return User::class;
    }
}