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
        $payload = $request->get('json');

        if (!$payload) {
            return "error";
        }

        $data = json_decode($payload, true);
        $api_key = env('HELLOSIGN_API_KEY_HOOK');

        if (!is_array($data)) {
            return "error";
        }

        // hellosign test check
        $callback_test = $data['event']['event_type'] ?? '';

        if ($callback_test == 'callback_test') {
            return "Hello API Event Received";
        }

        $md5_header_check = base64_encode(hash_hmac('md5', $payload, $api_key));
        $md5_header = $request->header('Content-MD5');

        if ($md5_header != $md5_header_check) {
            return "error";
        }

        // Valid Request
        if (
            isset($data['event']) &&
            $data['event']['event_type'] == 'signature_request_all_signed' &&
            isset($data['signature_request'])
        ) {
            $signature_request_id = $data['signature_request']['signature_request_id'];
            $filepath = 'hellosign/hellosign_' . $signature_request_id . '.pdf';

            $client = new \HelloSign\Client($api_key);

            // $sig_link = $client->getFiles(
            //     $signature_request_id, 
            //     null, 
            //     \HelloSign\SignatureRequest::FILE_TYPE_PDF
            // );
            // info($sig_link);

            $user = User::where('signature_request_id', $signature_request_id)->first();

            if ($user) {
                $user->hellosign_form = '';
                $user->save();
            }

            return "Hello API Event Received";
        }
    }
}
