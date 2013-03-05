<?php

namespace Psc\Image;

use \Imagine\Image\ImageInterface AS ImagineImage;

/**
 *
 *
 * Als Konvention kann man noch process(\Imagine\Image\ImageInterface $imagineImage, $args1, $args2, ...)
 * implementieren um ein doc-block freundlicheres Interface anzubieten
 */
interface Transformation {
  
  public function processArguments(\Imagine\Image\ImageInterface $originalImage, Array $arguments);
  
}
?>