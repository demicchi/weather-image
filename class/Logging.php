<?php

namespace StudioDemmys\WeatherImage;


class Logging
{
    protected function __construct()
    {
    }
    
    public static function getLogFile()
    {
        return Common::getAbsolutePath(Config::getConfig("logging/file"));
    }
    
    public static function generateMessage($message, $error_level, $caller = null): string
    {
        $unique_id = "";
        if (defined("WEATHERIMAGE_UNIQUE_ID"))
            $unique_id = " " . WEATHERIMAGE_UNIQUE_ID;
        if (is_null($caller)) {
            return date("Y/m/d H:i:s") . $unique_id . '[' . $error_level . ']' . addslashes($message) . PHP_EOL;
        } else {
            return date("Y/m/d H:i:s") . $unique_id . '[' . $error_level . '](' . $caller . ')' . addslashes($message) . PHP_EOL;
        }
    }
    
    public static function error($message, bool $from_exception = false): void
    {
        $log_level = self::getLogLevel();
        $caller = ($log_level->value >= ErrorLevel::Debug->value) ? self::getCaller($from_exception) : null;
        if ($log_level->value >= ErrorLevel::Error->value)
            error_log(self::generateMessage($message, 'error', $caller), 3, self::getLogFile());
    }
    
    public static function warn($message, bool $from_exception = false): void
    {
        $log_level = self::getLogLevel();
        $caller = ($log_level->value >= ErrorLevel::Debug->value) ? self::getCaller($from_exception) : null;
        if ($log_level->value >= ErrorLevel::Warn->value)
            error_log(self::generateMessage($message, 'warn', $caller), 3, self::getLogFile());
    }
    
    public static function info($message, bool $from_exception = false): void
    {
        $log_level = self::getLogLevel();
        $caller = ($log_level->value >= ErrorLevel::Debug->value) ? self::getCaller($from_exception) : null;
        if ($log_level->value >= ErrorLevel::Info->value)
            error_log(self::generateMessage($message, 'info', $caller), 3, self::getLogFile());
    }
    
    public static function debug($message, bool $from_exception = false): void
    {
        if (self::getLogLevel()->value >= ErrorLevel::Debug->value) {
            $caller = self::getCaller($from_exception);
            error_log(self::generateMessage($message, 'debug', $caller), 3, self::getLogFile());
        }
    }
    
    public static function getLogLevel(): ErrorLevel
    {
        $log_level_str = Config::getConfig("logging/level");
        $log_level = ErrorLevel::getErrorLevel($log_level_str);
        if (is_null($log_level))
            throw new \Exception("[FATAL] The logging level (" . $log_level_str . ") is invalid.");
        return $log_level;
    }
    
    public static function getCaller(bool $from_exception = false): string
    {
        $trace_depth = ($from_exception) ? 3 : 2;
        $exception = ($from_exception) ? "Exception|" : "";
        $backtrace = debug_backtrace(null, $trace_depth + 1);
        if (count($backtrace) >= $trace_depth + 1) {
            $class = $backtrace[$trace_depth]["class"] ?? "__base";
            $caller = $class . '::' . $backtrace[$trace_depth]["function"];
        } else {
            $caller = "__base";
        }
        $caller .= ' ' . $backtrace[$trace_depth - 1]["file"] . ':' . $backtrace[$trace_depth - 1]["line"];
        return $exception . $caller;
    }
    
}