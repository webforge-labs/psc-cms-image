<?php

namespace Psc\Image;

class ThumbnailTransformationTest extends \Webforge\Code\Test\Base {
  
  public function setUp() {
    $this->chainClass = 'Psc\\Image\\ThumbnailTransformation';
    parent::setUp();

    $this->imagine = new \Imagine\Gd\Imagine();
    $this->bud = $this->imagine->open((string) $this->getFile('images/bud.jpg')); // 62x62

    $this->transformation = new ThumbnailTransformation();
  }

  public function testThumbnailStandard_ResizedWhenOneSideBigger() {
    $thumb = $this->thumbnail($this->bud, 'standard', 40, 120);
    $this->assertSize($thumb, 40, 40); // resizes the width to the correct (not 120)

    $thumb = $this->thumbnail($this->bud, 'standard', 120, 40);
    $this->assertSize($thumb, 40, 40); // resizes the height to the correct (not 120)
  }

  public function testThumbnailStandard_NotResizedWhenSmaller() {
    $thumb = $this->thumbnail($this->bud, 'standard', 62, 120);
    $this->assertSize($thumb, 62, 62);
  }

  public function testThumbnailOutbound_NotResizedWhenSmaller() {
    $thumb = $this->thumbnail($this->bud, 'outbound', 62, 120);
    $this->assertSize($thumb, 62, 62);
  }

  protected function assertSize($image, $width, $height) {
    $size = $image->getSize();

    $this->assertEquals($width, $size->getWidth(), 'width from image does not match. '.$size);
    $this->assertEquals($height, $size->getHeight(), 'height from image does not match. '.$size);
  }

  protected function thumbnail($image, $method, $width, $height, Array $options = array()) {
    $image = $this->transformation->processArguments($image, array($width, $height, $method), $options);

    $this->assertInstanceOf('Imagine\Image\ImageInterface', $image);

    return $image;
  }
}
