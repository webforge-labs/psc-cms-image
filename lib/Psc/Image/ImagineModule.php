<?php

namespace Psc\Image;

use \Psc\ClassLoader;

class ImagineModule extends \Psc\CMS\Module {
  
  protected $classLoader;
  
  public function bootstrap($bootFlags = 0x000000) {
    $this->dispatchBootstrapped();
    return $this;
  }
  
  public function getModuleDependencies() {
    return array();
  }
  
  public function getNamespace() {
    return 'Imagine';
  }
  
  public function getClassPath() {
    throw new \Psc\Exception('this is deprecated now');
  }
}
?>