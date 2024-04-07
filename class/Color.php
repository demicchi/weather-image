<?php

namespace StudioDemmys\WeatherImage;

class Color
{
    public int $r;
    public int $g;
    public int $b;
    public int $a;
    
    public function __construct(array $color)
    {
        $this->r = $color[0] ?? 0;
        $this->g = $color[1] ?? 0;
        $this->b = $color[2] ?? 0;
        $this->a = $color[3] ?? 0;
    }
}