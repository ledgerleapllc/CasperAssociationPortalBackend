<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelloSignController extends Controller
{
    // Hellosign Hook
    public function hellosignHook(Request $request)
    {
        Log::info($request);

        $payload = $request->get('json');
        Log::info($payload);
        if (!$payload) return "error";

        $data = json_decode($payload, true);
        $api_key = 'e0c85dde1ba2697d4236a6bc6c98ed2d3ca7e3b1cb375f35b286f2c0d07b22d8';

        if (!is_array($data)) return "error";

        $md5_header_check = base64_encode(hash_hmac('md5', $payload, $api_key));
        $md5_header = $request->header('Content-MD5');
        if ($md5_header != $md5_header_check)
            return "error";
        // Valid Request
        if (
            isset($data['event']) &&
            $data['event']['event_type'] == 'signature_request_all_signed' &&
            isset($data['signature_request'])
        ) {
            $signature_request_id = $data['signature_request']['signature_request_id'];
            $filepath = 'hellosign/hellosign_' . $signature_request_id . '.pdf';

            $client = new \HelloSign\Client($api_key);
            $client->getFiles($signature_request_id, $filepath, \HelloSign\SignatureRequest::FILE_TYPE_PDF);

            $user = User::where('signature_request_id', $signature_request_id)->first();

            if ($user) {
                $user->hellosign_form = $filepath;
                $user->save();
            }
            return "Hello API Event Received";
        }
    }
}
