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
use App\Models\DiscussionComment;
use App\Models\DiscussionPin;
use App\Models\DiscussionRemoveNew;
use Carbon\Carbon;
use Illuminate\Http\Response;
use App\Facades\Paginator;

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

    public function getTrending(Request $request)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $trendings = Discussion::where('likes', '!=', 0)->where('is_draft', 0)->take(9)->orderBy('likes', 'desc')->paginate($limit);
        $count = Discussion::where('likes', '!=', 0)->where('is_draft', 0)->orderBy('likes', 'desc')->count();
        if ($count >= 9) {
            return $this->successResponse($trendings);
        } else {
            $remains = 9 - $count;
            $trending_ids = $trendings->pluck('id');
            $removed_ids = DiscussionRemoveNew::where(['user_id' => $user->id])->pluck('discussion_id');
            $news = Discussion::whereNotIn('id', $trending_ids)
                ->whereNotIn('id', $removed_ids)
                ->where('is_draft', 0)
                ->take($remains)->orderBy('id', 'desc')->get();
            $trendingArray = $trendings->toArray() ;
            $trendingArray['data'] = array_merge($trendingArray['data'], $news->toArray());

            return $this->successResponse( [
                'data' => $trendingArray['data']
            ]);
        }
    }

    public function getDiscussions(Request $request)
    {
        $data = array();
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = Discussion::with(['user', 'user.profile'])->where('discussions.is_draft', 0)
            ->leftJoin('discussion_pins', function ($query) use ($user) {
                $query->on('discussion_pins.discussion_id', '=', 'discussions.id')
                    ->where('discussion_pins.user_id', $user->id);
            })
            ->leftJoin('discussion_votes', function ($query) use ($user) {
                $query->on('discussion_votes.discussion_id', '=', 'discussions.id')
                    ->where('discussion_votes.user_id', $user->id);;
            })
            ->select([
                'discussions.*',
                'discussion_pins.id as is_pin',
                'discussion_votes.id as is_vote',
                'discussion_votes.is_like as is_like',
            ])->orderBy('discussions.created_at', 'DESC')->paginate($limit);

        return $this->successResponse($data);
    }

    public function getPinnedDiscussions(Request $request)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = DiscussionPin::where('discussion_pins.user_id', $user->id)->with('user')
            ->join('discussions', 'discussions.id', '=', 'discussion_pins.discussion_id')
            ->leftJoin('discussion_votes', function ($query) use ($user) {
                $query->on('discussion_votes.discussion_id', '=', 'discussions.id')
                    ->where('discussion_votes.user_id', $user->id);;
            })
            ->select([
                'discussions.*',
                'discussion_votes.id as is_vote',
                'discussion_pins.discussion_id',
                'discussion_votes.is_like as is_like',
            ])->orderBy('discussion_pins.created_at', 'DESC')->paginate($limit);
        return $this->successResponse($data);
    }

    public function getMyDiscussions(Request $request)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = Discussion::with(['user', 'user.profile'])->where('discussions.is_draft', 0)
            ->where('discussions.user_id', $user->id)
            ->leftJoin('discussion_pins', function ($query) use ($user) {
                $query->on('discussion_pins.discussion_id', '=', 'discussions.id')
                    ->where('discussion_pins.user_id', $user->id);
            })
            ->leftJoin('discussion_votes', function ($query) use ($user) {
                $query->on('discussion_votes.discussion_id', '=', 'discussions.id')
                    ->where('discussion_votes.user_id', $user->id);;
            })
            ->select([
                'discussions.*',
                'discussion_pins.id as is_pin',
                'discussion_votes.id as is_vote',
                'discussion_votes.is_like as is_like',
            ])->orderBy('discussions.created_at', 'DESC')->paginate($limit);

        return $this->successResponse($data);
    }

    public function getDiscussion(Request $request, $id)
    {
        $user = auth()->user();
        $discussion = Discussion::with(['user', 'user.profile'])
            ->where('discussions.id', $id)
            ->leftJoin('discussion_pins', function ($query) use ($user) {
                $query->on('discussion_pins.discussion_id', '=', 'discussions.id')
                    ->where('discussion_pins.user_id', $user->id);
            })
            ->leftJoin('discussion_votes', function ($query) use ($user) {
                $query->on('discussion_votes.discussion_id', '=', 'discussions.id')
                    ->where('discussion_votes.user_id', $user->id);;
            })
            ->select([
                'discussions.*',
                'discussion_pins.id as is_pin',
                'discussion_votes.id as is_vote',
                'discussion_votes.is_like as is_like',
            ])->first();
        $discussion->read = $discussion->read + 1;
        $discussion->save();
        $discussion->total_pinned = DiscussionPin::where('discussion_id', $id)->count();
        return $this->successResponse($discussion);
    }

    public function postDiscussion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
            // 'is_draft' => 'required|in:0,1'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = auth()->user();
        $discussion = $this->discussionRepo->create([
            "title" => $request->title,
            "description" => $request->description,
            "user_id" => $user->id,
            "is_draft" => (int) $request->get('is_draft'),
        ]);

        return $this->successResponse($discussion);
    }

    public function publishDraftDiscussion($id)
    {
        $discussion = Discussion::where('id', $id)->where('is_draft', 1)->first();
        if($discussion) {
            $discussion->is_draft = 0;
            $discussion->save();
        }
        return $this->metaSuccess();
    }

    public function createComment(Request $request, $id)
    {
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

    public function updateComment(Request $request, $id)
    {
        $data = array();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'comment_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $comment = DiscussionComment::where('discussion_id', $request->comment_id)->where('user_id', $user->id)->first();
        if ($comment) {
            $comment->description = $request->description;
            $comment->save();
            return $this->successResponse($comment);
        }
        return $this->errorResponse('Invalid discussion id', Response::HTTP_BAD_REQUEST);
    }

    public function setVote(Request $request, $id)
    {
        $data = array();
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'is_like' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $discussion = $this->discussionRepo->find($id);
        if ($discussion == null) {
            return $this->errorResponse('Invalid discussion id', Response::HTTP_BAD_REQUEST);
        }
        $is_like = $request->is_like;
        $vote = $this->discussionVoteRepo->first(['discussion_id' => $id, 'user_id' => $user->id]);
        if ($discussion->user_id != $user->id) {
            if ($vote == null) {
                $vote = $this->discussionVoteRepo->create([
                    'discussion_id' => $id,
                    'user_id' => $user->id,
                    "is_like" => $is_like
                ]);
                if ($is_like) {
                    $discussion->likes = $discussion->likes  + 1;
                } else {
                    $discussion->dislikes = $discussion->dislikes  + 1;
                }
                $discussion->save();
            } else {
                if ($vote->is_like != $is_like) {
                    $vote = $this->discussionVoteRepo->update($vote->id, [
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
                $discussion->save();
            }
            return $this->successResponse([
                'discussion' => $discussion,
                'vote' => $vote
            ]);
        }
        return $this->errorResponse('Can not vote for my discussion', Response::HTTP_BAD_REQUEST);
    }

    public function setPin(Request $request, $id)
    {
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

    public function removeNewMark(Request $request, $id)
    {
        $user = auth()->user();
        $this->discussionRemoveNewRepo->deleteConditions([['created_at', '<=',  Carbon::now()->subDays(3)]]);
        $this->discussionRemoveNewRepo->create(['discussion_id' => $id, 'user_id' => $user->id]);

        return $this->metaSuccess();
    }

    public function getComment(Request $request, $id)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = DiscussionComment::with(['user', 'user.profile'])
            ->where('discussion_comments.discussion_id', $id)
            ->select([
                'discussion_comments.*',
            ])->orderBy('discussion_comments.created_at', 'DESC')->paginate($limit);

        return $this->successResponse($data);
    }

    public function getDraftDiscussions(Request $request)
    {
        $limit = $request->limit ?? 15;
        $user = auth()->user();
        $data = Discussion::with(['user', 'user.profile'])->where('discussions.is_draft', 1)
            ->where('discussions.user_id', $user->id)
            ->orderBy('discussions.created_at', 'DESC')->paginate($limit);
        return $this->successResponse($data);
    }

    public function deleteDraftDiscussions($id)
    {
        $user = auth()->user();
        $discussion = Discussion::where('id', $id)->where('discussions.is_draft', 1)->where('discussions.user_id', $user->id)->first();
        if($discussion) {
            $discussion->delete();
            return $this->metaSuccess();
        } else {
            return $this->errorResponse('Can not delete draft', Response::HTTP_BAD_REQUEST);
        }
    }
}
