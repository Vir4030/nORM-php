<?php
class NormJS {

  const EOL = "\r\n";
  
  public static function echoln($contents = '') {
    echo($contents.self::EOL);
  }
  
  /**
   * Outputs Javascript which converts the given item to a Javascript object with the given name.
   *
   * @param DBEntity $item
   * @param string $javascriptName
   */
  public static function ENTITY_TO_JAVASCRIPT($item, $javascriptName) {
    self::echoln($javascriptName.' = new '.get_class($item).'();');
    if ($item->getId())
      self::echoln($javascriptName.'.set_id('.$item->getId().');');
    foreach ($item->getFieldNames() AS $field) {
      $value = $item->__get($field);
      if ($value !== null)
        self::echoln($javascriptName.'.'.$field.' = '.$item::DB()->quote($value, $item::requiresQuoting($field)).';');
    }
  }
  
  /**
   * Outputs Javascript which converts an array of PHP entities to Javascript entities.
   *
   * This method will 'var' declare a local variable 'html_class_temp_entity' to use as it builds the objects.
   *
   * @param DBEntity[] $items
   * @param string $javascriptName name of array to fill
   */
  public static function ENTITIES_TO_JAVASCRIPT($items, $javascriptName) {
    $localVariable = 'html_class_temp_entity';
    self::echoln('var '.$localVariable.';');
    self::echoln($javascriptName.' = [];');
    foreach ($items AS $item) {
      self::echoln();
      self::ENTITY_TO_JAVASCRIPT($item, $localVariable);
      self::echoln($javascriptName.'.push('.$localVariable.');');
    }
  }
  
}
