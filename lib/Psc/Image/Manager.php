<?php

namespace Psc\Image;

use \Psc\Doctrine\Entities\BasicImage,
    \Psc\Doctrine\Entities\BasicImageRepository,
    \Psc\Doctrine\Helper as DoctrineHelper,
    \Doctrine\ORM\EntityManager,
    \Doctrine\Common\Collections\ArrayCollection,
    \Imagine\Image\ImageInterface As ImagineImage,
    \Psc\Code\Code,
    \Webforge\Common\System\File,
    \Webforge\Common\System\Dir,
    \Webforge\Common\String as S,
    \Psc\PSC,
    \Imagine\Gd\Imagine,
    \Psc\Data\FileCache,
    \Psc\Data\Cache
    ;

class Manager extends \Psc\Object {
  
  const IF_NOT_EXISTS = 0x000002;
  
  /**
   * @var EntityRepository
   */
  protected $imageRep;
  
  /**
   * @var ArrayCollection
   */
  protected $images;
  
  /**
   * Das Root Verzeichnis der Bilder
   *
   * standardmäßig ist dies in base/files/images und dann mit möglichen Unterverzeichnissen
   * @var Webforge\Common\System\Directory
   */
  protected $directory;
  
  /**
   * @var array
   */
  protected $registeredCacheAdapters = array('thumbnail'=>'\Psc\Image\ThumbnailCacheAdapter');

  /**
   * @var array
   */
  protected $registeredTransformations = array('thumbnail'=>'\Psc\Image\ThumbnailTransformation');
  
  /**
   * @var Cache
   */
  protected $cache;
  
  /**
   * @var string
   */
  protected $entityName;
  
  /**
   * @var Imagine\Gd\Imagine
   */
  protected $imagine;
  
  /**
   * @var \Doctrine\ORM\EntityManager
   */
  protected $em;
  
  protected $cacheLog = array();
  
  /**
   * @param Cache $imagesCache in Falle eines FileCaches wird vor diesen kein "images" angehängt (als cache key)
   */
  public function __construct($imageEntityName = 'BasicImage', EntityManager $em, Dir $imagesDirectory = NULL, Cache $imagesCache = NULL) {
    $this->images = new ArrayCollection();
    $this->entityName = $imageEntityName;
    $this->em = $em;
    $this->imageRep = $this->em->getRepository($this->entityName);
    $this->directory = $imagesDirectory ?: PSC::get(PSC::PATH_FILES)->append('images/');
  
    $this->imagine = new Imagine();
    
    $this->cache = $imagesCache ?: new FileCache(PSC::get(PSC::PATH_CACHE)->append('images/'));
    $this->directory->create();
  }
  
  /**
   * Erstellt ein neues Bild und fügt es dem Manager hinzu
   *
   * Gibt das Bild um $imagineImage zurück
   * @return Image
   */
  public function store(ImagineImage $imagineImage, $label = NULL, $flags = 0x000000) {
    $hash = sha1($imagineImage->get('png'));
    
    if (($flags & self::IF_NOT_EXISTS) === self::IF_NOT_EXISTS) {
      try {
        $image = $this->load($hash);
        $file = $image->getSourceFile();
        
        if (!$file->exists()) {
          $file->getDirectory()->create();
          // das ist eigentlich SEHR unerwartet, weil dann die db nicht mit dem filesystem in sync ist:
          $image->setImagineImage($imagineImage);
          $imagineImage->save((string) $file);
          if ($file->getSize() <= 0) {
            throw new ProcessingException('Fehler beim (NEU-)Speichern von Bild: '.$file."\n".
                                          "Memory Usage: ".Code::getMemoryUsage()."\n".
                                          'Datei wurde von Imagine nicht geschrieben');
          }
          $this->em->persist($image);
        }
        
        return $image;
        
      } catch (\Psc\Image\NotFoundException $e) {
      }
    }
    
    $image = $this->newInstance();
    $image->setLabel(trim($label));
    $image->setImagineImage($imagineImage);
    
    /* filename */
    
    // wir speichern dumpf immer in png ab
    $file = $this->generateFile($hash, 'png');
    $image->setSourceFile($file); // damits schon gecached ist
    
    $rel = clone $file;
    $rel->makeRelativeTo($this->directory);
    $image->setSourcePath((string) $rel);
    
    /* binärdaten Physikalisch speichern */
    $image->getImagineImage()->save((string) $file);
    if ($file->getSize() <= 0) {
      throw new ProcessingException('Fehler beim Speichern von Bild: '.$file."\n".
                                    "Memory Usage: ".Code::getMemoryUsage()."\n".
                                    'Datei wurde von Imagine nicht geschrieben');
    }
    
    /* binärdaten hashen */
    $image->setHash($hash); // sha1_file((string) $file)

    /* Datenbank speichern */
    $this->em->persist($image);
    
    $this->doAttach($image);
    return $image;
  }
  
