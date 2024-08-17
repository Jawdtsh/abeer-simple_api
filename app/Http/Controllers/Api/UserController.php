<?php

namespace App\Http\Controllers\Api;

use App\Enums\TokenAbility;
use App\Events\newUserNotify;
use App\Events\UserEvent;
use App\Events\VerificationCodeEvent;
use App\Jobs\SendTwoFactorToken;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    public function signup(Request $request): \Illuminate\Http\JsonResponse
    {
//        dd('fhfh');
        try {
            //Validated
            $validateUser = Validator::make($request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email',
                    'password' => 'required',
                    'phone'=>'required',
                    'profile_picture' => 'nullable|image',
                    'certificate' => 'file|mimes:pdf,doc,docx'
                ]);

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validateUser->errors()->all()[0],
                    'data'=>[]
                ], 401);
            }else{
            if ($request->hasFile('profile_picture')){
                $file_name=time().'.'.$request->profile_picture->extension();
                $request->profile_picture->move(public_path('images'),$file_name);
                $profile_picture="public/images/$file_name";
            }

            if ($request->hasFile('certificate')) {
                $file_name = time() . '.' . $request->certificate->extension();
                $request->certificate->move(public_path('images'), $file_name);
                $certificate = "public/images/$file_name";
            }
                $user = new User();
                $user->name = $request->name;
                $user->email = $request->email;
                $user->phone = $request->phone;
                $user->password = Hash::make($request->password);
                $user->profile_picture=$profile_picture;
                $user->certificate=$certificate;
                $user->save();

//                Event::dispatch(new UserEvent($user));

                // verification Event
                event(new VerificationCodeEvent($user));

                $accessToken = $user->createToken('access_token', [TokenAbility::ACCESS_API->value], Carbon::now()->addMinutes(config('sanctum.ac_expiration')));
                $refreshToken = $user->createToken('refresh_token', [TokenAbility::ISSUE_ACCESS_TOKEN->value], Carbon::now()->addMinutes(config('sanctum.rt_expiration')));

                return response()->json([
                    'status' => true,
                    'message' => 'User Created Successfully',
                    'token' => $accessToken->plainTextToken,
                    'refresh_token' => $refreshToken->plainTextToken,
                ], 200);
            }



        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data'=>''
            ], 500);
        }
    }
    public function login(Request $request){
        try {
            $validateUser = Validator::make($request->all(),
                [
                    'email' => 'required|email',
                    'password' => 'required',
                    'phone'=>'required',
                    'device_name' => 'required',
                ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => $validateUser->errors()->all()[0],
                    'data'=>[]
                ], 401);
            }else{

                if(!Auth::attempt($request->only(['email', 'password','phone']))){
                    return response()->json([
                        'status' => false,
                        'message' => 'Email & Password & phone does not match with our record.',
                    ], 401);
                }
                $user = User::where('email', $request->email)->first();
                $token=$user->createToken($request->device_name)->plainTextToken;
                $user->token=$token;

                return response()->json([
                    'status' => true,
                    'message' => 'User Logged In Successfully',
                    'data' => $user,
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data'=>[],
            ], 500);
        }
    }
    public function getProfile(Request $request){
        try {
            $user_id=$request->user()->id;
            $user=User::find($user_id);
            return response()->json([
                'status' => true,
                'message' => 'User Profile',
                'data' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data'=>[]
            ], 500);
        }
    }
    public function updateProfile(Request $request){
        try {
            $validateUser = Validator::make($request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,id,'.$request->user()->id,
                    'phone'=>'required',
                    'profile_picture' => 'nullable|image',
                    'certificate' => 'file|mimes:pdf,doc,docx'
                ]);
            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validateUser->errors()->all()[0],
                    'data'=>[]
                ], 401);
            }else{
                $user=User::find($request->user()->id);
                $user->name=$request->name;
                $user->email=$request->email;
                $user->phone=$request->phone;
                if ($request->profile_picture && $request->profile_picture->isValid()){
                    $file_name=time().'.'.$request->file('profile_picture')->getClientOriginalName();
                    $path= $request->file('profile_picture')->storeAs('/',$file_name,'public');
                    $profile_picture="/storage/".$path;
                    $user->profile_picture=$profile_picture;
                }
                if ($request->certificate && $request->certificate->isValid()){
                    $file_name=time().'.'.$request->file('certificate')->getClientOriginalName();
                    $path= $request->file('certificate')->storeAs('/',$file_name,'public');
                    $certificate="/storage/".$path;
                    $user->certificate=$certificate;
                }
                $user->update();
                return response()->json([
                    'status' => true,
                    'message' => 'Profile Update',
                    'token' => $user,
                ], 200);
            }

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data'=>[]
            ], 500);
        }
    }
    public function logout(Request $request){
        try {
            $request->user()->currentAccessToken()->delete();
            return response()->json([
                'status' => true,
                'message' => 'Logout Successfully!',
                'data' => [],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data'=>[]
            ], 500);
        }
    }

    public function refreshToken(Request $request)
    {
        $accessToken = $request->user()->createToken('access_token', [TokenAbility::ACCESS_API->value], Carbon::now()->addMinutes(config('sanctum.ac_expiration')));
        return response(['message' => "Token generate", 'token' => $accessToken->plainTextToken]);
    }

    public function generatePath($file_type,$image , $user_name)
    {
        if($file_type=='image')
        {
            $folderName = 'profile';
            $folderPath = 'images/' . $folderName . '/users/' . $user_name ;

        }
        else if($file_type=='pdf')
        {
            $folderName = 'pdf';
            $folderPath = 'Files/' . $folderName . '/users/' . $user_name ;
            // $path = Storage::disk('public')->putFile($folderPath, $image);

        }

        return $folderPath;
    }
    /************************************************************************************/
    public function resendVerifyCode()
    {
        $cachedData = Cache::get(request()->ip()) ?? null;
        if($cachedData==null)
        {
            $cachedData = Cache::get('resend_code_' . request()->ip()) ?? null ;
        }
        // if($cachedData==null)
        // return $this->apiError(message: 'you are verification your email already', code: 404);
        // {
        //     return false;
        // }
        $retrievedEmail = $cachedData['email'];
        $user = User::whereEmail($retrievedEmail)->first();
        $user->resendVerificationCode();

    }
    /***************************************************************************************/
    public function confirmVerifyCode($request)
    {
        $user = Auth::user();
        $code = $request->verify_code;

        $cachedData = Cache::get($request->ip()) ;

        if ($cachedData) {
            $retrievedEmail = $cachedData['email'];
            $retrievedCode = $cachedData['v_code'];
            // return $retrievedCode;
            if ($code !== $retrievedCode) {
                return false;
            } else {
                $user->resetVerificationCode();
                Cache::forget(request()->ip());
                Cache::forget('resend_code_' . request()->ip());
                return true;
            }
        }

        return false;
    }



}
