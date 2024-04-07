<?php

namespace StudioDemmys\WeatherImage;


class Image
{
    protected function __construct()
    {
    }
    
    public static function calculateBoundingBox(float $size, string $font, string $text) : TextBoundingBox
    {
        Logging::debug("Calculate the baseline offset for {$text} in size of {$size} of {$font}");
        $text_box = imagettfbbox($size, 0, $font, $text);
        if ($text_box === false)
            exit("error");
        Logging::debug("  the baseline offset is " . $text_box[1]);
        return new TextBoundingBox(
            [$text_box[0], $text_box[1]],
            [$text_box[2], $text_box[3]],
            [$text_box[4], $text_box[5]],
            [$text_box[6], $text_box[7]],
            $text_box[1]
        );
    }
    
    public static function calculateTextWritePosition(array $box_position, array $box_size, TextBoundingBox $text_bounding_box) : array
    {
        $text_width = abs($text_bounding_box->bottom_left[0] - $text_bounding_box->bottom_right[0]);
        $text_height = abs($text_bounding_box->bottom_left[1] - $text_bounding_box->top_left[1]);
        $x = $box_position[0] + (($box_size[0] - $text_width) / 2);
        $y = $box_position[1] + (($box_size[1] - $text_height) / 2) + $text_height - $text_bounding_box->baseline_offset;
        Logging::debug("box_x=" . $box_position[0] . ", box_width=" . $box_size[0]);
        Logging::debug("text_width=" . $text_width);
        Logging::debug("=====> text_x=". $x);
        Logging::debug("box_y=" . $box_position[1] . ", box_height=" . $box_size[1]);
        Logging::debug("text_height=" . $text_height . ", text_baseline=" . $text_bounding_box->baseline_offset);
        Logging::debug("=====> text_y=" . $y);
        return [$x, $y];
    }
    
    public static function writeTextInBox(\GdImage $image, array $box_position, array $box_size,
                                          float $font_size, string $font_file, array|int|Color $color, string $text,
                                          int $baseline_offset = null): array|false
    {
        $bounding_box = self::calculateBoundingBox($font_size, $font_file, $text);
        Logging::debug("The bounding box: " . print_r($bounding_box, true));
        
        if (is_int($color)) {
            $text_color = $color;
        } elseif (is_array($color)) {
            $text_color = imagecolorallocatealpha(
                $image,
                $color[0],
                $color[1],
                $color[2],
                $color[3]
            );
        } else {
            $text_color = imagecolorallocatealpha(
                $image,
                $color->r,
                $color->g,
                $color->b,
                $color->a
            );
        }
        
        if (!is_null($baseline_offset)) {
            Logging::debug("The baseline offset is overwritten to " . $baseline_offset);
            $bounding_box->baseline_offset = $baseline_offset;
        }
        
        $position = self::calculateTextWritePosition($box_position, $box_size, $bounding_box);
        
        $text_box = imagettftext($image, $font_size, 0, $position[0], $position[1], $text_color, $font_file, $text);
        if ($text_box === false)
            exit("error");
        return $text_box;
    }
    
    
    public static function getColorInt(\GdImage $image, array|int|Color $color): bool|int
    {
        if (is_int($color))
            return $color;
        if (is_array($color))
            return imagecolorallocatealpha($image, $color[0], $color[1], $color[2], $color[3]);
        return imagecolorallocatealpha($image, $color->r, $color->g, $color->b, $color->a);
    }
}