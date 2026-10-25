<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Mail van de kernel aan Lucas, via de relay van srv6, vanuit juno@lucasjohnston.nl. De ontvanger staat
 * hier vast en is niet in te stellen: dit endpoint kan dus nooit als open relay dienen.
 */
class MailController extends Controller
{
    private const RECIPIENT = 'lrjohnston@protonmail.com';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'html' => ['required', 'string', 'max:500000'],
        ]);

        Mail::html($data['html'], fn ($message) => $message->to(self::RECIPIENT)->subject($data['subject']));

        return response()->json(['sent_to' => self::RECIPIENT], 201);
    }
}
