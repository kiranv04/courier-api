<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_number',
        'name',
        'email',
        'phone',
        'password',
        'must_change_password',
        'yield_ratio_door',
        'yield_ratio_warehouse',
        'owner_type',
        'owner_id',
        'rel_id',
        'rel_type',
        'is_active',
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

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->employee_number)) {
                $user->employee_number = $user->generateEmployeeNumber();
            }
        });
    }

    public function generateEmployeeNumber(): string
    {
        $prefix = CompanySettings::current()->company_abbreviation;

        return DB::transaction(function () use ($prefix) {
            $lastNumber = static::where('employee_number', 'like', "{$prefix}-EMP-%")
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('employee_number');

            $nextSeq = 1;
            if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
                $nextSeq = (int) $matches[1] + 1;
            }

            return sprintf('%s-EMP-%04d', $prefix, $nextSeq);
        });
    }
}
