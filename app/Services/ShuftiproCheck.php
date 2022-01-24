<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;

use Exception;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ShuftiproCheck
{
    public function handleExisting($item) {
        $url = 'https://api.shuftipro.com/status';
        $client_id = config('services.shufti.client_id');
        $secret_key = config('services.shufti.client_secret');

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
            'verification.declined',
            'verification.status.changed',
        ];

        $user_id = (int) $item->user_id;
        $reference_id = $data['reference'];
        $event = $data['event'];

        // Event Validation
        if (!in_array($event, $events))
            return "error";

        $declined_reason = isset($data['declined_reason']) ? $data['declined_reason'] : null;
        $proofs = isset($data['proofs']) ? $data['proofs'] : null;
        $verification_result = isset($data['verification_result']) ? $data['verification_result'] : null;
        $verification_data = isset($data['verification_data']) ? $data['verification_data'] : null;

        $is_successful = $event == 'verification.accepted' ? 1 : 0;
        $status = $is_successful ? 'approved' : 'denied';
        //Aml check
        $aml_declined_reason  = null;
        $hit  = null;

        if (
            isset($verification_data['background_checks']) &&
            $verification_data['background_checks']['aml_data'] &&
            $verification_data['background_checks']['aml_data']['hits']
        ) {
            $hits =  $verification_data['background_checks']['aml_data']['hits'];
            if (count($hits) > 0 && isset($hits[0]['fields']['Enforcement Type'])) {
                $type = $hits[0]['fields']['Enforcement Type'];
                if (count($type) > 0 && isset($type[0]['value'])) {
                    $aml_declined_reason = $type[0]['value'];
                }
            }
            if (count($hits) > 0 ) {
                $hit= $hits[0];
            }
        }

        $data = json_encode([
            'declined_reason' => $declined_reason,
            'event' => $event,
            'verification_result' => $verification_result,
            'aml_declined_reason' => $aml_declined_reason,
            'hit' => $hit,
        ]);

        $document_proof = $address_proof = null;
        $document_result = $address_result = $background_checks_result = 0;

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

        $record = $item;
        $record->user_id = $user_id;
        $record->reference_id = $reference_id;
        $record->is_successful = $is_successful;
        $record->data = $data;
        $record->document_result = $document_result;
        $record->address_result = $address_result;
        $record->background_checks_result = $background_checks_result;
        $record->status = $status;
        $record->reviewed = $is_successful ? 1 : 0; // No need to review successful ones

        if ($status == 'approved') {
            $record->manual_approved_at = now();
        }
        if ($document_proof) {
            $record->document_proof = $document_proof;
        }
        if ($address_proof) {
            $record->address_proof = $address_proof;
        }

        $record->save();

        $user = User::find($user_id);
        $user->kyc_verified_at = now();
        $user->save();
        
        if ($status == "approved") {
            $profile = Profile::where('user_id', $user_id);
            if ($profile) {
                $profile->status = 'approved';
                $profile->save();
            }
            return 'success';
        } else {
            return 'fail';
        }
    }

    public function handle($item)
    {
        $url = 'https://api.shuftipro.com/status';
        $client_id = config('services.shufti.client_id');
        $secret_key = config('services.shufti.client_secret');

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
        //Aml check
        $aml_declined_reason  = null;
        $hit  = null;

        if (
            isset($verification_data['background_checks']) &&
            $verification_data['background_checks']['aml_data'] &&
            $verification_data['background_checks']['aml_data']['hits']
        ) {
            $hits =  $verification_data['background_checks']['aml_data']['hits'];
            if (count($hits) > 0 && isset($hits[0]['fields']['Enforcement Type'])) {
                $type = $hits[0]['fields']['Enforcement Type'];
                if (count($type) > 0 && isset($type[0]['value'])) {
                    $aml_declined_reason = $type[0]['value'];
                }
            }
            if (count($hits) > 0 ) {
                $hit= $hits[0];
            }
        }

        $data = json_encode([
            'declined_reason' => $declined_reason,
            'event' => $event,
            // 'proofs' => $proofs,
            'verification_result' => $verification_result,
            'aml_declined_reason' => $aml_declined_reason,
            'hit' => $hit,
            // 'verification_data' => $verification_data
        ]);

        $document_proof = $address_proof = null;
        $document_result = $address_result = $background_checks_result = 0;

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

        if ($document_proof) {
            $record->document_proof = $document_proof;
            /*
            try {
                $url = strtok($document_proof, '?');
                $pathinfo = pathinfo($url);
                $contents = file_get_contents($url);
                $name = 'document_proof/document_' . time() . '.' . $pathinfo['extension'];
                Storage::put($name, $contents);
                $record->document_proof = $name;
            } catch (Exception $e) {
                $record->document_proof = $document_proof;
            }
            */
        }
        if ($address_proof) {
            $record->address_proof = $address_proof;
            /*
            try {
                $url = strtok($document_proof, '?');
                $pathinfo = pathinfo($url);
                $contents = file_get_contents($url);
                $name = 'address_proof/address_' . time() . '.' . $pathinfo['extension'];
                Storage::put($name, $contents);
                $record->address_proof = $name;
            } catch (Exception $e) {
                $record->address_proof = $address_proof;
            }
            */
        }
        $record->save();

        // Update Temp Record
        $temp->status = 'processed';
        $temp->save();

        $user = User::find($user_id);
        $user->kyc_verified_at = now();
        $user->save();
        if ($status == "approved") {
            $profile = Profile::where('user_id', $user_id);
            if ($profile) {
                $profile->status = 'approved';
                $profile->save();
            }
            return 'success';
        } else {
            return 'fail';
        }
    }
}
