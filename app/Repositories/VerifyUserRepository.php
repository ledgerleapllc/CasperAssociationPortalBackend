<?php

namespace App\Repositories;

use App\Models\VerifyUser;
use App\Repositories\Base\BaseRepository;

class VerifyUserRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return VerifyUser::class;
    }
}
