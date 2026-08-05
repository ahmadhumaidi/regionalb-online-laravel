<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmDeletedUsername extends Model
{
    protected $table = 'rsm_deleted_usernames';

    protected $primaryKey = 'username';
    public $incrementing = false;
    protected $keyType = 'string';

    public const UPDATED_AT = null;
    public const CREATED_AT = 'deleted_at';

    protected $fillable = [
        'username',
        'deleted_name',
        'deleted_role',
        'deleted_by_user_id',
    ];
}
