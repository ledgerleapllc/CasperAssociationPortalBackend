<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\UserRepository;
use App\Repositories\DiscussionRepository;
use App\Repositories\DiscussionCommentRepository;
use App\Repositories\DiscussionPinRepository;
use App\Repositories\DiscussionVoteRepository;
use App\Repositories\DiscussionRemoveNewRepository;
use Illuminate\Support\Facades\Validator;
use App\Models\Discussion;
use App\Models\DiscussionPin;
use App\Models\DiscussionRemoveNew;
use Carbon\Carbon;

class DiscussionController extends Controller
{
    private $userRepo;
    private $discussionRepo;
    private $discussionPinRep;
    private $discussionVoteRepo;
    private $discussionCommentRepo;
    private $discussionRemoveNewRepo;
    

    public function __construct(
        UserRepository $userRepo,
        DiscussionRepository $discussionRepo,
        DiscussionPinRepository $discussionPinRepo,
        DiscussionVoteRepository $discussionVoteRepo,
        DiscussionCommentRepository $discussionCommentRepo,
        DiscussionRemoveNewRepository $discussionRemoveNewRepo
    ) {
        $this->userRepo = $userRepo;
        $this->discussionRepo = $discussionRepo;
        $this->discussionPinRepo = $discussionPinRepo;
        $this->discussionVoteRepo = $discussionVoteRepo;
        $this->discussionCommentRepo = $discussionCommentRepo;
        $this->discussionRemoveNewRepo = $discussionRemoveNewRepo;
    }

    public function getTrending() {
        $data = array();
        $user = auth()->user();
        $trendings = Discussion::where('likes', '!=', 0)->take(9)->orderBy('likes', 'desc')->get();
        $remains = 9 - count($trendings);
        if ($remains > 0) {
            $trending_ids = $trendings->pluck('id');
            $removed_ids = DiscussionRemoveNew::where(['user_id' => $user->id])->pluck('discussion_id');
            $news = Discussion::whereNotIn('id', $trending_ids)
                            ->whereNotIn('id', $removed_ids)
                            ->take($remains)->orderBy('id', 'desc')->get();
            $trendings = array_merge($trendings->toArray(), $news->toArray());
        }
        $data['trendings'] = $trendings;

        return $this->successResponse($data);        
    }

    public function getDiscussions() {
        $data = array();
        $limit = $request->limit ?? 15;
        $user = auth()->user()->load(['pinnedDiscussionsList', 'myDiscussionsList']);
        $data = Discussion::where([])->orderBy('created_at', 'DESC')->paginate($limit);
        
        return $this->successResponse($data);
    }

    public function getPinnedDiscussions() {
        $data = array();
        $user = auth()->user()->load(['pinnedDiscussionsList']);
        $data['pinned_discussions'] = $user->pinnedDiscussionsList->pluck('discussion');

        $removedNews = DiscussionRemoveNew::where(['user_id' => $user->id])->pluck('discussion_id');
        $data['new_discussions'] = Discussion::whereNotIn('id', $removedNews)
                ->whereDate('created_at', '>',  Carbon::now()->subDays(3))
                ->get();
        
        return $this->successResponse($data);
    }

    public function getMyDiscussions() {
        $data = array();
        $user = auth()->user()->load(['myDiscussionsList']);
        $data = $user->myDiscussionsList;
        
        return $this->successResponse($data);
    }

    public function getDiscussion(Request $request, $id) {
        $data = array();
        $discussion = $this->discussionRepo->find($id);
        $discussion->load('commentsList');
        $discussion->read = $discussion->read + 1;
        $data['discussion'] = $discussion;
        $discussion->save();

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

    public function createComment(Request $request, $id) {
        $data = array();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'description' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        
        $model_data = [
            "user_id" => $user->id,
            "discussion_id" => $id,
            "description" => $request->description
        ];
        
        $data['comment'] = $this->discussionCommentRepo->create($model_data);
        $discussion = $this->discussionRepo->find($id);
        $discussion->comments = $discussion->comments + 1;
        $discussion->save();
        
        $data['comment']['user'] = $user;

        return $this->successResponse($data);
    }

    public function updateComment(Request $request, $id) {
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
        
        $data['comment'] = $this->discussionCommentRepo->update($request->comment_id, $model_data);
        $data['comment']['user'] = $user;

        return $this->successResponse($data);
    }

    public function setVote(Request $request, $id) {
        $data = array();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'is_like' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $discussion = $this->discussionRepo->find($id);
        if ($discussion == null)
            return $this->errorResponse('Invalid discussion id', Response::HTTP_BAD_REQUEST);
        $discussion->load('commentsList');
        $is_like = $request->is_like;
        $vote = $this->discussionVoteRepo->first(['discussion_id' => $id, 'user_id' => $user->id]);
        if ($discussion->user_id != $user->id)
        if ($vote == null) {
            $this->discussionVoteRepo->create([
                'discussion_id' => $id,
                'user_id' => $user -> id,
                "is_like" => $is_like
            ]);

            if ($is_like) 
                $discussion->likes = $discussion->likes  + 1;
            else $discussion->dislikes = $discussion->dislikes  + 1;
            $xx = true;
            $discussion->save();
        } else {
            if ($vote->is_like != $is_like) {
                $this->discussionVoteRepo->update($vote->id, [
                    'is_like' => $is_like
                ]);
                if ($is_like) {
                    $discussion->dislikes = $discussion->dislikes - 1;
                    $discussion->likes = $discussion->likes + 1;
                } else {
                    $discussion->dislikes = $discussion->dislikes + 1;
                    $discussion->likes = $discussion->likes - 1;
                }                    
            }
            $xx = false;
            $discussion->save();
        }       

        return $this->successResponse(["discussion" => $discussion, "xx" => $xx]);    
    }

    public function setPin(Request $request, $id) {
        $data = array();
        $user = auth()->user();
        $pinned = $this->discussionPinRepo->first(['discussion_id' => $id, 'user_id' => $user->id]);
        if ($pinned == null) {
            $this->discussionPinRepo->create(['discussion_id' => $id, 'user_id' => $user->id]);
        } else {
            $this->discussionPinRepo->deleteConditions(['discussion_id' => $id, 'user_id' => $user->id]);
        }
        return $this->metaSuccess();
    }

    public function removeNewMark(Request $request, $id) {
        $user = auth()->user();
        $this->discussionRemoveNewRepo->deleteConditions([['created_at', '=<',  Carbon::now()->subDays(3)]]);
        $this->discussionRemoveNewRepo->create(['discussion_id' => $id, 'user_id' => $user->id]);        

        return $this->metaSuccess();
    }
}
