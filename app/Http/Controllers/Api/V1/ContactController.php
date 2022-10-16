<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactUsMail;
use App\Models\ContactRecipient;
use App\Models\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function submitContact(Request $request)
    {
        $user_id = null;
        if(auth()->user()) {
            $user_id = auth()->user()->id;
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $contactUs = new ContactUs();
        $contactUs->user_id = $user_id;
        $contactUs->name = $request->name;
        $contactUs->email = $request->email;
        $contactUs->message = $request->message;
        $contactUs->save();
        $contactRecipients = ContactRecipient::get();
        if (count($contactRecipients) > 0) {
            foreach ($contactRecipients as $item) {
                Mail::to($item->email)->send(new ContactUsMail($contactUs));
            }
        }
        return $this->metaSuccess();
    }

    public function getContactRecipients(Request $request)
    {
        $sort_key = $request->sort_key ?? 'created_at';
        $sort_direction = $request->sort_direction ?? 'desc';
        $contactRecipients = ContactRecipient::orderBy($sort_key, $sort_direction)->get();
        return $this->successResponse($contactRecipients);
    }

    public function addContactRecipients(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }
        $contactRecipient = ContactRecipient::where('email', $request->email)->first();
        if ($contactRecipient) {
            return $this->errorResponse('This email has already exist', Response::HTTP_BAD_REQUEST);
        } else {
            $contactRecipient = new ContactRecipient();
            $contactRecipient->email = $request->email;
            $contactRecipient->save();
            return $this->metaSuccess();
        }
    }

    public function deleteContactRecipients($id)
    {
        ContactRecipient::where('id', $id)->delete();
        return $this->metaSuccess();
    }
}
