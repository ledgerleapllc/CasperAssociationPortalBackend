<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;
use App\Repositories\DiscussionRepository;
use App\Repositories\DiscussionCommentRepository;
use App\Repositories\DiscussionPinRepository;
use App\Repositories\DiscussionVoteRepository;
use Illuminate\Support\Facades\Validator;

class DiscussionController extends Controller
{
    private $userRepo;
    private $discussionRepo;
    private $discussionPinRep;
    private $discussionVoteRepo;
    private $discussionCommentRepo;
    

    public function __construct(
        UserRepository $userRepo,
        DiscussionRepository $discussionRepo,
        DiscussionPinRepository $discussionPinRepo,
        DiscussionVoteRepository $discussionVoteRepo,
        DiscussionCommentRepository $discussionCommentRepo
    ) {
        $this->userRepo = $userRepo;
        $this->discussionRepo = $discussionRepo;
        $this->discussionPinRepo = $discussionPinRepo;
        $this->discussionVoteRepo = $discussionVoteRepo;
        $this->discussionCommentRepo = $discussionCommentRepo;
    }

    public function getDiscussions() {
        $data = array();
        $user = auth()->user()->load(['pinnedDiscussionsList', 'myDiscussionsList']);
        $data['discussions'] = $this->discussionRepo->getAll();
        $data['pinned_discussions'] = $user->pinnedDiscussionsList;
        $data['my_discussions'] = $user->myDiscussionsList;

        return $this->successResponse($data);
    }

    public function getDiscussion(Request $request, $id) {
        $data = array();
        $discussion = $this->discussionRepo->find($id);
        $discussion->load('commentsList');
        $data['discussion'] = $discussion;
        
        return $this->successResponse($data);
    }

    public function postDiscussion(Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = auth()->user();
        $discussion = $this->discussionRepo->create([
            "title" => $request->title,
            "description" => $request->description,
            "user_id" => $user->id,
        ]);

        return $this->successResponse($discussion);
    }

    public function postComment(Request $request, $id) {
        $data = array();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'comment_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        
        $model_data = [
            "user_id" => $user->id,
            "discussion_id" => $id,
            "description" => $request->description
        ];
        if ($request->comment_id == -1) {
            $data['comment'] = $this->discussionCommentRepo->create($model_data);
        } else {
            $data['comment'] = $this->discussionCommentRepo->update($request->comment_id, $model_data);
        }

        return $this->successResponse($data);
    }
}