  /**
   * Speicherte eine "Version" des Images
   *
   * der Anwendungsfall ist z. B. ein Thumbnail.
   * dann ist $name 'thumbnail'. Das Bild wird im Filecache vom OriginalImage erzeugt und kann dann
   * Mithilfe dessen geladen werden.
   *
   * der ThumbnailCacheAdapter bestimmt dann die Kriterien für die Version des Bildes (z.b. höhe breite type des vergrößerung / verkleinerung, etc)
   *
   * wenn das Image noch nicht im Manager ist, wird versucht es hinzuzufügen. Dies kann nicht klappen - wenn das Bild noch nicht mit store() irgendwann abgespeichert wurde. Dann wird eine Exception geschmissen
   * @param ImagineImage $imageVersion z. B. das Thumbnail des Bildes
   * @retun Array $keys die Schlüssel die benutzt werden wenn man mit loadVersion() die Version wieder laden will
   */
  public function storeVersion(Image $image, ImagineImage $imageVersion, $type, Array $arguments = array()) {
    $this->attach($image);
   
    $ac = $this->getCacheAdapter($type);
    $keys = $cacheKeys = $ac->getCacheKeys($imageVersion, $image, $arguments);
    
    $keys = array_merge($keys, $this->getImageUniqueKeys($image));
    
    /* jetzt cachen wir das Bild in unserem FileCache (oder was auch immer für ein Cache) */
    $this->cacheLog('store: "'.implode(':',$keys).'"');
    $this->cache->store($keys, $imageVersion->get('png'));
    
    // double check writing:
    if (!$this->cache->hit($keys)) {
      throw new ProcessingException('Fehler beim Speichern von Bild: '.$image->getSourceFile()."\n".
                                    "Memory Usage: ".Code::getMemoryUsage()."\n".
                                    'ImageVersion wurde vom Cache nicht geschrieben (hit ist false)'
                                   );
    }
      
    
    return $cacheKeys; // das sind die keys ohne die imageUniques
  }
  
  /**
   * @param Image $image wird nicht überprüft ob es attached ist (das vorher machen)
   * @return array
   */
  protected function getImageUniqueKeys(Image $image) {
    /* zu diesen schlüsseln fügen wir den Dateinamen des Bildes hinzu */
    $sourceFile = $image->getSourceFile();
    
    // eigentlich den . abschneiden */
    $keys = array_slice($sourceFile->getDirectory()->getPathArray(),-1,1);
    
    // das ist das charDir und dazu kommt noch der Dateiname
    $keys[] = $sourceFile->getName(File::WITH_EXTENSION);
    return $keys;
  }
  
  /**
   * Lädt eine Version eines Bildes aus dem Cache
   *
   * die Version muss vorher natürlich mit storeVersion() erzeugt worden sein und
   * $keys muss von storeVersion zurückgegeben worden sein
   * 
   * kann die Version nicht geladen werden, wird $loaded auf FALSE gesetzt
   *
   * Zu beachten ist, dass $keys hier nicht direkt die Argumente z. B. für das erstellen eines Thumbs sind.
   * Man kann die Argumente mit dem CacheAdapter umwandeln lassen
   *
   * Beispiel: 
   * $loaded = FALSE;
   * $imageVersion = $manager->loadVersion($image, $keysFromAdapter, $loaded);
   * if (!$loaded) {
   *   $imageVersion = new Imagine\Image\ImageInterface() //... 
   *         
   *   $manager->storeVersion($image, $imageVersion, 'whatever');
   * }
   * // hier ist $imageVersion immer schön definiert
   *
   * @param Array $keys so wie der CacheAdapter sie zurückgibt. Der erste Key sollte der Name des Adapters sein
   * @return ImagineImage aber nur wenn es im cache ist, sonst ist es undefined was zurückgegeben wird
   */
  public function loadVersion(Image $image, Array $keys, &$loaded) {
    $this->attach($image);
    
    /* Keys die reinkommen sind sowas wie:
      array($version, param1, param2, param3)
    */
    $keys = array_merge($keys, $this->getImageUniqueKeys($image));
    /* jetzt ists:
      array($version, param1,param2,param3,..., $randomverzeichnis, $filename.'.jpg')
      o ä.
    */
    
    $dKeys = implode(':',$keys);
    $this->cacheLog('load: "'.$dKeys.'"');
    $contents = $this->cache->load($keys,$loaded);
    
    if ($loaded) {
      $this->cacheLog('hit : '.$dKeys);
      return $this->imagine->load($contents);
    } else {
      $this->cacheLog('miss : '.$dKeys);
    }
  }

