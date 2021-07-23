<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class MetricController extends Controller
{
    public function getMetric()
    {
        $user = auth()->user();
        $metric = Metric::where('user_id', $user->id)->first();
        return $this->successResponse($metric);
    }

    public function updateMetric(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'uptime' => 'nullable|numeric|between:0,100',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = User::where('id', $id)->where('role', 'member')->first();
        if(!$user) {
            return $this->errorResponse('User not found', Response::HTTP_BAD_REQUEST);
        }
        $metric = Metric::where('user_id', $id)->first();
        if (!$metric) {
            $metric = new Metric();
        }
        if (isset($request->uptime) && $request->uptime != null) {
            $metric->uptime = $request->uptime;
        }
        if (isset($request->block_height_average) && $request->block_height_average != null ) {
            $metric->block_height_average = $request->block_height_average;
        }
        if (isset($request->update_responsiveness) && $request->update_responsiveness != null ) {
            $metric->update_responsiveness = $request->update_responsiveness;
        }
        if (isset($request->peers) && $request->peers != null ) {
            $metric->peers = $request->peers;
        }
        $metric->user_id = $id;
        $metric->save();
        return $this->successResponse($metric);
    }

    public function getMetricUser($id)
    {
        $metric = Metric::where('user_id', $id)->first();
        if(!$metric) {
            return $this->successResponse([]);
        }
        return $this->successResponse($metric);
    }
}
