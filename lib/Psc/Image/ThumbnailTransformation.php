<?php

namespace Psc\Image;

use \Imagine\Image\ImageInterface AS ImagineImage,
    \Imagine\Image\Box
;

class ThumbnailTransformation extends StandardTransformation {

  public function process(ImagineImage $imagineImage, $width, $height, $method='standard') {
    if ($method == 'standard')
      $style = ImagineImage::THUMBNAIL_INSET;
    else
      $style = ImagineImage::THUMBNAIL_OUTBOUND;

    return $imagineImage->thumbnail(new Box($width, $height), $style);
  }
}

?>