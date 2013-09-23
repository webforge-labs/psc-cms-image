<?php

namespace Psc\Image;

use Psc\Image\Manager;
use Psc\Entities\Image;
use Webforge\Common\System\File;
use Webforge\Common\String as S;

/**
 * @group Imagine
 */
class ThumbnailFormatTest extends \PHPUnit_Framework_TestCase { //\Psc\Doctrine\DatabaseTestCase

  protected $manager;

  public function setUp() {
    $this->markTestSkipped('this cannot go here, because CMS, this cannot go CMS because imagine?');
    // leere fixture für images
    parent::setUp();
    
    $this->manager = new Manager('Psc\Entities\Image', $this->em);
    $this->imagine = new \Imagine\GD\Imagine;
    
    // da wir den Manager mit keinem Parameter aufrufen, ist base/files/images unser originalDir
    $this->dir = $this->getProject()->getFiles()->sub('images/');
    $this->cacheDir = $this->getProject()->getCache()->sub('images/');
  }
  
  protected function resetDirectory() {
    // reset physical files
    $this->assertEquals($this->dir, $this->manager->getDirectory());
    $this->dir->wipe();    
  }

  public function testImageThumbnailGetsRightSizeWithOutbound() {
    $this->resetDatabaseOnNextTest();
    $image = $this->manager->store($this->im('image2.jpg'), $title = 'my nice title');

    $thumb = $image->getThumbnail(300, 200, $method = 'outbound', array('format'=>'jpg', 'quality'=>70));

    $this->assertInstanceOf('Imagine\Image\ImageInterface', $thumb);

    $size = $thumb->getSize();
    $this->assertEquals(200, $size->getHeight());
    $this->assertEquals(300, $size->getWidth());
  }

  public function testSaveImageThumbnailInOtherFormatThanpng() {
    $this->resetDatabaseOnNextTest();
    $image = $this->manager->store($this->im('image2.jpg'), $title = 'my nice title');

    $url = $image->getUrl('thumbnail', array(300, 200, 'outbound'), array('format'=>'jpg', 'quality'=>70));
    // /dimg bla
    if (S::startsWith($url,'/dimg/')) {
      $url = mb_substr($url, 6);
    }

    $file = File::createFromURL($url, $this->cacheDir);
    $this->assertTrue($file->exists(), $file.' does not exist');

    $this->assertEquals('jpg', $file->getExtension());

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, (string) $file);
    finfo_close($finfo);

    $this->assertEquals('image/jpeg', $mimeType);
  }
 
  protected function im($name) {
    return $this->imagine->open($this->getCommonFile($name, 'images/'));
  }
}
