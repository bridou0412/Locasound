<?php
declare(strict_types=1);

namespace Robert2\API\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Robert2\API\Contracts\Serializable;
use Robert2\API\Models\Traits\Serializer;
use Robert2\API\Validation\Validator as V;

class EventTechnician extends BaseModel implements Serializable
{
    use Serializer;

    public $timestamps = false;

    protected $withoutAlreadyBusyChecks = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->validation = [
            'start_time' => V::callback([$this, 'checkDates']),
            'end_time' => V::callback([$this, 'checkDates']),
            'position' => V::optional(V::length(2, 191)),
        ];
    }

    public function checkDates()
    {
        $dateChecker = V::notEmpty()->date();
        if (!$dateChecker->validate($this->start_time)) {
            return false;
        }

        if (!$dateChecker->validate($this->end_time)) {
            return false;
        }

        $start = new \DateTime($this->start_time);
        $end = new \DateTime($this->end_time);

        if ($start > $end) {
            return 'end-date-must-be-later';
        }

        $eventStart = new \DateTime($this->event->start_date);
        $eventEnd = new \DateTime($this->event->end_date);
        if ($start < $eventStart || $end < $eventStart) {
            return 'technician-assignation-before-event';
        }
        if ($start > $eventEnd || $end > $eventEnd) {
            return 'technician-assignation-after-event';
        }

        $precision = [0, 15, 30, 45];
        $startMinutes = (int)$start->format('i');
        $endMinutes = (int)$end->format('i');
        if (!in_array($startMinutes, $precision) || !in_array($endMinutes, $precision)) {
            return 'date-precision-must-be-quarter';
        }

        if ($this->withoutAlreadyBusyChecks) {
            return true;
        }

        $technicianHasOtherEvents = static::where('id', '!=', $this->id)
            ->where('technician_id', $this->technician_id)
            ->where([
                ['end_time', '>=', $this->start_time],
                ['start_time', '<=', $this->end_time],
            ])
            ->whereHas('event', function (Builder $query) {
                $query->where('deleted_at', null);
            })
            ->exists();
        if ($technicianHasOtherEvents) {
            return 'technician-already-busy-for-this-period';
        }

        return true;
    }

    // ------------------------------------------------------
    // -
    // -    Relations
    // -
    // ------------------------------------------------------

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function technician()
    {
        return $this->belongsTo(Technician::class, 'technician_id')
            ->withTrashed();
    }

    // ------------------------------------------------------
    // -
    // -    Mutators
    // -
    // ------------------------------------------------------

    protected $appends = [
        'technician',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'technician_id' => 'integer',
        'start_time' => 'string',
        'end_time' => 'string',
        'position' => 'string',
    ];

    public function getTechnicianAttribute()
    {
        return $this->getRelationValue('technician');
    }

    public function getEventAttribute()
    {
        return $this->getRelationValue('event');
    }

    // ------------------------------------------------------
    // -
    // -    Setters
    // -
    // ------------------------------------------------------

    protected $fillable = [
        'event_id',
        'technician_id',
        'start_time',
        'end_time',
        'position',
    ];

    public function setPositionAttribute($value)
    {
        $value = is_string($value) ? trim($value) : $value;
        $this->attributes['position'] = $value;
    }

    public static function flushForEvent(int $eventId)
    {
        static::where('event_id', $eventId)->delete();
    }

    /**
     * Permet d'ignorer la validation qui v??rifie le chevauchement avec les dates des autres assignations.
     * Utile quand on est certain que les autres assignations vont ??tre supprim??es avant le save
     * (voir static::flushForEvent). ?? cha??ner avec un ->validate().
     */
    public function withoutAlreadyBusyChecks(): self
    {
        $this->withoutAlreadyBusyChecks = true;
        return $this;
    }

    // ------------------------------------------------------
    // -
    // -    "Repository" methods
    // -
    // ------------------------------------------------------

    /**
     * @param Collection|EventTechnician[] $eventTechnicians
     * @param \DateTime $prevStartDate
     * @param array $newEventData
     *
     * @return array
     */
    public static function getForNewDates(
        Collection $eventTechnicians,
        \DateTime $prevStartDate,
        array $newEventData
    ): array {
        $newStartDate = new \DateTime($newEventData['start_date']);
        $newEndDate = new \DateTime($newEventData['end_date']);
        $offsetInterval = $prevStartDate->diff($newStartDate);

        $technicians = [];
        foreach ($eventTechnicians as $eventTechnician) {
            $technicianStartTime = roundDate(
                (new \DateTime($eventTechnician->start_time))->add($offsetInterval)
            );
            $technicianEndTime = roundDate(
                (new \DateTime($eventTechnician->end_time))->add($offsetInterval)
            );

            if ($technicianStartTime > $newEndDate) {
                continue;
            }
            if ($technicianEndTime < $newStartDate) {
                continue;
            }
            if ($technicianStartTime < $newStartDate) {
                $technicianStartTime = $newStartDate;
            }
            if ($technicianEndTime >= $newEndDate) {
                $technicianEndTime = clone($newEndDate)->setTime(23, 45, 0);
            }

            $technicians[] = [
                'id' => $eventTechnician->technician_id,
                'start_time' => $technicianStartTime->format('Y-m-d H:i:s'),
                'end_time' => $technicianEndTime->format('Y-m-d H:i:s'),
                'position' => $eventTechnician->position,
            ];
        }

        return $technicians;
    }

    public static function staticRemove($id, array $options = []): ?BaseModel
    {
        if (!static::findOrFail($id)->delete()) {
            throw new \RuntimeException(sprintf("Unable to delete the event technician %d.", $id));
        }
        return null;
    }
}
