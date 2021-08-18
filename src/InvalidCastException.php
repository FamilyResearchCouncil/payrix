<?php namespace Frc\Payrix;

use RuntimeException;

class InvalidCastException extends RuntimeException
{
    /**
     * The name of the column.
     *
     * @var string
     */
    public $column;

    /**
     * The name of the cast type.
     *
     * @var string
     */
    public $castType;

    /**
     * Create a new exception instance.
     *
     * @param  string  $column
     * @param  string  $castType
     * @return static
     */
    public function __construct($column, $castType)
    {
        parent::__construct("Call to undefined cast [{$castType}] on column [{$column}].");

        $this->column = $column;
        $this->castType = $castType;
    }
}
