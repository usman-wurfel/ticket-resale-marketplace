<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventLocation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function zones()
    {
        return $this->hasMany(EventZone::class, 'location_id');
    }
}
