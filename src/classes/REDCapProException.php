<?php

namespace YaleREDCap\REDCapPRO;

class REDCapProException extends \Exception
{
    public $rcpro = NULL;
    public function __construct($rcpro = NULL)
    {
        $this->rcpro = $rcpro;
    }
}
