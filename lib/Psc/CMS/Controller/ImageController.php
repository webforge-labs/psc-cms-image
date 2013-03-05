<?php

namespace Psc\CMS\Controller;

use Psc\Image\Manager;
use Psc\Form\ValidationPackage;
use Webforge\Common\System\File;
use Psc\Net\ServiceResponse;
use Psc\Net\Service;

/**
 * 
 */
class ImageController extends \Psc\SimpleObject {
  
  /**
   * @var Psc\Image\Manager
   */
  protected $manager;
  
  /**
   *
   * noch nicht benutzt bis jetzt
   * @var Psc\Form\ValidationPackage
   */
  protected $v;
  
  public function __construct(Manager $manager, ValidationPackage $v = NULL) {
    $this->setManager($manager);
    $this->setValidationPackage($v ?: new ValidationPackage());
  }
  
  /**
   * @controller-api
   * @return Psc\Image\Image
   */
  public function getImage($idOrHash, $fileName = NULL) {
    $image = $this->manager->load($idOrHash);
    
    // is das nich eigentlich falsch hier? weil es muss doch nur bei insertImageFile was workaroundiges für die IEs kommen, oder?
    return new ServiceResponse(Service::OK, $image, ServiceResponse::JSON_UPLOAD_RESPONSE);
  }
  
  /**
   * @controller-api
   * @return Psc\Image\Image
   */
  public function insertImageFile(File $img, \stdClass $specification) {
    $image = $this->manager->store($this->manager->createImagineImage($img),
                                   NULL,
                                   Manager::IF_NOT_EXISTS
                                  );
    $this->manager->flush();
    
    return new ServiceResponse(Service::OK, $image, ServiceResponse::JSON_UPLOAD_RESPONSE);
  }
  
  /**
   * @param Psc\Image\Manager $manager
   */
  public function setManager(Manager $manager) {
    $this->manager = $manager;
    return $this;
  }
  
  /**
   * @return Psc\Image\Manager
   */
  public function getManager() {
    return $this->manager;
  }
  
  /**
   * @param Psc\Form\ValidationPackage $v
   */
  public function setValidationPackage(ValidationPackage $v) {
    $this->v = $v;
    return $this;
  }
  
  /**
   * @return Psc\Form\ValidationPackage
   */
  public function getValidationPackage() {
    return $this->v;
  }
}
?>