<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmCoordinatorSchedule extends Model
{
    protected $table = 'rsm_coordinator_schedules';

    protected $fillable = [
        'area',
        'schedule_date',
        'koordinator_user_id',
        'koordinator_name',
        'koordinator_home',
        'wilayah',
        'unit_name',
        'visit_type',
        'agenda',
        'status',
        'result_text',
        'next_action',
        'attachment_path',
        'checklist_json',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
        ];
    }
}
