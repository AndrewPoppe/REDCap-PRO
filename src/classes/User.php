<?php

namespace YaleREDCap\REDCapPRO;

class User
{
    public $name;

    function __construct($params)
    {

        if (isset($params["name"])) {
            echo "name";
            var_dump($module);
            $this->name = $params["name"];
        }

        if (isset($params["age"])) {
            echo "age";
            $this->name = $params["age"];
        }

        if (isset($params["profession"])) {
            echo "profession";
            $this->name = $params["profession"];
        }
    }

    function getName()
    {
        return $this->name;
    }
}
