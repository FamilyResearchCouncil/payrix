<?php namespace Frc\Payrix\Models;

use Frc\Payrix\Models\Concerns\HasAttributes;

class Report extends Resource
{
    protected array $appends = [
//        'login',
//        'type',
//        'status',
//        'schedule',
//        'scheduleFactor',
    ];

    /*******************************************************
     * attributes
     ******************************************************/

    /**
     * Accessor for $this->login
     **/
    public function getLoginAttribute($value)
    {
        return $value ?? $this->getLogin()->id;
    }

    /**
     * Accessor for $this->type
     **/
    public function getTypeAttribute($value)
    {
        return $value ?? 'json';
    }

    /**
     * Accessor for $this->status
     **/
    public function getStatusAttribute($value)
    {
        return $value ?? 'ready';
    }

    /**
     * Accessor for $this->schedule
     **/
    public function getScheduleAttribute($value)
    {
        return $value ?? 'unscheduled';
    }
    
    /**
     * Accessor for $this->scheduleFactor
     **/
    public function getScheduleFactorAttribute($value)
    {
        return $value ?? '1';
    }
    

}