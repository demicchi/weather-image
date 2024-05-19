<?php

namespace StudioDemmys\WeatherImage\DataSource;

use StudioDemmys\WeatherImage\Common;
use StudioDemmys\WeatherImage\Config;

class Jma implements DataSourceInterface
{
    const FORECAST_TYPE_DAYS_WEATHER = 0;
    const FORECAST_TYPE_DAYS_PROBABILITY_OF_PRECIPITATION = 1;
    const FORECAST_TYPE_DAYS_TEMPERATURE = 2;
    
    const FORECAST_TYPE_WEEK_PROBABILITY_OF_PRECIPITATION = 0;
    const FORECAST_TYPE_WEEK_TEMPERATURE = 1;
    
    const FORECAST_CONTENT_WEATHER_CODE = "weatherCodes";
    const FORECAST_CONTENT_WEATHER_DETAIL = "weathers";
    const FORECAST_CONTENT_WIND = "winds";
    const FORECAST_CONTENT_WAVE = "waves";
    const FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION = "pops";
    const FORECAST_CONTENT_TEMPERATURE = "temps";
    const FORECAST_CONTENT_TEMPERATURE_MINIMUM = "tempsMin";
    const FORECAST_CONTENT_TEMPERATURE_MAXIMUM = "tempsMax";
    
    protected array $forecast_data;
    protected \DateTimeImmutable $now;
    protected array $cache_data;
    protected ?int $previous_timestamp;
    
    public function __construct(?\DateTimeInterface $now = null, ?int $cache_timestamp = null)
    {
        if (is_null($now))
            $this->now = new \DateTimeImmutable(
                "now",
                new \DateTimeZone(Config::getConfigOrSetIfUndefined("data_source/jma/timezone", "+09:00"))
            );
        else
            $this->now = \DateTimeImmutable::createFromInterface($now);
        
        $cache_updated = false;
        $cache_file_exists = file_exists(Config::getConfig("data_source/jma/cache/file"));
        
        if ($cache_file_exists) {
            $this->cache_data = json_decode(
                file_get_contents(Config::getConfig("data_source/jma/cache/file")),
                null,
                512,
                JSON_BIGINT_AS_STRING | JSON_INVALID_UTF8_IGNORE | JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR
            );
            
            $expiry_timestamp = intval($cache_data["cached_timestamp"] ?? 0)
                + Config::getConfigOrSetIfUndefined("data_source/jma/cache/lifetime", 0);
            
            if ($expiry_timestamp < $this->now->getTimestamp()) {
                $this->forecast_data = json_decode(
                    file_get_contents(Config::getConfig("data_source/jma/forecast_url")),
                    null,
                    512,
                    JSON_BIGINT_AS_STRING | JSON_INVALID_UTF8_IGNORE | JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR
                );
                $this->cache_data["cached_timestamp"] = $this->now->getTimestamp();
                $this->cache_data["cache"][$this->getReportDateTime()->getTimestamp()] = $this->forecast_data;
                $cache_updated = true;
            } else {
                $this->forecast_data = $this->cache_data["cache"][max(array_keys($this->cache_data["cache"]))];
            }
        } else {
            $this->forecast_data = json_decode(
                file_get_contents(Config::getConfig("data_source/jma/forecast_url")),
                null,
                512,
                JSON_BIGINT_AS_STRING | JSON_INVALID_UTF8_IGNORE | JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR
            );
            $this->cache_data["cached_timestamp"] = $this->now->getTimestamp();
            $this->cache_data["cache"][$this->getReportDateTime()->getTimestamp()] = $this->forecast_data;
            $cache_updated = true;
        }
        
        $history_life_timestamp = $this->now->getTimestamp()
            - Config::getConfigOrSetIfUndefined("data_source/jma/cache/history", 0);
        foreach ($this->cache_data["cache"] as $key => $value) {
            if (intval($key) < $history_life_timestamp) {
                unset($this->cache_data["cache"][$key]);
                $cache_updated = true;
            }
        }
        
        if ($cache_updated) {
            file_put_contents(
                Config::getConfig("data_source/jma/cache/file"),
                json_encode($this->cache_data, JSON_INVALID_UTF8_IGNORE | JSON_THROW_ON_ERROR, 512),
                LOCK_EX
            );
        }
        
        if (!is_null($cache_timestamp)) {
            if (empty($this->cache_data["cache"][$cache_timestamp])){
                throw new \Exception("The cache (timestamp={$cache_timestamp}) is not saved");
                return;
            }
            $this->forecast_data = $this->cache_data["cache"][$cache_timestamp];
        }
        
        $previous_timestamp = 0;
        $current_timestamp = $this->getReportDateTime()->getTimestamp();
        foreach (array_keys($this->cache_data["cache"]) as $timestamp) {
            if ($timestamp < $current_timestamp && $previous_timestamp < $timestamp)
                $previous_timestamp = $timestamp;
        }
        $this->previous_timestamp = ($previous_timestamp == 0) ? null : $previous_timestamp;
    }
    
