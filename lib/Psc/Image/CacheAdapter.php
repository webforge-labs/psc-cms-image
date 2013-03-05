<?php

namespace Psc\Image;

interface CacheAdapter {
  
  /**
   * Gibt die eindeutigen Schlüssel für eine Cache Operation für $imageVersion zurück
   *
   * an die Schlüssel wird noch der "Dateiname" des Bildes angehängt. Es reicht also die Kriterien für das Cachen des Bildes
   * zu bestimmen, wenn man davon ausgeht, dass alle Bilder einen unique "Dateinamen" haben.
   * Also bei Thumbnail wären array('thumbnail','verkleinerungsMethode','breite','höhe')
   * völlig ausreichend. Da dann auf der ebene "Höhe" alle Dateinamen wieder unique sind
   *
   * der erste Key in den CacheKeys sollte der Alias (Adaptername) des CacheAdapters sein
   * @param ImagineImage $imageVersion die modifizierte Version des Bildes in $image, die gespeichert werden soll
   * @param Image $image die Originalversion (die bereits gespeichert wurde) des Bildes
   */
  public function getCacheKeys(\Imagine\Image\ImageInterface $imageVersion, Image $image);
  
  public function convertArguments(Array $arguments);
}

?>