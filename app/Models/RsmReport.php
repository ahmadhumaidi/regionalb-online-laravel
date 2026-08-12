<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RsmReport extends Model
{
    protected $table = 'rsm_reports';

    public const TYPE_MARKETING = 'marketing';
    public const TYPE_ADS = 'ads';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'area',
        'report_type',
        'report_date',
        'user_id',
        'partner_campus_id',
        'wilayah',
        'unit_name',
        'staff_name',
        'created_by_name',
        'created_by_role',
        'status',
        'title',
        'activity_kind',
        'location_name',
        'target_text',
        'result_text',
        'leads_count',
        'closing_count',
        'notes',
        'platform',
        'ad_period',
        'campaign_name',
        'ad_goal',
        'budget_requested',
        'budget_approved',
        'realization_amount',
        'impressions_count',
        'cpl',
        'cpm',
        'campaign_link',
        'category',
        'obstacle_text',
        'follow_up_text',
        'leader_follow_up_text',
        'attachment_path',
        'insight_attachment_path',
        'revision_note',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'budget_requested' => 'decimal:2',
            'budget_approved' => 'decimal:2',
            'realization_amount' => 'decimal:2',
            'impressions_count' => 'integer',
            'cpl' => 'decimal:2',
            'cpm' => 'decimal:2',
        ];
    }

    public function adLeads(): HasMany
    {
        return $this->hasMany(RsmAdLead::class, 'report_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RsmActivityLog::class, 'report_id');
    }
}
