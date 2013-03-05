<?php

namespace Psc\CMS\Controller;

use Psc\Image\Manager;
use Psc\Data\FileCache;
use Webforge\Common\System\File;

/**
 * @group class:Psc\CMS\Controller\ImageController
 */
class ImageControllerTest extends \Psc\Doctrine\DatabaseTestCase {
  
  protected $imageCtrl;
  
  public function setUp() {
    $this->chainClass = 'Psc\CMS\Controller\ImageController';
    parent::setUp();
    $manager = new Manager(
      'Psc\Entities\Image',
      $this->em,
      $this->getTestDirectory('images/')->create(),
      new FileCache($this->getTestDirectory('imagesCache/')->create())
    );
    
    $this->imageCtrl = new ImageController($manager);      
    
    $this->bud = $manager->store($manager->createImagineImage($this->getFile('img1.jpg')), 'bud', Manager::IF_NOT_EXISTS);
    $this->terence = $manager->store($manager->createImagineImage($this->getFile('img2.jpg')), 'terence', Manager::IF_NOT_EXISTS);
    $manager->flush();
  }
  
  public function testGetImageReturnsTheImageEntityQueriedById() {
    $this->assertSame(
      $this->bud,
      $this->getResponseData(
        $this->imageCtrl->getImage($this->bud->getIdentifier())
      )
    );
  }
  
  public function testGetImageReturnsTheImageEntityQueriedByHash() {
    $hashImage = $this->imageCtrl->getImage($this->terence->getHash());
    $this->assertSame(
      $this->terence,
      $this->getResponseData(
        $hashImage
      )
    );
  }
  
  public function testInsertImageFileReturnsImageEntity() {
    $file = File::createTemporary();
    $file->writeContents($this->getFile('img1.jpg')->getContents());
    
    $image = $this->imageCtrl->insertImageFile(
      $file,
      (object) array('specification','not','yet','specified') // yagni
    );
    
    $this->assertSame($this->bud, $this->getResponseData($image));
  }
  
  public function testImageConversionToResponseHasUsableURLInIt() {
    $export = $this->bud->export();
    $this->assertObjectHasAttribute('url', $export);
    $this->assertNotEmpty($export->url);
  }
  
  protected function getResponseData($response) {
    if ($response instanceof \Psc\Net\ServiceResponse) {
      return $response->getBody();
    } else {
      return $response;
    }
  }
}
?>