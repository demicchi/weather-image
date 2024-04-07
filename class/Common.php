<?php

namespace StudioDemmys\WeatherImage;

class Common
{
    protected function __construct()
    {
    }
    
    public static function getAbsolutePath(string $path): string
    {
        if (str_starts_with($path, "/") || preg_match( '@^[a-zA-Z]:(\\\\|/)@', $path )) {
            return $path;
        } else {
            return dirname(__FILE__) . "/../" . $path;
        }
    }
    
    public static function sanitizeUserInput(string $text): string
    {
        $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8");
        $text = preg_replace('/[\p{Cc}\p{Cf}\p{Z}]/u', '', $text);
        return $text;
    }
    
}