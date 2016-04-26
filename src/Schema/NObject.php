<?php

namespace Norm\Schema;

use Norm\Type\Object as TypeObject;

class NObject extends NField
{
    public function prepare($value)
    {

        if (empty($value)) {
            return null;
        } elseif ($value instanceof TypeObject) {
            return $value;
        } elseif (is_string($value)) {
            $value = json_decode($value, true);
        }

        return new TypeObject($value);
    }

    protected function formatReadonly($value, $model = null)
    {
        return $this->render('__norm__/nobject/readonly', [
            'value' => $value,
            'self' => $this,
        ]);
    }

    protected function formatInput($value, $model = null)
    {
        return $this->render('__norm__/nobject/input', [
            'value' => $value,
            'self' => $this,
        ]);
    }
}