  /**
   * Gibt die Version eines Bildes, die mit einer Transformation erzeugt werden kann zurück
   *
   * Das Bild wird automatisch gecached oder aus dem cache gelesen / oder in den Cache eingefügt
   * @param string $type muss als transformation und als cacheAdapter registriert sein
   * @return ImagineImage
   */
  public function getVersion(Image $image, $type, Array $arguments = array()) {
    $this->attach($image);
    $ca = $this->getCacheAdapter($type);
    
    $keys = $ca->convertArguments($arguments);
    array_unshift($keys,$type); //$version zu den Cache-Keys hinzufügen
    
    $loaded = FALSE;
    $imageVersion = $this->loadVersion($image, $keys, $loaded);
    if (!$loaded) {
      $tf = $this->getTransformation($type);
      
      try {
        $imageVersion = $tf->processArguments($image->getImagineImage(),$arguments);
      } catch (\Exception $e) {
        throw new ProcessingException('Fehler beim Convertieren von Bild: '.$image->getSourcePath()."\n".
                                    'Transformation: '.Code::getClass($tf).' '.Code::varInfo($arguments)."\n".
                                    "Memory Usage: ".Code::getMemoryUsage()."\n".
                                    $e->getMessage(),
                                    
                                    0, $e);
      }
      
      $this->storeVersion($image, $imageVersion, $type, $arguments);      
    }
    return $imageVersion;
  }
  
  
  /**
   * Gibt die URL zu einem Bild zurück (absolut zu htdocs)
   *
   * Alias /dimg muss auf das Cache verzeichnis gesetzt sein
   * @param type ist entweder ein CacheAdapater / Transformation Type oder "original"
   */
  public function getURL(Image $image, $type, Array $arguments = array()) {
    $this->attach($image);
    
    if ($type == 'original') {
      $parts = array_merge(array('images'), $this->getImageUniqueKeys($image));
      
    } else {
      $this->getVersion($image, $type, $arguments); // nur einfach erstellen
    
      $cacheKeys = $this->getCacheAdapter($type)->convertArguments($arguments);
      $parts = array_merge(array('dimg',$type), $cacheKeys, $this->getImageUniqueKeys($image));
    }
    
    return '/'.implode('/',$parts);
  }
  
  
  /**
   * Verwaltet das Bild im Manager
   *
   * attached ohne zu überprüfen
   */
  protected function doAttach(Image $image) {
    $this->addImage($image);
    $image->setImageManager($this);
    return $this;
  }

  /**
   * Überprüft das Bild und verwaltet es im Manager
   * @chainable
   */
  public function attach(Image $image) {
    if (!$this->isAttached($image)) {
      if ($image->getId() > 0) {
        $this->doAttach($image);
      } else {
        throw new \Psc\Exception('Das Bild muss vom ImageManager verwaltet werden, es kann aber nicht hinzugefügt werden, da es noch nicht gespeichert wurde. (id <= 0)');
      }
    }
    return $this;
  }
  
  protected function addImage(Image $image) {
    if (!$this->images->contains($image)) {
      $this->images->add($image);
    }
    return $this;
  }
  
  /**
   * @return bool
   */
  public function isAttached(Image $image) {
    return $this->images->contains($image);
  }
  
  /**
   * Lädt ein Bild aus der Datenbank
   * 
   * @params $input
   * 
   * @param int    $input die ID des Entities
   * @param string $input der gespeicherte sourcePath des Entities (muss / oder \ enthalten)
   * @param string $input der sha1 hash der Bildinformationen des OriginalBildes
   * @return Psc\Image\Image
   * @throws Psc\Image\NotFoundException
   */
  public function load($input) {
    try {
      if (is_numeric($input)) {
        $image = $this->imageRep->hydrate((int) $input);
      } elseif (is_string($input) && (mb_strpos($input,'/') !== FALSE || mb_strpos($input,'\\') !== FALSE)) {
        $image = $this->imageRep->hydrateBy(array('sourcePath'=>(string) $input));
      } elseif (is_string($input)) { // hash
        
        $image = $this->imageRep->hydrateBy(array('hash'=>(string) $input));
      } elseif ($input instanceof Image) {
        $image = $input;
      } elseif ($input instanceof ImagineImage) {
        throw new \Psc\Exception('von einer ImagineResource kann kein Bild geladen werden');
      } else {
        throw new \Psc\Exception('Input kann nicht analyisiert werden: '.Code::varInfo($input));
      }
      
      $this->attach($image);
      return $image;
    
    } catch (\Psc\Doctrine\EntityNotFoundException $e) {
      $e = new NotFoundException('Image nicht gefunden: '.Code::varInfo($input),1, $e);
      $e->searchCriteria = $input;
      throw $e;
    }
  }
  
