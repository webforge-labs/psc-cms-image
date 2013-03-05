<?php

namespace Psc\Image;

use \Imagine\Image\ImageInterface AS ImagineImage;

class StandardTransformation implements Transformation {
  
  public function processArguments(\Imagine\Image\ImageInterface $originalImage, Array $arguments) {
    list($width,$height,$method) = $arguments;
  
    return $this->process($originalImage, $width,$height,$method);
  }
}
?>