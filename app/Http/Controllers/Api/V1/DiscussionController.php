<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

use App\Repositories\UserRepository;
use App\Repositories\DiscussionRepository;
use App\Repositories\DiscussionCommentRepository;
use App\Repositories\DiscussionPinRepository;
use App\Repositories\DiscussionVoteRepository;
use App\Repositories\DiscussionRemoveNewRepository;

use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\DiscussionPin;
use App\Models\DiscussionRemoveNew;
use App\Models\DiscussionVote;

use Carbon\Carbon;

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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse([]);

        $trendings = Discussion::where('likes', '!=', 0)
        						->where('is_draft', 0)
        						->take(9)
        						->orderBy('likes', 'desc')
        						->get();
        $count = Discussion::where('likes', '!=', 0)
        					->where('is_draft', 0)
        					->orderBy('likes', 'desc')
        					->count();
        if ($count >= 9) {
            return $this->successResponse($trendings);
        } else {
            $remains = 9 - $count;
            $trending_ids = $trendings->pluck('id');
            // $removed_ids = DiscussionRemoveNew::where(['user_id' => $user->id])->pluck('discussion_id');
            $news = Discussion::whereNotIn('id', $trending_ids)
                // ->whereNotIn('id', $removed_ids)
                ->where('is_draft', 0)
                ->take($remains)->orderBy('id', 'desc')->get();
            $trendingArray = $trendings->toArray();
            $trendingArray = array_merge($trendingArray, $news->toArray());
            return $this->successResponse($trendingArray);
        }
    }

    // Get Discussions
    public function getDiscussions(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

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
        if ($discussion) {
            $discussion->read = $discussion->read + 1;
            $discussion->save();
            $discussion->total_pinned = DiscussionPin::where('discussion_id', $id)->count();
        }
        return $this->successResponse($discussion);
    }
    
    public function updateDiscussion($id, Request $request) {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $discussion = $this->discussionRepo->update($id, [
            "title" => $request->title,
            "description" => $request->description,
        ]);
        
        return $this->successResponse($discussion);
    }

    public function postDiscussion(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
            // 'is_draft' => 'required|in:0,1'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $discussion = Discussion::where('id', $id)->where('is_draft', 1)->first();
        if($discussion) {
            $discussion->is_draft = 0;
            $discussion->save();
        }
        return $this->metaSuccess();
    }

    public function createComment(Request $request, $id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $data = [];
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
        if ($discussion) {
            $discussion->comments = $discussion->comments + 1;
            $discussion->save();
        }

        $data['comment']['user'] = $user;

        return $this->successResponse($data);
    }

    public function updateComment(Request $request, $id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $validator = Validator::make($request->all(), [
            'description' => 'required',
            'comment_id' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $comment = DiscussionComment::where('id', $request->comment_id)
        							->where('discussion_id', $id)
        							->where('user_id', $user->id)->first();
        if ($comment) {
            $comment->description = $request->description;
            $comment->edited_at = Carbon::now('UTC');
            $comment->save();
            return $this->successResponse($comment);
        }
        return $this->errorResponse('Invalid discussion id', Response::HTTP_BAD_REQUEST);
    }

    public function deleteComment($id, $commentId)
    {
    	$user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $comment = DiscussionComment::where('id', $commentId)
        							->where('discussion_id', $id)
        							->where(function ($query) use ($user) {
        								if ($user->role != 'admin') {
        									$query->where('user_id', $user->id);
        								}
        							})
        							->first();
        if ($comment) {
        	$comment->description = '<p>Comment deleted</p>';
        	$comment->deleted_at = Carbon::now('UTC');
        	$comment->save();
        	return $this->metaSuccess();
        }
    	return $this->errorResponse('Invalid discussion id', Response::HTTP_BAD_REQUEST);
    }

    public function setVote(Request $request, $id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $this->discussionRemoveNewRepo->deleteConditions([['created_at', '<=',  Carbon::now('UTC')->subDays(3)]]);
        $this->discussionRemoveNewRepo->create(['discussion_id' => $id, 'user_id' => $user->id]);

        return $this->metaSuccess();
    }

    public function getComment(Request $request, $id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
        $data = DiscussionComment::with(['user', 'user.profile'])
            ->where('discussion_comments.discussion_id', $id)
            ->select([
                'discussion_comments.*',
            ])->orderBy('discussion_comments.created_at', 'DESC')->paginate($limit);

        return $this->successResponse($data);
    }

    public function getDraftDiscussions(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
        $data = Discussion::with(['user', 'user.profile'])->where('discussions.is_draft', 1)
            ->where('discussions.user_id', $user->id)
            ->orderBy('discussions.created_at', 'DESC')->paginate($limit);
        return $this->successResponse($data);
    }

    public function deleteDiscussion($id)
    {
    	$user = auth()->user()->load(['pagePermissions']);
    	if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);
        
        $discussion = Discussion::where('id', $id)
        						->where(function ($query) use ($user) {
        							if ($user->role != 'admin') {
        								$query->where('user_id', $user->id);
        							}
        						})
        						->first();
        if (!$discussion) {
        	return $this->errorResponse('Can not delete discussion', Response::HTTP_BAD_REQUEST);
        }

        DiscussionComment::where('discussion_id', $discussion->id)->delete();
        DiscussionPin::where('discussion_id', $discussion->id)->delete();
        DiscussionRemoveNew::where('discussion_id', $discussion->id)->delete();
        DiscussionVote::where('discussion_id', $discussion->id)->delete();
        $discussion->delete();

        return $this->metaSuccess();
    }

    public function deleteDraftDiscussions($id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'discussions'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $discussion = Discussion::where('id', $id)->where('discussions.is_draft', 1)->where('discussions.user_id', $user->id)->first();
        if($discussion) {
            $discussion->delete();
            return $this->metaSuccess();
        } else {
            return $this->errorResponse('Can not delete draft', Response::HTTP_BAD_REQUEST);
        }
    }
}