<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;

use App\Models\Perk;
use App\Models\PerkResult;

use App\Jobs\PerkNotification;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use Aws\S3\S3Client;

class PerkController extends Controller
{
    public function createPerk(Request $request) {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:70',
            'content' => 'required',
            'action_link' => 'required|url',
            // 'image' => 'required|mimes:pdf,jpeg,jpg,png,gif,txt,rtf|max:200000',
            'image' => 'required|mimes:pdf,jpeg,jpg,png,gif,txt,rtf|max:2048',
            'start_date' => 'required|nullable|date_format:Y-m-d',
            'end_date' => 'required|nullable|date_format:Y-m-d|after_or_equal:today',
            'start_time' => 'required|nullable|date_format:H:i:s',
            'end_time' => 'required|nullable|date_format:H:i:s',
            'setting' => 'required|in:0,1',
            'timezone' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $user = auth()->user();

        $timezone = $request->timezone;

        $startTime = $request->start_date . ' ' . $request->start_time;
        $startTimeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $startTime, $timezone);
        $startTimeCarbon->setTimezone('UTC');

        $endTime = $request->end_date . ' ' . $request->end_time;
        $endTimeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $endTime, $timezone);
        $endTimeCarbon->setTimezone('UTC');

        $setting = $request->setting;

        if ($startTimeCarbon->gte($endTimeCarbon)) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }

        $perk = new Perk();
        $perk->user_id = $user->id;
        $perk->title = $request->title;
        $perk->content = $request->content;
        $perk->action_link = $request->action_link;
        $perk->start_date = $request->start_date;
        $perk->end_date = $request->end_date;
        $perk->start_time = $request->start_time;
        $perk->end_time = $request->end_time;
        $perk->setting = $setting;
        $perk->timezone = $timezone;
        $perk->time_begin = $startTimeCarbon;
        $perk->time_end = $endTimeCarbon;

        $filenameWithExt = $request->file('image')->getClientOriginalName();
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        $extension = $request->file('image')->getClientOriginalExtension();

        $filenamehash = md5(Str::random(10) . '_' . (string) time());
        $fileNameToStore = $filenamehash . '.' . $extension;

        // S3 file upload
        $S3 = new S3Client([
            'version' => 'latest',
            'region' => getenv('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
        $s3result = $S3->putObject([
            'Bucket' => getenv('AWS_BUCKET'),
            'Key' => 'client_uploads/' . $fileNameToStore,
            'SourceFile' => $request->file('image')
        ]);
        // $ObjectURL = 'https://'.getenv('AWS_BUCKET').'.s3.amazonaws.com/client_uploads/'.$fileNameToStore;
        $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL') . '/not-found';
        $perk->image = $ObjectURL;

        // check visibility and status
        $now = Carbon::now('UTC');
        $visibility = 'hidden';
        $status = 'inactive';
        if ($setting == 1) {
			if ($now >= $startTimeCarbon && $now <= $endTimeCarbon) {
				$visibility = 'visible';
				$status = 'active';
			} else if ($now < $startTimeCarbon) {
				$visibility = 'hidden';
				$status = 'waiting';
			} else if ($now > $endTimeCarbon) {
				$visibility = 'hidden';
				$status = 'expired';
			}
        } else {
            $visibility = 'hidden';
            $status = 'inactive';
        }
        $perk->visibility = $visibility;
        $perk->status = $status;
        $perk->save();

        if ($perk->visibility == 'visible' && $perk->status == 'active') {
	    	PerkNotification::dispatch($perk)->onQueue('default_long');
	    }

        return $this->successResponse($perk);
    }

    public function updatePerk(Request $request, $id)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:70',
            'content' => 'nullable',
            'action_link' => 'nullable|url',
            // 'image' => 'nullable|mimes:pdf,jpeg,jpg,png,gif,txt,rtf|max:200000',
            'image' => 'nullable|mimes:pdf,jpeg,jpg,png,gif,txt,rtf|max:2048',
            'start_date' => 'required|nullable',
            'end_date' => 'required|nullable',
            'start_time' => 'required|nullable|date_format:H:i:s',
            'end_time' => 'required|nullable|date_format:H:i:s',
            'setting' => 'nullable|in:0,1',
            'timezone' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = auth()->user();
        
        $perk = Perk::where('id', $id)->first();
        if (!$perk) {
            return $this->errorResponse('Not found perk', Response::HTTP_BAD_REQUEST);
        }

        $originalStatus = $perk->status;
        $originalVisibility = $perk->visibility;

        $timezone = $request->timezone;

        $startTime = $request->start_date . ' ' . $request->start_time;
        $startTimeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $startTime, $timezone);
        $startTimeCarbon->setTimezone('UTC');

        $endTime = $request->end_date . ' ' . $request->end_time;
        $endTimeCarbon = Carbon::createFromFormat('Y-m-d H:i:s', $endTime, $timezone);
        $endTimeCarbon->setTimezone('UTC');

        if ($startTimeCarbon->gte($endTimeCarbon)) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }

        if ($request->title) {
            $perk->title = $request->title;
        }
        if ($request->content) {
            $perk->content = $request->content;
        }
        
        $perk->start_date = $request->start_date;
        $perk->end_date = $request->end_date;
        $perk->start_time = $request->start_time;
        $perk->end_time = $request->end_time;
        $perk->timezone = $timezone;
        $perk->time_begin = $startTimeCarbon;
        $perk->time_end = $endTimeCarbon;

        if (isset($request->setting)) {
            $perk->setting = $request->setting;
        }

        if ($request->hasFile('image')) {
            $extension = $request->file('image')->getClientOriginalExtension();
            $filenamehash = md5(Str::random(10) . '_' . (string)time());
            $fileNameToStore = $filenamehash . '.' . $extension;

            // S3 file upload
            $S3 = new S3Client([
                'version' => 'latest',
                'region' => getenv('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3result = $S3->putObject([
                'Bucket' => getenv('AWS_BUCKET'),
                'Key' => 'client_uploads/'.$fileNameToStore,
                'SourceFile' => $request->file('image')
            ]);

            // $ObjectURL = 'https://'.getenv('AWS_BUCKET').'.s3.amazonaws.com/client_uploads/'.$fileNameToStore;
            $ObjectURL = $s3result['ObjectURL'] ?? getenv('SITE_URL').'/not-found';
            $perk->image = $ObjectURL;
        }

        // check visibility and status
        $visibility = 'hidden';
        $status = 'inactive';
        $setting = $perk->setting;
        $now = Carbon::now('UTC');
		if ($setting == 1) {
			if ($now >= $startTimeCarbon && $now <= $endTimeCarbon) {
				$visibility = 'visible';
				$status = 'active';
			} else if ($now < $startTimeCarbon) {
				$visibility = 'hidden';
				$status = 'waiting';
			} else if ($now > $endTimeCarbon) {
				$visibility = 'hidden';
				$status = 'expired';
			}
        } else {
            $visibility = 'hidden';
            $status = 'inactive';
        }
        $perk->visibility = $visibility;
        $perk->status = $status;
        $perk->save();

        if (
        	$perk->visibility == 'visible' &&
        	$perk->status == 'active' &&
        	($perk->visibility != $originalVisibility || $perk->status != $originalStatus)
        ) {
	    	PerkNotification::dispatch($perk)->onQueue('default_long');
	    }

        return $this->successResponse($perk);
    }

    public function getPerksAdmin(Request $request)
    {
        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'end_date';
        $sort_direction = $request->sort_direction ?? 'desc';
        if (isset($request->setting)) {
            $perks = Perk::where('setting', $request->setting)->orderBy($sort_key, $sort_direction)->paginate($limit);
        } else {
            $perks = Perk::orderBy($sort_key, $sort_direction)->paginate($limit);
        }
        return $this->successResponse($perks);
    }

    public function getPerkDetailAdmin($id)
    {
        $perk = Perk::where('id', $id)->first();
        if (!$perk) {
            return $this->errorResponse('Not found perk', Response::HTTP_BAD_REQUEST);
        }
        return $this->successResponse($perk);
    }

    public function deletePerk($id)
    {
        PerkResult::where('perk_id', $id)->delete();        
        Perk::where('id', $id)->delete();
        return $this->metaSuccess();
    }

    public function getPerkResultAdmin(Request $request, $id)
    {
        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'perk_result.created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        $perk = PerkResult::join('perk', 'perk_result.perk_id', '=', 'perk.id')
            ->join('users', 'perk.user_id', '=', 'users.id')->where('perk_result.perk_id', $id)
            ->select([
                'perk_result.user_id',
                'perk_result.perk_id',
                'perk_result.created_at',
                'perk_result.views',
                'perk.total_views',
                'perk.total_clicks',
                'users.email',
            ])->orderBy($sort_key, $sort_direction)->paginate($limit);
        return $this->successResponse($perk);
    }

    public function getPerksUser(Request $request)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'perks'))
            return $this->successResponse(['data' => []]);

        $limit = $request->limit ?? 50;
        $sort_key = $request->sort_key ?? 'created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        $perks = Perk::where('visibility', 'visible')->orderBy($sort_key, $sort_direction)->paginate($limit);
        return $this->successResponse($perks);
    }

    public function getPerkDetailUser($id)
    {
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'perks'))
            return $this->errorResponse('Your access is blocked', Response::HTTP_BAD_REQUEST);

        $perk = Perk::where('visibility', 'visible')->where('id', $id)->first();
        if (!$perk) {
            return $this->errorResponse('Not found perk', Response::HTTP_BAD_REQUEST);
        }
        $perkResul = PerkResult::where('perk_id', $id)->where('user_id', $user->id)->first();
        if ($perkResul) {
            $perk->total_views = $perk->total_views + 1;

            $perkResul->views =  $perkResul->views +1;
            $perkResul->save();
        } else {
            $newPerkResult = new PerkResult();
            $newPerkResult->perk_id = $id;
            $newPerkResult->user_id = $user->id;
            $newPerkResult->views = 1;
            $newPerkResult->save();

            $perk->total_views = $perk->total_views + 1;
            $perk->total_clicks = $perk->total_clicks + 1;
        }
        $perk->save();
        return $this->successResponse($perk);
    }
}