  public function attachImages($images) {
    foreach ($images as $image) {
      $this->attach($image);
    }
    return $images;
  }
  
  /**
   * Lädt die Bildinfos aus dem FileSystem (oder sonstwo her)
   */
  public function getImagineImage(Image $image) {
    
    $file = $image->getSourceFile();  // nicht hier $this->getSourceFile aufrufen, da wir dann mit den caches in image durcheinander kommen
    try {
      $imagineImage = $this->imagine->open((string) $file); // das schmeisst exceptions, wenn es die Datei z. B. nicht gibt
    } catch (\Imagine\Exception\RuntimeException $e) {
      $e = new LoadException('Das Bild '.$image.' konne nicht aus dem Datei-System geladen werden! ',0,$e);
      $e->file = $file;
      throw $e;
    }
    
    return $imagineImage;
  }
  
  public function remove(Image $image) {
    $this->em->remove($image);
    return $this;
  }

  /**
   * Wird vom Entity ausgeführt, wenn es gelöscht wird
   *
   * löscht die Binärdaten des Bildes
   */
  public function listenRemoved(Image $image) {
    $file = $image->getSourceFile();
    if ($file->exists())
      $file->delete();
      
    /* @TODO cleanup in cache! */
    return $this;
  }
  
  public function getSourceFile(Image $image) {
    $f = new File($this->directory.str_replace(array('\\','/'),DIRECTORY_SEPARATOR, $image->getSourcePath()));
    $f->resolvePath();
    return $f;
  }
  
  /**
   * @return ImagineImage
   */
  public function createImagineImage($input) {
    if ($input instanceof File) {
      return $this->imagine->open((string) $input);
    } elseif (is_string($input)) {
      return $this->imagine->load($input);
    } else {
      throw $this->invalidArgument(1, $input, 'Webforge\Common\System\File|string', __FUNCTION__);
    }
  }
  
  /**
   * @return Image
   */
  protected function newInstance() {
    $c = $this->entityName;
    return new $c();
  }
  
  /**
   * @return File
   */
  protected function generateFile($hash, $ext = 'png') {
    if (mb_strlen($hash) <= 1) {
      throw new \InvalidArgumentException('Hash kann nicht kürzer als 2 Chars sein! '.Code::varInfo($hash));
    }
    
    /* Verzeichnisse von a-z erstellen */

    // es ist schneller das subverzeichnis beim erstellen der Datei zu erstellen, als das wir einmal alle erstellen,
    // wenn wir hier eh jedes mal prüfen ob wir "a" haben (und dann alle erzeugen)
    // denn das create benutzt beim a-z erstellen 24+10 mal file_exists und sowieso für jeden generateFile aufruf einmal exists
    //if (!$this->directory->sub('a/')->exists()) {
    //  foreach ($az as $char) {
    //    $this->directory->sub($char.'/')->create();
    //  }
    //}
    
    // der hash kann auch mit 0-9 anfangen
    $charDir = $this->directory->sub(mb_substr($hash,0,1).'/')->create();
    $file = new File($charDir, $hash.'.'.$ext);
    
    return $file;
  }
  
  public function registerTransformation(Transformation $transformation, $name = NULL) {
    if (!isset($name)) $name = Code::getClassName(Code::getClass($transformation));
    $this->registeredTransformations[$name] = $transformation;
    return $this;
  }

  public function getTransformation($transformationName) {
    if (!array_key_exists($transformationName,$this->registeredTransformations)) {
      throw new \Psc\Exception('Transformation mit dem Namen: '.$transformationName.' nicht gefunden. Wurde dieser Name registriert?');
    }
    $c = $this->registeredTransformations[$transformationName];
    return new $c();
  }
  
  public function registerCacheAdapter(CacheAdapter $adapter, $name = NULL) {
    if (!isset($name)) $name = Code::getClassName(Code::getClass($adapter));
    $this->registeredCacheAdapters[$name] = $adapter;
    return $this;
  }
  
  public function getCacheAdapter($adapterName) {
    if (!array_key_exists($adapterName,$this->registeredCacheAdapters)) {
      throw new \Psc\Exception('CacheAdapter mit dem Namen: '.$adapterName.' nicht gefunden. Wurde der Adapter registriert?');
    }
    $c = $this->registeredCacheAdapters[$adapterName];
    return new $c();
  }
  
  public function flush() {
    $this->em->flush();
    return $this;
  }
  
  public function clear() {
    $this->em->clear();
    unset($this->images);
    $this->images = new ArrayCollection();
    return $this;
  }

  public function getRepository() {
    return $this->imageRep;
  }
  
  public function cacheLog($msg) {
    $this->cacheLog[] = $msg;
    return $this;
  }
  
  public function getDirectory() {
    return $this->directory;
  }
}
?>