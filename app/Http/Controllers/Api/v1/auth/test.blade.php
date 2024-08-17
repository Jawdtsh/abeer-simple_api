<?php

namespace App\Http\Controllers\Api\v1\auth;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Services\Auth\CreateUserService;
use App\Models\User;
use App\Traits\ApiResponse;
use App\Traits\UploadedFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse,UploadedFileStorage;
    public CreateUserService $createUser;

    public function __construct(CreateUserService $createUser)
    {
        $this->createUser=$createUser;
    }
    public function signup(RegisterRequest $request)
    {
        try {
            $user=$this->createUser->storeUser($request);
//         UserEvent::dispatch($user);
            $accessToken = $user->createToken('access_token', [TokenAbility::ACCESS_API->value], Carbon::now()->addMinutes(config('sanctum.ac_expiration')));
            $refreshToken = $user->createToken('refresh_token', [TokenAbility::ISSUE_ACCESS_TOKEN->value], Carbon::now()->addMinutes(config('sanctum.rt_expiration')));
            return $this->success(
                [
                    'token' => $accessToken->plainTextToken,
                    'refresh_token' => $refreshToken->plainTextToken,
                ]
                ,'User Created Successfully.',
                200
            );
        }catch (\Throwable $th) {
            return $this->error($th->getMessage(),500);}
    }
    public function login(LoginRequest $request){
        try {
            if(!Auth::attempt($request->only(['email', 'password','phone']))){
                return $this->error("This email is not associated with any user", 403);
            }

            $user = User::where('email', $request->email)->first();
            $token=$user->createToken($request->device_name)->plainTextToken;
            $user->token=$token;

            return $this->success($user ,"User Logged In Successfully", 200);
        }catch (\Throwable $th) {
            return $this->error($th->getMessage(),500);}
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

}
