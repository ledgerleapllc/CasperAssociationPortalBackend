<?php

namespace App\Repositories;

use App\Models\DiscussionRemoveNew;
use App\Repositories\Base\BaseRepository;

class DiscussionRemoveNewRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return DiscussionRemoveNew::class;
    }
}