<?php

namespace App\Repositories;

use App\Models\DiscussionVote;
use App\Repositories\Base\BaseRepository;

class DiscussionVoteRepository extends BaseRepository
{
    /**
     * Get model.
     *
     * @return string
     */
    public function getModel()
    {
        return DiscussionVote::class;
    }
}