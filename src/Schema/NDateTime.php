<?php

namespace Norm\Schema;

use DateTimeZone;
use DateTime;
use Norm\Type\DateTime as TypeDateTime;

class NDateTime extends NField
{
    protected function getTimeZone()
    {
        return $this->repository->getAttribute('timezone') ?: date_default_timezone_get();
    }

    public function execPrepare($value)
    {
        if (empty($value)) {
            return null;
        } elseif ($value instanceof TypeDateTime) {
            return $value;
        } elseif ($value instanceof DateTime) {
            $t = new TypeDateTime($value->format('c'));
            $t->setTimeZone(new DateTimeZone($this->getTimeZone()));
            return $t;
        } elseif (is_string($value)) {
            $original = date_default_timezone_get();
            date_default_timezone_set($this->getTimeZone());
            $t = date('c', strtotime($value));
            date_default_timezone_set($original);
            return new TypeDateTime($t);
        }

        $t = new TypeDateTime(date('c', (int) $value));
        $t->setTimeZone(new DateTimeZone($this->getTimeZone()));
        return $t;
    }
}