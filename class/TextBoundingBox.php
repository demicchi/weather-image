<?php

namespace StudioDemmys\WeatherImage;

class TextBoundingBox
{
    public function __construct(
        public array $bottom_left,
        public array $bottom_right,
        public array $top_right,
        public array $top_left,
        public int $baseline_offset,
    ){}
}