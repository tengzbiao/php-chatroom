<?php

class User
{
    public $socket;
    public $id;
    public $nickname;
    public $portrait;

    public function __construct($id, $socket)
    {
        $this->id = $id;
        $this->socket = $socket;
    }
}