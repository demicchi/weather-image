<?php

namespace StudioDemmys\WeatherImage\DataSource;

interface DataSourceInterface
{
    public function getText(string $type, ?array $parameter, ?\DateTimeInterface $now): ?string;
    public function getImage(string $type, ?array $parameter, ?\DateTimeInterface $now): ?\GdImage;
}