<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\emailMailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;


class EmailController extends Controller
{
    public function send(){
        $email = 'abeerosami1996@gmail.com';
        $verificationCode = strtoupper(substr(md5(rand()), 0, 6));
//// Generate verification code and timestamp
//        $verificationCode = strtoupper(substr(md5(rand()), 0, 6));
//        $timestamp = time(); // Store this in your database
//
//// During validation
//        $userEnteredCode = 'AB12CD'; // Replace with actual user input
//        $storedTimestamp = 1678451234; // Retrieve from your database
//        $currentTimestamp = time();
//
//        $elapsedTime = $currentTimestamp - $storedTimestamp;
//        $validityWindow = 10 * 60; // 10 minutes in seconds
//
//        if ($elapsedTime <= $validityWindow && $userEnteredCode === $verificationCode) {
//            // Code is valid
//            // Proceed with email verification
//        } else {
//            // Code is expired or incorrect
//            // Prompt the user to request a new code
//        }

        Mail::to($email)->send(new emailMailable());

        return response()->json([
            'message' => 'Email has been sent.'
        ], Response::HTTP_OK);
    }

}
