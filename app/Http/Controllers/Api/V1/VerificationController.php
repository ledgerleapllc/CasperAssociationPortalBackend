<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentFile;
use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    public function submitNode(Request $request) {     
        // Validator
        $validator1 = Validator::make($request->all(), [
            'type' => 'required|in:entity,individual'
        ]);
        if ($validator1->fails()) {
            return $this->validateResponse($validator1->errors());
        }
        $type = $request->type;
        $user = auth()->user();
        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile();
            $profile->user_id = $user->id;
        }
        $profile->type = $type;

        if ($type == 'entity') {
            $validator2 = Validator::make($request->all(), [
                'entity_name' => 'required',
                'entity_type' => 'required',
                'entity_registration_number' => 'required',
                'entity_registration_country' => 'required',
                'vat_number' => 'required'
            ]);
            if ($validator2->fails()) {
                return $this->validateResponse($validator2->errors());
            }

            $profile->entity_name = $request->entity_name;
            $profile->entity_type = $request->entity_type;
            $profile->entity_registration_number = $request->entity_registration_number;
            $profile->entity_registration_country = $request->entity_registration_country;
            $profile->vat_number = $request->vat_number;
        } else {
            $profile->entity_name = null;
            $profile->entity_type = null;
            $profile->entity_registration_number = null;
            $profile->entity_registration_country = null;
            $profile->vat_number = null;
        }
        $userData = User::where('id', $user->id)->first();
        $userData->type = $type;
        $userData->save();
        $profile->save();
        return $this->metaSuccess();
    }

    public function submitDetail(Request $request) {     
        // Validator
        $validator1 = Validator::make($request->all(), [
            'type' => 'required|in:entity,individual',
            'first_name' => 'required',
            'last_name' => 'required',
            'country_citizenship' => 'required',
            // 'page_number' => 'required|integer',
            'dob' => 'required',
        ]);
        
        if ($validator1->fails()) {
            return $this->validateResponse($validator1->errors());
        }
        $user = auth()->user();
        $type = $request->type;
        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            return $this->errorResponse(__('Pofile not exist'), Response::HTTP_BAD_REQUEST);
        }
        $profile->first_name = $request->first_name;
        $profile->last_name = $request->last_name;
        $profile->country_citizenship = $request->country_citizenship;
        $profile->dob = Carbon::parse($request->dob)->format('Y-m-d');
        if ($type == 'entity') {
            $profile->page_is_representative = $request->page_is_representative?? '';
            $profile->page_number = $request->page_number;
        }
        $userData = User::where('id', $user->id)->first();
        $userData->first_name = $request->first_name;
        $userData->last_name = $request->last_name;
        $userData->save();
        $profile->save();

        return $this->metaSuccess();
    }

    public function uploadDocument(Request $request) {     
        try {
            // Validator
            $validator = Validator::make($request->all(), [
                'files' => 'array',
                'files.*' => 'file|max:100000|mimes:pdf,docx,doc,txt,rtf'
            ]);
            if ($validator->fails()) {
                return $this->validateResponse($validator->errors());
            }
            $user = auth()->user();
            if ($request->hasFile('files')) {
                $files = $request->file('files');
                foreach ($files as $file) {
                    $name = $file->getClientOriginalName();
                    $folder = 'document/' . $user->id;
                    $path = $file->storeAs($folder, $name);
                    $url = Storage::url($path);
                    $documentFile = DocumentFile::where('user_id', $user->id)->where('name', $name)->first();
                    if (!$documentFile) {
                        $documentFile = new DocumentFile();
                        $documentFile->user_id = $user->id;
                        $documentFile->name = $name;
                        $documentFile->path = $path;
                        $documentFile->url = $url;
                        $documentFile->save();
                    }
                }
            }
            $response = DocumentFile::where('user_id', $user->id)->get();
            return $this->successResponse($response);
        } catch (\Exception $ex) {
            return $this->errorResponse(__('Failed upload file'), Response::HTTP_BAD_REQUEST, $ex->getMessage());
        }
    }

    public function removeDocument($id) {  
        $user = auth()->user();
        $documentFile = DocumentFile::where('user_id', $user->id)->where('id', $id)->first();
        if ($documentFile) {
            Storage::delete($documentFile->path);
            $documentFile->delete();
        }
        $response = DocumentFile::where('user_id', $user->id)->get();
        return $this->successResponse($response);
    }
}
