<?php

namespace StudioDemmys\WeatherImage;


if (!defined('WEATHERIMAGE_UNIQUE_ID'))
    define("WEATHERIMAGE_UNIQUE_ID", bin2hex(random_bytes(4)));

require_once dirname(__FILE__)."/class/Common.php";
require_once dirname(__FILE__)."/class/Config.php";

Config::loadConfig();
Config::getConfigOrSetIfUndefined("logging/level", "debug");
Config::getConfigOrSetIfUndefined("logging/file", "./log/log.txt");

require_once dirname(__FILE__)."/class/ErrorLevel.php";
require_once dirname(__FILE__)."/class/Logging.php";

require_once dirname(__FILE__)."/class/Color.php";
require_once dirname(__FILE__)."/class/TextBoundingBox.php";
require_once dirname(__FILE__)."/class/Image.php";

require_once dirname(__FILE__)."/class/DataSource/DataSourceInterface.php";
require_once dirname(__FILE__)."/class/DataSource/Jma.php";
require_once dirname(__FILE__)."/class/DataSource/Date.php";

// --------------------------------------------------------------------------------
// Initialize
// --------------------------------------------------------------------------------

$timezone = Config::getConfigOrSetIfUndefined("timezone", date_default_timezone_get());

$now = new \DateTimeImmutable("now", new \DateTimeZone($timezone));
Logging::debug("now: " . $now->format(\DateTimeInterface::ATOM));

$jma = new \StudioDemmys\WeatherImage\DataSource\Jma();
$date = new \StudioDemmys\WeatherImage\DataSource\Date();

// --------------------------------------------------------------------------------
// Define a style
// --------------------------------------------------------------------------------

$style = Config::getConfig("default_style");
if (isset($_GET["style"])) {
    $query_style = strtolower(Common::sanitizeUserInput($_GET["style"]));
    if (empty(Config::getConfigOrSetIfUndefined("style/{$query_style}")))
        $query_style = $style;
    $style = $query_style;
}

// --------------------------------------------------------------------------------
// Prepare a base image
// --------------------------------------------------------------------------------

$image = imagecreatefromstring(file_get_contents(Config::getConfig("style/{$style}/background_image")));
if ($image === false)
    exit("error");

// --------------------------------------------------------------------------------
// Render labels
// --------------------------------------------------------------------------------

$labels = Config::getConfigOrSetIfUndefined("style/{$style}/labels");
if (is_array($labels)) {
    foreach ($labels as $label_name => $label) {
        Logging::debug("Write a label -- {$label_name}");
        $source = Config::getConfig("style/{$style}/labels/{$label_name}/source");
        switch (strtolower($source)) {
            case "jma":
                $print_text = $jma->getText(
                    Config::getConfig("style/{$style}/labels/{$label_name}/type"),
                    Config::getConfigOrSetIfUndefined("style/{$style}/labels/{$label_name}/parameter", null),
                    $now
                );
                break;
            case "date":
                $print_text = $date->getText(
                    Config::getConfig("style/{$style}/labels/{$label_name}/type"),
                    Config::getConfigOrSetIfUndefined("style/{$style}/labels/{$label_name}/parameter", null),
                    $now
                );
                break;
            default:
                exit("error");
        }
        $text_color = Config::getConfigOrSetIfUndefined("style/{$style}/labels/{$label_name}/font/color", [0, 0, 0, 0]);
        $text_box = Image::writeTextInBox(
            $image,
            Config::getConfig("style/{$style}/labels/{$label_name}/box/position"),
            Config::getConfig("style/{$style}/labels/{$label_name}/box/size"),
            Config::getConfig("style/{$style}/labels/{$label_name}/font/size"),
            Config::getConfig("style/{$style}/labels/{$label_name}/font/file"),
            $text_color,
            $print_text
        );
        
        if ($text_box === false)
            exit("error");
    }
}

// --------------------------------------------------------------------------------
// Render user images
// --------------------------------------------------------------------------------

$user_images = Config::getConfigOrSetIfUndefined("style/{$style}/images");
if (is_array($user_images)) {
    foreach ($user_images as $user_image_name => $user_image) {
        Logging::debug("Render a user image -- {$user_image_name}");
        $source = Config::getConfig("style/{$style}/images/{$user_image_name}/source");
        switch (strtolower($source)) {
            case "jma":
                $render_image = $jma->getImage(
                    Config::getConfig("style/{$style}/images/{$user_image_name}/type"),
                    Config::getConfigOrSetIfUndefined("style/{$style}/images/{$user_image_name}/parameter", null),
                    $now
                );
                if ($render_image !== false) {
                    $render_image_width = imagesx($render_image);
                    $render_image_height = imagesy($render_image);
                    imagecopyresampled(
                        $image,
                        $render_image,
                        (Config::getConfig("style/{$style}/images/{$user_image_name}/box/position"))[0],
                        (Config::getConfig("style/{$style}/images/{$user_image_name}/box/position"))[1],
                        0,
                        0,
                        (Config::getConfig("style/{$style}/images/{$user_image_name}/box/size"))[0],
                        (Config::getConfig("style/{$style}/images/{$user_image_name}/box/size"))[1],
                        $render_image_width,
                        $render_image_height
                    );
                } else {
                    Logging::warn("The image is invalid");
                    exit("error");
                }
                break;
            default:
                exit("error");
        }
    }
}

// --------------------------------------------------------------------------------
// Export png
// --------------------------------------------------------------------------------

header('Content-Type: image/png');
ob_start();
imagepng($image);
$output = ob_get_contents();
ob_end_clean();
imagedestroy($image);
header('Content-Length:' . strlen($output));
echo $output;
exit();
