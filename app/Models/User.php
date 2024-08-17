<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'certificate',
        'profile_picture',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'c_password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    protected $dates = [
        'updated_at',
        'created_at',
        'email_verified_at',
        'two_factor_expires_at',
    ];


    public function generateVerificationCode()
    {
        $verificationCode = $this->generateCode();
        Cache::remember(request()->ip(), 60*3, function () use ($verificationCode) {
            return [
                'email'=>$this->email,
                'v_code'=>$verificationCode,
            ];
        });
        Cache::forever('resend_code_' . request()->ip(), [
            'email' => $this->email,
        ]);
        return $verificationCode;
    }

    /***********************************************/
    public function generateCode()
    {
        $characters = '0123456789ABCDEYZab0123456789cdefghijk0123456789';
        $verificationCode = '';
        for ($i = 0; $i < 6; $i++) {
            $verificationCode .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $verificationCode;
    }

    /***********************************************/

    public  function resetVerificationCode()
    {
        $this->email_verified_at = now();
        $this->save();
    }

    /*************************************************/
    public function resendVerificationCode()
    {
        $verificationCode = $this->generateVerificationCode();
        $minutesRemaining = 3;
        $this->notify(new VereficationCodeNotification($verificationCode, $minutesRemaining));
    }



    /**
     * Generate 6 digits MFA code for the User
     */
    public function generateTwoFactorCode()
    {
        $this->timestamps = false; //Dont update the 'updated_at' field yet

        $this->two_factor_code = rand(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    /**
     * Reset the MFA code generated earlier
     */
    public function resetTwoFactorCode()
    {
        $this->timestamps = false; //Dont update the 'updated_at' field yet

        $this->two_factor_code = '';
        $this->two_factor_expires_at = now();
        $this->save();
    }
}


