<?php

namespace App\Repositories;

use App\Models\Discussion;
use App\Repositories\Base\BaseRepository;

class DiscussionRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return Discussion::class;
    }
}