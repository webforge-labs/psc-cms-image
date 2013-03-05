<?php

namespace Psc\Image;

use \Imagine\Image\ImageInterface AS ImagineImage;

class ThumbnailCacheAdapter implements CacheAdapter {
  
  public function getCacheKeys(ImagineImage $imageVersion, Image $image, Array $arguments = array()) {
    $keys = array_merge(array('thumbnail'), $this->convertArguments($arguments));
    return $keys;
  }
  
  public function convertArguments(Array $arguments) {
    list($width,$height,$method) = $arguments;
    return array($method,(int) $width, (int) $height);
  }
}
?>