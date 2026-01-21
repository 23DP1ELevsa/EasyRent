<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageMail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        Mail::to('eduards.levsa@gmail.com')->send(new ContactMessageMail($data));

        return response()->json(['status' => 'ok']);
    }
}
