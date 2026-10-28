<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case StatusChange = 'status_change';
    case Login = 'login';
    case Logout = 'logout';

    public function label(): string
    {
        return __("activity.actions.{$this->value}");
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Create => 'green',
            self::Update => 'blue',
            self::Delete => 'red',
            self::StatusChange => 'yellow',
            self::Login, self::Logout => 'gray',
        };
    }
}