    public function getText(string $type, ?array $parameter, ?\DateTimeInterface $now = null): ?string
    {
        if (is_null($now))
            $now = $this->now;
        else
            $now = \DateTimeImmutable::createFromInterface($now);
        
        switch ($type) {
            case "report_datetime":
                return static::getReportDateTime()->format($parameter["format"] ?? \DateTimeInterface::ATOM);
                break;
            
            case "report_publishing_office":
                return static::getPublishingOffice()
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "weather_area_name":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_CODE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $now
                );
                return $content["area_name"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "weather_area_code":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_CODE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $now
                );
                return $content["area_code"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "weather_code":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_CODE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );
                return $content["content"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "weather_description":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_CODE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );
                
                if (is_null($content["content"] ?? null))
                    return Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                
                $description = static::getWeatherDescription($content["content"]);
                if (!isset($parameter["max_characters_per_line"]))
                    return $description;
                
                $max_characters_per_line = $parameter["max_characters_per_line"];
                $encoding = Config::getConfigOrSetIfUndefined("data_source/jma/encoding", "UTF-8");
                $text = [];
                $cursor = 0;
                $length = mb_strlen($description, $encoding);
                while ($cursor < $length) {
                    $text[] = mb_substr($description, $cursor, $max_characters_per_line, $encoding);
                    $cursor += $max_characters_per_line;
                }
                return implode(PHP_EOL, $text);
                break;
                
            case "weather_detail":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_DETAIL,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );
                return $content["content"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "wind":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WIND,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );
                return $content["content"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "wave":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WAVE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );
                return $content["content"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
                
            case "pop_area_name":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $now
                );
                return $content["area_name"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "pop_area_code":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $now
                );
                return $content["area_code"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "pop_concat":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content_array = [];
                $content_array[] = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date,
                    true
                )
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"] ?? "-";
                $content_array[] = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date->modify("06:00"),
                    true
                )
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"] ?? "-";
                $content_array[] = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date->modify("12:00"),
                    true
                )
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"] ?? "-";
                $content_array[] = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date->modify("18:00"),
                    true
                )
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"] ?? "-";
                return implode(
                    $parameter["separator"] ?? "/",
                    $content_array
                );
                break;
                
            case "pop":
                $target_date = match (strtolower($parameter["target_datetime"])) {
                    "today_00" => $now->modify("today 00:00"),
                    "today_06" => $now->modify("today 06:00"),
                    "today_12" => $now->modify("today 12:00"),
                    "today_18" => $now->modify("today 18:00"),
                    "tomorrow_00" => $now->modify("tomorrow 00:00"),
                    "tomorrow_06" => $now->modify("tomorrow 06:00"),
                    "tomorrow_12" => $now->modify("tomorrow 12:00"),
                    "tomorrow_18" => $now->modify("tomorrow 18:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date,
                    true
                );
                
                if (!is_null($content["content"] ?? null))
                    return $content["content"];
                
                // get data from a week forecast
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                    Config::getConfig("data_source/jma/area/probability_of_precipitation"),
                    $target_date->modify("00:00"),
                    true,
                    true
                );
                
                return $content["content"]
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"]
                    ?? "-";
                break;
                
            case "temp_area_name":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $now
                );
                return $content["area_name"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "temp_area_code":
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $now
                );
                return $content["area_code"]
                    ?? Config::getConfigOrSetIfUndefined("data_source/jma/default_none_string", "");
                break;
            
            case "temp_min":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $target_date,
                    true
                );
                if (!is_null($content["content"] ?? null))
                    return $content["content"];
                
                // get data from a week forecast
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE_MINIMUM,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $target_date->modify("00:00"),
                    true,
                    true
                );
                
                return $content["content"]
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"]
                    ?? "-";
                break;
            
            case "temp_max":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 09:00"),
                    "tomorrow" => $now->modify("tomorrow 09:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $target_date,
                    true
                );
                if (!is_null($content["content"] ?? null))
                    return $content["content"];
                
                // get data from a week forecast
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_TEMPERATURE_MAXIMUM,
                    Config::getConfig("data_source/jma/area/temperature"),
                    $target_date->modify("00:00"),
                    true,
                    true
                );
                
                return $content["content"]
                    ?? (
                        (Config::getConfigOrSetIfUndefined("data_source/jma/fallback", false) && !is_null($this->previous_timestamp))
                            ? (new self($now, $this->previous_timestamp))->getText($type, $parameter, $now) : null
                    )
                    ?? $parameter["none_character"]
                    ?? "-";
                break;
        }
        return null;
    }
    
    public function getImage(string $type, ?array $parameter, ?\DateTimeInterface $now = null): ?\GdImage
    {
        if (is_null($now))
            $now = $this->now;
        else
            $now = \DateTimeImmutable::createFromInterface($now);
        
        switch ($type) {
            case "weather":
                $target_date = match (strtolower($parameter["target_date"])) {
                    "today" => $now->modify("today 00:00"),
                    "tomorrow" => $now->modify("tomorrow 00:00"),
                };
                $content = static::getForecastData(
                    static::FORECAST_CONTENT_WEATHER_CODE,
                    Config::getConfig("data_source/jma/area/weather"),
                    $target_date
                );

                if (is_null($content["content"] ?? null))
                    return null;
                
                $target_date = \DateTimeImmutable::createFromInterface($content["time_define"]);
                $threshold_date = $target_date->modify(
                    Config::getConfigOrSetIfUndefined("data_source/jma/day_night_threshold", '17:00:00+09:00')
                );
                
                $image_path = static::getWeatherImagePath(
                    $content["content"],
                    ($target_date < $threshold_date)
                );
                return imagecreatefromstring(file_get_contents(Common::getAbsolutePath($image_path)));
                break;
        }
        return null;
    }
    
    /**
     * @param \DateTimeInterface[] $datetime_list
     */
    protected function getNearestDateTimeIndex(\DateTimeInterface $needle, array $datetime_list, bool $strict = false)
        : ?int
    {
        $reference_timestamp = $needle->getTimestamp();
        if ($strict) {
            $previous = null;
            foreach ($datetime_list as $key => $datetime) {
                // skip if the previous points to a future datetime
                // memo: inverted `timeDefines` member seems to be ignored by the JMA official scripts
                if (!is_null($previous) && $datetime <= $previous)
                    continue;
                if ($datetime->getTimestamp() == $reference_timestamp)
                    return $key;
                $previous = $datetime;
            }
            return null;
        } else {
            $min_diff = null;
            $candidate_key = 0;
            $previous = null;
            foreach ($datetime_list as $key => $datetime) {
                // skip if the previous points to a future datetime
                // memo: inverted `timeDefines` member seems to be ignored by the JMA official scripts
                if (!is_null($previous) && $datetime <= $previous)
                    continue;
                $diff = abs($reference_timestamp - $datetime->getTimestamp());
                if (is_null($min_diff) || $diff < $min_diff) {
                    $candidate_key = $key;
                    $min_diff = $diff;
                }
                $previous = $datetime;
            }
            return $candidate_key;
        }
    }
    
    
    /**
     * @return \DateTimeImmutable[]
     */
    protected function getDateTimeImmutableFromStringForEach(array $datetime): array
    {
        return array_map(
            fn($value): \DateTimeImmutable
                => new \DateTimeImmutable(
                    $value,
                    new \DateTimeZone(Config::getConfigOrSetIfUndefined("data_source/jma/timezone", "+09:00"))
                ),
            $datetime
        );
    }
    
    protected function getTimeDefineList(int $forecast_type, bool $week_forecast = false): array
    {
        return static::getDateTimeImmutableFromStringForEach(
            $this->forecast_data[$week_forecast ? 1 : 0]["timeSeries"][$forecast_type]["timeDefines"]
        );
    }
    
    protected function getAreaData(string $area_code, int $forecast_type, bool $week_forecast = false): ?array
    {
        foreach ($this->forecast_data[$week_forecast ? 1 : 0]["timeSeries"][$forecast_type]["areas"] as $area) {
            if ($area["area"]["code"] == $area_code)
                return $area;
        }
        return null;
    }
    
    protected function getForecastData(string $forecast_content, string $area_code, \DateTimeInterface $datetime,
                                       bool $strict = false, bool $week_forecast = false): ?array
    {
        if ($week_forecast) {
            $forecast_type = match ($forecast_content) {
                static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                => static::FORECAST_TYPE_WEEK_PROBABILITY_OF_PRECIPITATION,
                static::FORECAST_CONTENT_TEMPERATURE_MINIMUM,
                static::FORECAST_CONTENT_TEMPERATURE_MAXIMUM,
                => static::FORECAST_TYPE_WEEK_TEMPERATURE,
            };
        } else {
            $forecast_type = match ($forecast_content) {
                static::FORECAST_CONTENT_WEATHER_CODE,
                static::FORECAST_CONTENT_WEATHER_DETAIL,
                static::FORECAST_CONTENT_WIND,
                static::FORECAST_CONTENT_WAVE,
                => static::FORECAST_TYPE_DAYS_WEATHER,
                static::FORECAST_CONTENT_PROBABILITY_OF_PRECIPITATION,
                => static::FORECAST_TYPE_DAYS_PROBABILITY_OF_PRECIPITATION,
                static::FORECAST_CONTENT_TEMPERATURE,
                => static::FORECAST_TYPE_DAYS_TEMPERATURE,
            };
        }
        
        $area_data = static::getAreaData($area_code, $forecast_type, $week_forecast);
        if (is_null($area_data))
            return null;
        $time_define_list = static::getTimeDefineList($forecast_type, $week_forecast);
        $index = static::getNearestDateTimeIndex($datetime, $time_define_list, $strict);
        if (is_null($index))
            return null;
        $content = $area_data[$forecast_content][$index];
        return [
            "area_name" => $area_data["area"]["name"],
            "area_code" => $area_data["area"]["code"],
            "time_define" => $time_define_list[$index],
            "content" => ((empty($content) && !is_numeric($content)) ? null : $content), // zeros should be returned as is
        ];
    }
    
    protected function getReportDateTime(): ?\DateTimeImmutable
    {
        if (empty($this->forecast_data[0]["reportDatetime"]))
            return null;
        return new \DateTimeImmutable(
            $this->forecast_data[0]["reportDatetime"],
            new \DateTimeZone(Config::getConfigOrSetIfUndefined("data_source/jma/timezone", "+09:00"))
        );
    }
    
    protected function getPublishingOffice(): ?string
    {
        return $this->forecast_data[0]["publishingOffice"] ?? null;
    }
    
    protected function getWeatherDescription(?string $weather_code): ?string
    {
        if (empty($weather_code))
            return null;
        return Config::getConfigOrSetIfUndefined("data_source/jma/weather_code/{$weather_code}/weather", null);
    }
    
    protected function getWeatherImagePath(?string $weather_code, bool $daytime = true): ?string
    {
        if (empty($weather_code))
            return null;
        $key = "data_source/jma/weather_code/{$weather_code}/" . ($daytime ? "image_day" : "image_night");
        return Config::getConfigOrSetIfUndefined($key, null);
    }
    
}