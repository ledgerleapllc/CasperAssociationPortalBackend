<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationView;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function getHighPriority()
    {
        $data = Notification::where('high_priority', 1)->where('type', 'Banner')->first();
        return $this->successResponse($data);
    }

    public function getNotificationUser(Request $request)
    {
        $data = [];
        $type = $request->type ?? '';
        $user = auth()->user();
        $ids = NotificationView::join('notification', 'notification.id', '=', 'notification_view.notification_id')
            ->where('visibility', 'visible')
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('type', 'Popup')
                    ->where('show_login', 0);})
                ->orWhere(function ($query) {
                    $query->where('type', 'Banner')
                    ->whereNotNull('notification_view.dismissed_at');
                });
            })->where('notification_view.user_id', $user->id)
            ->select(['notification_view.notification_id'])->groupBy('notification_view.notification_id')->get();
        if ($type) {
            $data = Notification::where('visibility', 'visible')
            ->where('type', $type)
            ->whereNotIn('id', $ids)->get();
        } else {
            $data = Notification::where('visibility', 'visible')->whereNotIn('id', $ids)->get();
        }
        
        return $this->successResponse($data);
    }

    public function dismiss($id)
    {
        $user = auth()->user();
        $notification = Notification::where('id', $id)->where('type', 'Banner')->where('visibility', 'visible')->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }
        $notificationView = NotificationView::where('notification_id', $id)->where('user_id', $user->id)->first();
        if (!$notificationView) {
            $notificationView = new notificationView();
            $notificationView->user_id = $user->id;
            $notificationView->notification_id = $id;
            $notificationView->first_view_at = now();
            $notificationView->dismissed_at = now();
            $notificationView->save();

            $notification->total_views = $notification->total_views + 1;
            $notification->save();
        } else {
            if (!$notificationView->dismissed_at) {
                $notificationView->dismissed_at = now();
                $notificationView->save();
            }
        }
        
        return $this->metaSuccess();
    }

    public function clickCTA($id)
    {
        $user = auth()->user();
        $notification = Notification::where('id', $id)->where('visibility', 'visible')->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }
        $notificationView = NotificationView::where('notification_id', $id)->where('user_id', $user->id)->first();
        if (!$notificationView) {
            $notificationView = new notificationView();
            $notificationView->user_id = $user->id;
            $notificationView->notification_id = $id;
            $notificationView->first_view_at = now();
            $notificationView->cta_click_at = now();
            $notificationView->cta_click_count = $notificationView->cta_click_count + 1;
            $notificationView->save();

            $notification->total_views = $notification->total_views + 1;
            $notification->save();
        } else {
            if (!$notificationView->cta_click_at) {
                $notificationView->cta_click_at = now();
            }
            $notificationView->cta_click_count = $notificationView->cta_click_count + 1;
            $notificationView->save();
        }
        
        return $this->metaSuccess();
    }

    public function updateView($id)
    {
        $user = auth()->user();
        $notification = Notification::where('id', $id)->where('visibility', 'visible')->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }
        $notificationView = NotificationView::where('notification_id', $id)->where('user_id', $user->id)->first();
        if (!$notificationView) {
            $notificationView = new notificationView();
            $notificationView->user_id = $user->id;
            $notificationView->notification_id = $id;
            $notificationView->first_view_at = now();
            $notificationView->save();
            
            $notification->total_views = $notification->total_views + 1;
            $notification->save();
        }
        
        return $this->metaSuccess();
    }

    public function getNotification(Request $request) 
    {
        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        if (isset($request->setting)) {
            $notification = Notification::where('setting', $request->setting)->orderBy($sort_key, $sort_direction)->paginate($limit);
        } else {
            $notification = Notification::orderBy($sort_key, $sort_direction)->paginate($limit);
        }
        $ids = [];
        foreach ($notification as $notif) { 
            array_push($ids, $notif->id);
        }
        $count = User::where('role', 'member')->whereNotNull('letter_verified_at')
            ->whereNotNull('node_verified_at')->whereNotNull('signature_request_id')
            ->orderBy($sort_key, $sort_direction)->count();
        $data = ["notifications" => $notification, "total_member" => $count, "ids" => $ids];
        return $this->successResponse($data);
    }

    public function getUserViewLogs(Request $request, $id) 
    {
        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        $notification = Notification::where('id', $id)->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }
        $count = User::where('role', 'member')->whereNotNull('letter_verified_at')
            ->whereNotNull('node_verified_at')->whereNotNull('signature_request_id')
            ->orderBy($sort_key, $sort_direction)->count();

        $notificationView = NotificationView::where('notification_id', $id)
            ->orderBy($sort_key, $sort_direction)
            ->with('user')->paginate($limit);
        $data = ["notification" => $notification, "total_member" => $count, "users" => $notificationView];
        return $this->successResponse($data);
    }

    public function createNotification(Request $request)
    {
        $validatorType = Validator::make($request->all(), [
            'type' => 'required|in:Banner,Popup',
            'have_action' => 'required|in:0,1',
            'action_link' => 'nullable|url',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'setting' => 'required|in:0,1',
            'body' => 'required|string|max:200',
        ]);
        if ($validatorType->fails()) {
            return $this->validateResponse($validatorType->errors());
        }
        $type =  $request->type;
        if ($type == 'Banner') {
            $validator = Validator::make($request->all(), [
                'high_priority' => 'required|in:0,1',
                'allow_dismiss_btn' => 'required|in:0,1',
                'title' => 'required|string|max:60',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'show_login' => 'required|in:0,1',
                'title' => 'required|string|max:150',
                'btn_text' => 'nullable|string|max:25',
            ]);
        }
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $now = Carbon::now()->format('Y-m-d');
        $notification = new Notification();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $setting = $request->setting;
        $visibility = 'hidden';
        $status = 'OFF';

        if ($startDate && $endDate && $startDate > $endDate) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }

        $notification->type = $type;
        $notification->title = $request->title;
        $notification->body = $request->body;
        $notification->have_action = $request->have_action;
        $notification->action_link = $request->action_link;
        $notification->start_date = $request->start_date;
        $notification->end_date = $request->end_date;
        $notification->setting = $request->setting;
        if ($type == 'Banner') { 
            $notification->high_priority = $request->high_priority;
            $notification->allow_dismiss_btn = $request->allow_dismiss_btn;
        } else {
            $notification->show_login = $request->show_login;
            $notification->btn_text = $request->btn_text;
        }

        // check visibility and status
        if ($setting == 1) {
            if ($startDate && $endDate && ($now >= $startDate && $now <= $endDate)) {
                $visibility = 'visible';
                $status = 'active';
            }
            if (!$startDate && !$endDate) {
                $visibility = 'visible';
                $status = 'active';
            }
            if ($endDate && $endDate >= $now) {
                $visibility = 'visible';
                $status = 'active';
            }
            if ($startDate && $startDate > $now) {
                $visibility = 'hidden';
                $status = 'waiting';
            }
            if ($startDate && $startDate <= $now) {
                $visibility = 'visible';
                $status = 'active';
            }
        } else {
            $visibility = 'hidden';
            $status = 'OFF';
        }
        $notification->visibility = $visibility;
        $notification->status = $status;
        $notification->save();
        return $this->successResponse($notification);
    }

    public function updateNotification(Request $request, $id) 
    {
        $validatorType = Validator::make($request->all(), [
            'type' => 'required|in:Banner,Popup',
            'have_action' => 'required|in:0,1',
            'action_link' => 'nullable|url',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'setting' => 'required|in:0,1',
            'body' => 'required|string|max:200',
        ]);
        if ($validatorType->fails()) {
            return $this->validateResponse($validatorType->errors());
        }
        $type =  $request->type;
        if ($type == 'Banner') {
            $validator = Validator::make($request->all(), [
                'high_priority' => 'required|in:0,1',
                'allow_dismiss_btn' => 'required|in:0,1',
                'title' => 'required|string|max:60',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'show_login' => 'required|in:0,1',
                'title' => 'required|string|max:150',
                'btn_text' => 'nullable|string|max:25',
            ]);
        }
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $now = Carbon::now()->format('Y-m-d');
        $notification = Notification::where('id', $id)->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $setting = $request->setting;
        $visibility = 'hidden';
        $status = 'OFF';

        if ($startDate && $endDate && $startDate > $endDate) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }

        $notification->type = $type;
        $notification->title = $request->title;
        $notification->body = $request->body;
        $notification->have_action = $request->have_action;
        $notification->action_link = $request->action_link;
        $notification->start_date = $request->start_date;
        $notification->end_date = $request->end_date;
        $notification->setting = $request->setting;
        if ($type == 'Banner') { 
            $notification->high_priority = $request->high_priority;
            $notification->allow_dismiss_btn = $request->allow_dismiss_btn;
        } else {
            $notification->show_login = $request->show_login;
            $notification->btn_text = $request->btn_text;
        }

        // check visibility and status
        if ($setting == 1) {
            if ($startDate && $endDate && ($now >= $startDate && $now <= $endDate)) {
                $visibility = 'visible';
                $status = 'active';
            }
            if (!$startDate && !$endDate) {
                $visibility = 'visible';
                $status = 'active';
            }
            if ($endDate && $endDate >= $now) {
                $visibility = 'visible';
                $status = 'active';
            }
            if ($startDate && $startDate > $now) {
                $visibility = 'hidden';
                $status = 'waiting';
            }
            if ($startDate && $startDate <= $now) {
                $visibility = 'visible';
                $status = 'active';
            }
        } else {
            $visibility = 'hidden';
            $status = 'OFF';
        }
        $notification->visibility = $visibility;
        $notification->status = $status;
        $notification->save();
        return $this->successResponse($notification);
    }

    public function getNotificationDetail($id) 
    {
        $notification = Notification::where('id', $id)->first();
        if (!$notification) {
            return $this->errorResponse('Not found notification', Response::HTTP_BAD_REQUEST);
        }

        return $this->successResponse($notification); 
    }
}
