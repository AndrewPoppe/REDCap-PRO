<?php

namespace YaleREDCap\REDCapPRO;

class Participant
{
    public $module;
    public $users = [];

    function __construct($module)
    {
        $this->module = $module;
    }

    function addUser(User $user)
    {
        array_push($this->users, $user->name);
    }
}
