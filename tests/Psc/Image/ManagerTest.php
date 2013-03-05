<?php

namespace Psc\Image;

use Psc\Image\Manager;
use Psc\Entities\Image;
use Webforge\Common\System\File;

/**
 * @group Imagine
 * @group class:Psc\Image\Manager
 */
class ManagerTest extends \Psc\Doctrine\DatabaseTestCase {

  protected $manager;

  public function setUp() {
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
  
  public function testManagerHasAFileCacheAsDefaultCache() {
    $this->assertInstanceOf('Psc\Data\FileCache', $this->manager->getCache(), ' Test nur für Filecache gemacht');
  }
  
  public function testManagerCanStoreAnImage() {
    $this->resetDirectory();
    $image = $this->manager->store($this->im('img1.jpg'), $title = 'my nice title');
    
    $this->assertInstanceOf('Psc\Image\Image', $image);
    $this->resetDatabaseOnNextTest();
  }
  
  public function testManagerCanLoadAStoredImage() {
    $this->resetDirectory();
    $image = $this->storeFile($this->getFile('img1.jpg'), $label = 'my nice label');
    
    $this->manager->flush();
    $this->manager->clear(); // macht auch em clear
    
    $id = $image->getIdentifier();
    $imageDB = $this->manager->load($id);
    
    $this->assertEquals($image->getSourcePath(), $imageDB->getSourcePath());
    $this->assertEquals($image->getHash(), $imageDB->getHash());
    $this->assertEquals($label, $imageDB->getLabel());
    $this->resetDatabaseOnNextTest();
  }

  public function testManagerStoresAnAlreadyStoredImage() {
    $image1 = $this->manager->store($this->im('img1.jpg'), 'label');
    $this->manager->flush();
    $this->manager->clear();
    
    $image2 = $this->manager->store($this->im('img1.jpg'), 'label', Manager::IF_NOT_EXISTS);
    $this->manager->flush();
    
    $this->assertEquals($image1->getHash(), $image2->getHash());
    $this->assertEquals($image1->getSourcePath(), $image2->getSourcePath());
  }
  
  public function testImagesWillBeWrittenFromImagineInstancesToDatabase_AfterFlushTheyHaveTheSourceFileAndPathAttached() {
    $this->resetDirectory();

    $image1 = $this->storeCommonFile('image1.jpg');
    $image4 = $this->storeCommonFile('image4.jpg');
    $image10 = $this->storeCommonFile('image10.jpg');
    
    $this->em->flush();
    
    $this->assertNotEmpty($image1->getSourcePath());
    $this->assertNotEmpty($image4->getSourcePath());
    $this->assertNotEmpty($image10->getSourcePath());

    $this->assertFileExists((string) $image1->getSourceFile());
    $this->assertTrue($image1->getSourceFile()->getDirectory()->isSubdirectoryOf($this->dir));
    $this->assertFileExists((string) $image4->getSourceFile());
    $this->assertTrue($image4->getSourceFile()->getDirectory()->isSubdirectoryOf($this->dir));
    $this->assertFileExists((string) $image10->getSourceFile());
    $this->assertTrue($image10->getSourceFile()->getDirectory()->isSubdirectoryOf($this->dir));
  }
  
  protected function storeCommonFile($name) {
    return $this->storeFile($this->getCommonFile($name, 'images/'));
  }
  
  protected function storeFile(File $file, $label = NULL) {
    $imagine = $this->imagine->open((string) $file);
    
    return $this->manager->store($imagine, $label);
  }

  public function testRemoveImageDeletesThePhysicalFileAsWell() {
    $image1 = $this->manager->load(1);
    
    $this->manager->remove($image1);
    $this->manager->flush();
    
    $this->assertFileNotExists((string) $image1->getSourceFile());
  }
  
  public function testThumbnailAcceptance() {
    $image2 = $this->manager->load(2);
    
    /* thumbnailing */
    $iiThumb4 = $image2->getThumbnail(104, 97);
    $iiThumb4 = $image2->getThumbnail(104, 97);
  }
  
  public function testOtherOSPathinDB() {
    $image = $this->manager->store($this->imagine->load(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAAbCAIAAAAyOnIjAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAEBJREFUeNo8w9sNwCAIBdC7/3T82g0Q5GEXMJDWkxwQEZi5zzlvEfkqVP8La1WDWXW41+gRNXvmvvd+MZ5xBBgAnedKkCs1gtkAAAAASUVORK5CYII=')));
    $this->manager->flush();
    
    $iname = 'ssljdlfj.png';
    $image->setSourcePath('./q/'.$iname);
    
    $this->assertEquals(
      (string) new File($this->dir->sub('q/'), $iname),
      (string) $this->manager->getSourceFile($image)->resolvePath()
    );
  }

  public function testManagerStoresAnAlreadyStoredImageWithUniqueConstraint() {
    $this->resetDatabaseOnNextTest();
    
    $image2 = $this->manager->store($this->im('img1.jpg'), 'label');
    $this->manager->flush();
    
    // unique constraint muss verletzt werden:
    $this->setExpectedException('Doctrine\DBAL\DBALException');
    $image2 = $this->manager->store($this->im('img1.jpg'), 'label');
    
    $this->manager->flush();
  }
  
  protected function im($name) {
    return $this->imagine->open($this->getFile($name));
  }
}
?>