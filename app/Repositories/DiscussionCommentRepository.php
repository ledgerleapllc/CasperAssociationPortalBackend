<?php

namespace App\Repositories;

use App\Models\DiscussionComment;
use App\Repositories\Base\BaseRepository;

class DiscussionCommentRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return DiscussionComment::class;
    }
}