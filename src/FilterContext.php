<?php

namespace Norm;

use Norm\Schema\NField;

class FilterContext
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Model
     */
    protected $row;

    /**
     * @var NField
     */
    protected $field;

    public function __construct(Session $session, Model $row, NField $field)
    {
        $this->session = $session;
        $this->row = $row;
        $this->field = $field;
    }
}
