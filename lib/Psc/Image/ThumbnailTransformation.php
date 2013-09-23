<?php

namespace Psc\Image;

use Imagine\Image\ImageInterface AS ImagineImage;
use Imagine\Image\Box;

class ThumbnailTransformation implements Transformation {

  /**
   * @param list ($width, $height, $method) $arguments;
   */
  public function processArguments(ImagineImage $imagineImage, Array $arguments, Array $options = array()) {
    list ($width, $height, $method) = $arguments;

    if ($method == 'standard') {
      $style = ImagineImage::THUMBNAIL_INSET;
    } else {
      $style = ImagineImage::THUMBNAIL_OUTBOUND;
    }

    return $imagineImage->thumbnail(new Box($width, $height), $style);
  }
}
