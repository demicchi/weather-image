<?php

namespace StudioDemmys\WeatherImage;

enum ErrorLevel :int
{
    case Error = 1;
    case Warn = 3;
    case Info = 5;
    case Debug = 7;
    
    public static function getErrorLevel(string $level): ?ErrorLevel
    {
        foreach (self::cases() as $case) {
            if (strtolower($level) == strtolower($case->name))
                return $case;
        }
        return null;
    }
}
