<?php

/**
 * Entropy trait code mostly copied from https://github.com/kivkovic/entropy-cropper
 */

namespace Intervention\Image\Imagick\Commands\Traits;

trait UsesEntropyTrait
{
    public function getEntropyPoint($image, $cropWidth, $cropHeight)
    {
        $width    = $image->getSize()->getWidth();
        $height   = $image->getSize()->getHeight();
        $resource = $image->getCore();
        $factor   = 1;
//        logg("Image resolution: {$width} x {$height}");
//        logg("Crop size: {$cropWidth} x {$cropHeight}");
        if (min($width, $height) <= 0 || max($width, $height) > 10000) {
            throw new Exception('Invalid image size');
        }
        if ($width > 1000 || $height > 1000) {
            $factor    = $width > $height ? (1000 / $width) : (1000 / $height);
            $newWidth  = round($width * $factor);
            $newHeight = round($height * $factor);
            $clone     = $this->cloneResized($resource, $newWidth, $newHeight);
//            logg("Working copy resized to: {$newWidth} x {$newHeight}");
        } else {
            $clone     = clone $resource;
            $newHeight = $height;
            $newWidth  = $width;
        }
        $this->simpleBlur($clone, 8);
        imagefilter($clone, IMG_FILTER_EDGEDETECT);
        imagefilter($clone, IMG_FILTER_CONTRAST, -100);
        $this->simpleBlur($clone, 36);
        $colors = $this->colors($clone, $newWidth, $newHeight);
        $stepX  = max(1, floor(($newWidth - $cropWidth * $factor) / 3));
        $stepY  = max(1, floor(($newHeight - $cropHeight * $factor) / 3));

        list($targetX, $targetY) = $this->maxEntropySegment($colors, $newWidth, $newHeight, $cropWidth * $factor,
            $cropHeight * $factor, $stepX, $stepY);

        imagedestroy($clone);

        return [
            'x' => $targetX,
            'y' => $targetY,
        ];

    }

    protected function simpleBlur($image, $repeat = 1)
    {
        for ($i = 0; $i < $repeat; $i++) {
            imageconvolution($image,
                [
                    [1.0, 1.0, 1.0],
                    [1.0, 1.0, 1.0],
                    [1.0, 1.0, 1.0],
                ], 8.975, 0
            );
        }
    }

    protected function cloneResized($resource, $new_width, $new_height)
    {
        $width  = imagesx($resource);
        $height = imagesy($resource);
        $clone  = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($clone, $resource, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        return $clone;
    }

    protected function maxEntropySegment($colors, $width, $height, $crop_width, $crop_height, $step_x, $step_y)
    {
        $max_entropy = -100000;
        $max_x       = 0;
        $max_y       = 0;
        for ($x = 0; $x < $width - $crop_width; $x += $step_x) {
            for ($y = 0; $y < $height - $crop_height; $y += $step_y) {
                $current_entropy = $this->entropy($colors, $x, $y, $crop_width, $crop_height, 1, 1, 1);
                if ($current_entropy > $max_entropy) {
                    $max_entropy = $current_entropy;
                    $max_x       = $x;
                    $max_y       = $y;
                }
            }
        }

        return [$max_x, $max_y, $max_entropy];
    }

    protected function colors($image, $width, $height)
    {
        $rgb = [];
        for ($x = 0; $x < $width; $x++) {
            $rgb[$x] = [];
            for ($y = 0; $y < $height; $y++) {
                $value       = imagecolorat($image, $x, $y);
                $rgb[$x][$y] = $value;
            }
        }

        return $rgb;
    }

    protected function entropy(
        $colors,
        $x_offset,
        $y_offset,
        $crop_width,
        $crop_height,
        $normalize_r = 1,
        $normalize_g = 1,
        $normalize_b = 1
    ){
        $levels_rgb = array_fill(0, 768, 0);
        for ($x = 0; $x < $crop_width; $x++) {
            for ($y = 0; $y < $crop_height; $y++) {
                $rgb = $colors[$x + $x_offset][$y + $y_offset];
                list($r, $g, $b) = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
                $levels_rgb[$r]++;
                $levels_rgb[$g + 256]++;
                $levels_rgb[$b + 512]++;
            }
        }
        $entropy_rgb = $this->levelsToEntropy($levels_rgb);

        return $entropy_rgb;
    }

    protected function levelsToEntropy($levels)
    {
        $size = count($levels);
        $sum  = 0;
        for ($i = 0; $i < $size; $i++) {
            $sum += $levels[$i];
        }
        $entropy = 0;
        for ($i = 0; $i < $size; $i++) {
            if ($levels[$i] == 0) {
                continue;
            }
            $value   = $levels[$i] / $sum;
            $entropy += $value * log($value, 2);
        }

        return -$entropy;
    }
//
//    function logg($line = "")
//    {
//        echo "[".microtime(true)."] ".$line."\n";
//    }

}