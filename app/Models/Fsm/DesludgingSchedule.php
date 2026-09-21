<?php

namespace App\Models\Fsm;

use Illuminate\Database\Eloquent\Model;

class DesludgingSchedule extends Model
{
    protected $table = 'fsm.desludging_schedule_temp';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'service_provider_id' => 'integer',
        'ward' => 'integer',
        'fstp_distance' => 'decimal:2',
        'next_emptying_date' => 'date:Y-m-d',
        'priority' => 'integer',
        'sequence' => 'integer',
        'status' => 'integer',
        'generated_by' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function serviceProvider()
    {
        return $this->belongsTo(
            ServiceProvider::class,
            'service_provider_id'
        );
    }

    public function containment()
    {
        return $this->belongsTo(
            Containment::class,
            'containment_id'
        );
    }

    public function generatedBy()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'generated_by'
        );
    }
}