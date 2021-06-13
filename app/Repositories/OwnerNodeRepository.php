<?php

namespace App\Repositories;

use App\Models\OwnerNode;
use App\Repositories\Base\BaseRepository;

class OwnerNodeRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return OwnerNode::class;
    }
}
