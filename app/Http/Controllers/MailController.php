<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\MailLucas;

/**
 * Mail van de kernel aan Lucas (MailLucas). De ontvanger is niet in te stellen: dit endpoint kan dus nooit
 * als open relay dienen.
 */
class MailController extends Controller
{

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'html' => ['required', 'string', 'max:500000'],
        ]);

        MailLucas::send($data['subject'], $data['html']);

        return response()->json(['sent_to' => MailLucas::RECIPIENT], 201);
    }
}
