<?php

namespace App\Services;

use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class ShuftiproCheck
{

    public function handle($item)
    {
        $keys = [
            'production' => [
                'clientId' => 'e4tot3Y50imDSYYsVcWQbpQqG4jmZ7i7XoblVDp3CavbFn9Tvr1613403633',
                'clientSecret' => '$2y$10$f5Q.YTY6bl2wvmbS.aT8pONrDSxieRzmzQQdH.2VJkFdj7cRaR05i'
            ],
            'test' => [
                'clientId' => 'KgeOivDVEpzTrAqkx8aBwLJenCgKnW4SSQDbv17hRq8fyhvZhD1612459148',
                'clientSecret' => '$2y$10$wHWfyqB/1dhfTKwQQnstv.k0y9z2gYHBhkkRMkBPtPPOpYODHi6l6'
            ]
        ];

        // $mode = 'production';
        $mode = 'test';


        $url = 'https://api.shuftipro.com/status';
        $client_id  = $keys[$mode]['clientId'];
        $secret_key = $keys[$mode]['clientSecret'];

        $auth = $client_id . ":" . $secret_key;

        $response = Http::withBasicAuth($client_id, $secret_key)->post($url, [
            'reference' => $item->reference_id
        ]);

        $data = $response->json();
        if (!$data || !is_array($data)) return;

        if (
            !isset($data['reference']) ||
            !isset($data['event'])
        ) {
            return "error";
        }

        $events = [
            'verification.accepted',
            'verification.declined'
        ];

        $user_id = (int) $item->user_id;
        $reference_id = $data['reference'];
        $event = $data['event'];

        // Remove Other Temp Records
        ShuftiproTemp::where('user_id', $user_id)
            ->where('reference_id', '!=', $reference_id)
            ->delete();

        // Event Validation
        if (!in_array($event, $events))
            return "error";

        // Temp Record
        $temp = ShuftiproTemp::where('reference_id', $reference_id)->first();
        if (!$temp) return "error";

        $declined_reason = isset($data['declined_reason']) ? $data['declined_reason'] : null;
        $proofs = isset($data['proofs']) ? $data['proofs'] : null;
        $verification_result = isset($data['verification_result']) ? $data['verification_result'] : null;
        $verification_data = isset($data['verification_data']) ? $data['verification_data'] : null;

        $is_successful = $event == 'verification.accepted' ? 1 : 0;
        $status = $is_successful ? 'approved' : 'denied';

        $data = json_encode([
            'declined_reason' => $declined_reason,
            'event' => $event,
            'proofs' => $proofs,
            'verification_result' => $verification_result,
            'verification_data' => $verification_data
        ]);

        $document_proof = $address_proof = null;
        $document_result =
            $address_result =
            $background_checks_result = 0;

        // Document Proof
        if (
            $proofs &&
            isset($proofs['document']) &&
            isset($proofs['document']['proof'])
        ) {
            $document_proof = $proofs['document']['proof'];
        }

        // Address Proof
        if (
            $proofs &&
            isset($proofs['address']) &&
            isset($proofs['address']['proof'])
        ) {
            $address_proof = $proofs['address']['proof'];
        }

        // Document Result
        if (
            $verification_result &&
            isset($verification_result['document'])
        ) {
            $zeroCount = $oneCount = 0;
            foreach ($verification_result['document'] as $key => $value) {
                if ($key == 'document_proof') continue;

                $value = (int) $value;

                if ($value)
                    $oneCount++;
                else
                    $zeroCount++;
            }

            if ($oneCount && !$zeroCount)
                $document_result = 1;
        }

        // Address Result
        if (
            $verification_result &&
            isset($verification_result['address'])
        ) {
            $zeroCount = $oneCount = 0;
            foreach ($verification_result['address'] as $key => $value) {
                if ($key == 'address_document_proof') continue;

                $value = (int) $value;

                if ($value)
                    $oneCount++;
                else
                    $zeroCount++;
            }

            if ($oneCount && !$zeroCount)
                $address_result = 1;
        }

        // Background Checks Result
        if (
            $verification_result &&
            isset($verification_result['background_checks']) &&
            (int) $verification_result['background_checks'] === 1
        ) {
            $background_checks_result = 1;
        }

        Shuftipro::where('user_id', $user_id)->delete();


        $record = new Shuftipro();
        $record->user_id = $user_id;
        $record->reference_id = $reference_id;
        $record->is_successful = $is_successful;
        $record->data = $data;
        $record->document_result = $document_result;
        $record->address_result = $address_result;
        $record->background_checks_result = $background_checks_result;
        $record->status = $status;
        $record->reviewed = $is_successful ? 1 : 0; // No need to review successful ones

        if ($document_proof)
            $record->document_proof = $document_proof;
        if ($address_proof)
            $record->address_proof = $address_proof;

        $record->save();

        // Update Temp Record
        $temp->status = 'processed';
        $temp->save();

        $user = User::find($user_id);

        if ($status == "approved") {
            $user->kyc_verified_at = now();
            $user->save();
        }
    }
}
