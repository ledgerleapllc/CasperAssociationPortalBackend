<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Perk;
use App\Models\PerkResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PerkController extends Controller
{
    public function createPerk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:70',
            'content' => 'required',
            'action_link' => 'required|url',
            'image' => 'required|mimes:jpeg,jpg,png,gif|max:100000',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'setting' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $user = auth()->user();
        $now = Carbon::now()->format('Y-m-d');
        $perk = new Perk();
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $setting = $request->setting;
        $visibility = 'hidden';
        $status = 'inactive';

        if ($startDate && $endDate && $startDate > $endDate) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }

        $perk->user_id = $user->id;
        $perk->title = $request->title;
        $perk->content = $request->content;
        $perk->action_link = $request->action_link;
        $perk->start_date = $request->start_date;
        $perk->end_date = $request->end_date;
        $perk->setting = $request->setting;

        $filenameWithExt = $request->file('image')->getClientOriginalName();
        //Get just filename
        $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
        // Get just ext
        $extension = $request->file('image')->getClientOriginalExtension();
        // Filename to store
        $fileNameToStore = $filename . '_' . time() . '.' . $extension;
        // Upload Image
        $path = $request->file('image')->storeAs('perk', $fileNameToStore);
        $perk->image = $path;

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
            $status = 'inactive';
        }
        $perk->visibility = $visibility;
        $perk->status = $status;
        $perk->save();
        return $this->successResponse($perk);
    }

    public function getPerksAdmin(Request $request)
    {
        $limit = $request->limit ?? 15;
        $sort_key = $request->sort_key ?? 'created_at';
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
    public function updatePerk(Request $request, $id)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:70',
            'content' => 'nullable',
            'action_link' => 'nullable|url',
            'image' => 'nullable|mimes:jpeg,jpg,png,gif|max:100000',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'setting' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = auth()->user();
        $now = Carbon::now()->format('Y-m-d');
        $perk = Perk::where('id', $id)->first();
        if (!$perk) {
            return $this->errorResponse('Not found perk', Response::HTTP_BAD_REQUEST);
        }
        $startDate = array_key_exists('start_date', $data) ? $request->start_date : $perk->start_date;
        $endDate = array_key_exists('end_date', $data) ? $request->end_date : $perk->end_date;
        $setting = isset($request->setting) ? $request->setting : $perk->setting;

        $visibility = 'hidden';
        $status = 'inactive';
        if ($startDate && $endDate && $startDate > $endDate) {
            return $this->errorResponse('End date must greater than start date', Response::HTTP_BAD_REQUEST);
        }
        if ($request->title) {
            $perk->title = $request->title;
        }
        if ($request->content) {
            $perk->content = $request->content;
        }
        if (array_key_exists('start_date', $data)) {
            $perk->start_date = $request->start_date;
        }
        if (array_key_exists('end_date', $data)) {
            $perk->end_date = $request->end_date;
        }
        if (isset($request->setting)) {
            $perk->setting = $request->setting;
        }
        if ($request->hasFile('image')) {
            $filenameWithExt = $request->file('image')->getClientOriginalName();
            //Get just filename
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            // Get just ext
            $extension = $request->file('image')->getClientOriginalExtension();
            // Filename to store
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;
            // Upload Image
            $path = $request->file('image')->storeAs('perk', $fileNameToStore);
            $perk->image = $path;
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
            if ($endDate && $endDate < $now) {
                $visibility = 'hidden';
                $status = 'expired';
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
            $status = 'inactive';
        }
        $perk->visibility = $visibility;
        $perk->status = $status;
        $perk->save();
        return $this->successResponse($perk);
    }

    public function deletePerk($id)
    {
        Perk::where('id', $id)->delete();
        return $this->metaSuccess();
    }

    public function getPerkResultAdmin(Request $request, $id)
    {
        $limit = $request->limit ?? 15;
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
        $user = auth()->user();
        $limit = $request->limit ?? 15;
        $sort_key = $request->sort_key ?? 'created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        $perks = Perk::where('visibility', 'visible')->orderBy($sort_key, $sort_direction)->paginate($limit);
        return $this->successResponse($perks);
    }

    public function getPerkDetailUser($id)
    {
        $user = auth()->user();
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
