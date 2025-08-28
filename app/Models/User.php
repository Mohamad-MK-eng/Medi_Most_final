<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Traits\HandlesFiles;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 *
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property int $role_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \App\Models\Doctor|null $doctor
 * @property-read string $full_name
 * @property-read string $profile_url
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Patient|null $patient
 * @property-read \App\Models\Role $role
 * @property-read \App\Models\Secretary|null $secretary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passport\Token> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;
    use HandlesFiles;
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // هون عدلت
   protected $fillable = [
    'first_name',
    'last_name',
    'email',
    'password',
    'role_id',
    'gender',
    'profile_picture',
    'phone',
    'address',
];
// phone and address addition
protected $dates =['deleted_at'];


    protected $fileHandlingConfig = [
        'profile_picture' => [
            'directory' => 'user_profile_pictures',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size' => 3072, // 3MB
            'default' => 'default-user.jpg'
        ]
    ];


    protected $casts = [
        'phone',
        'role_id'
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $role): bool
    {
        return optional($this->role)->name === $role;
    }


    public function admin()
{
    return $this->hasOne(Admin::class);
}




    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function doctor()
    {
        return $this->hasOne(Doctor::class);
    }



    public function secretary()
    {
        return $this->hasOne(Secretary::class);
    }



    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }



    //public function hasRole(string $role):bool {
    //  return $this->role()->where('first_name',$role)->exists();
    //}
public function payments()
{
    if (!$this->relationLoaded('patient')) {
        $this->load('patient');
    }

    if (!$this->patient) {
        return null;
    }

    return $this->patient->payments();
}



    public function hasPermission(string $permission): bool
    {

        return $this->role()->whereHas('permissions', fn($q) => $q->where('permissions', $permission))->exists();
    }



    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }


    public function  getProfileUrlAttribute(): string
    {
        return route('profile.show', $this->id);
    }





    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable');
    }


public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

}






