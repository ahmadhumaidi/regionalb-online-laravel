<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmCollabReportArchive extends Model
{
    protected $table = 'rsm_collab_report_archive';

    public const CREATED_AT = 'archived_at';

    protected $fillable = [
        'report_name',
        'report_month',
        'payload',
    ];
}
