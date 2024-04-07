<?php

namespace StudioDemmys\WeatherImage\DataSource;

use StudioDemmys\WeatherImage\Config;

class Date implements DataSourceInterface
{
    public function __construct()
    {

    }
    
    public function getText(string $type, ?array $parameter, ?\DateTimeInterface $now): ?string
    {
        if (empty($parameter["format"]))
            return null;
        
        $timezone = new \DateTimeZone(Config::getConfigOrSetIfUndefined("data_source/jma/timezone", "+09:00"));
        if (is_null($now))
            $now = new \DateTimeImmutable(
                "now",
                $timezone
            );
        else
            $now = \DateTimeImmutable::createFromInterface($now);
        
        $target_date = match (strtolower($parameter["target_date"])) {
            "today" => $now->modify("today 00:00"),
            "tomorrow" => $now->modify("tomorrow 00:00"),
        };
        
        if (empty($parameter["locale"])) {
            $print_text = $target_date->format($parameter["format"]);
        } else {
            $formatter = new \IntlDateFormatter(
                $parameter["locale"],
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $timezone,
                \IntlDateFormatter::TRADITIONAL
            );
            $formatter->setPattern($parameter["format"]);
            $print_text = $formatter->format($target_date);
        }
        return $print_text;
    }
    
    public function getImage(string $type, ?array $parameter, ?\DateTimeInterface $now): ?\GdImage
    {
        return null;
    }
}