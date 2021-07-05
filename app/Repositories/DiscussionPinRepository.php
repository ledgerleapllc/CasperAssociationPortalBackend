<?php

namespace App\Repositories;

use App\Models\DiscussionPin;
use App\Repositories\Base\BaseRepository;

class DiscussionPinRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return DiscussionPin::class;
    }
}