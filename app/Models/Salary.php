<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $secretary_id
 * @property int $salary_setting_id
 * @property string $base_amount
 * @property string $bonus_amount
 * @property string $total_amount
 * @property string $status
 * @property string|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SalarySetting|null $salarysetting
 * @property-read \App\Models\Secretary $secretary
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereBaseAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereBonusAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereSalarySettingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereSecretaryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Salary whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Salary extends Model
{
    protected $fillable = [
        'secretary_id',
        'salary_setting_id',
        'base_amount',
        'bonus_amount',
        'total_amount',
        'status',
        'paid_at'


    ];


    protected $casts = [


        'secretary_id',
        'salary_setting_id',
        'base_amount',
        'bonus_amount',
        'total_amount',
        'status',
        'paid_at'


    ];


    public function secretary()
    {

        return $this->belongsTo(Secretary::class);
    }




    public function salarysetting()
    {
        return $this->belongsTo(SalarySetting::class);
    }
}
