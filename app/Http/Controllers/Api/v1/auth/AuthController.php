<?php

namespace App\Http\Controllers\Api\v1\auth;

use App\Enums\TokenAbility;
use App\Events\UserEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateRequest;
use App\Http\Services\Auth\CreateUserService;
use App\Http\Services\Auth\UpdateUserService;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Traits\ApiResponse;
use App\Traits\UploadedFile;
use App\Traits\UploadedFileStorage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse;
    public CreateUserService $createUser;
    public UpdateUserService $updateService;

    public function __construct(CreateUserService $createUser,UpdateUserService $updateService)
    {
        $this->createUser=$createUser;
        $this->updateService=$updateService;
    }

    public function signup(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
            $user=$this->createUser->storeUser($request);
            UserEvent::dispatch($user);


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
    }

    public function login(LoginRequest $request){
        try {
            if(!Auth::attempt($request->only(['email', 'password','phone']))){
                throw new AuthenticationException('Username or password is invalid.');
            }

            $user = User::where('email', $request->email)->first();
            $user->generateTwoFactorCode();
            $user->notify(new TwoFactorCode());
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
            return $this->success($user,'User Profile',200);

        }catch (\Throwable $th) {
            return $this->error($th->getMessage(),500);}
    }

    public function updateProfile(UpdateRequest $request){
        try {
            $user=$this->updateService->updateUser($request);
            return $this->success($user,'update user',200);
        }catch (\Throwable $th) {
            return $this->error($th->getMessage(),500);}
    }

    public function logout(Request $request){
        try {
            $request->user()->currentAccessToken()->delete();
            return $this->success([],'Logout Successful',200);  }catch (\Throwable $th) {
            return $this->error($th->getMessage(),500);}
    }

    public function refreshToken(Request $request)
    {
        $accessToken = $request->user()->createToken('access_token', [TokenAbility::ACCESS_API->value], Carbon::now()->addMinutes(config('sanctum.ac_expiration')));
      return $this->success($accessToken->plainTextToken,"Token generate",200);
//        return response(['message' => "Token generate", 'token' => $accessToken->plainTextToken]);
    }
    public function confirmCode(LoginRequest $request){
        $user=auth()->user();
        if ($request->input('two_factor_code')==$request->two_factor_code){
            $user->resetTwoFactorCode();
            return $this->success([],'Login Successful',200);
        }
        return $this->error('the two factor is error',401);
    }
}
