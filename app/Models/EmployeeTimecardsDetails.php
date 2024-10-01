<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTimecardsDetails extends Model
{
    use HasFactory;

    protected $table = 'employee_timecard_details';

    protected $fillable = [
        'id',
        'user_id'
    ];
}
