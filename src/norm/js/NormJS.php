<?php
class NormJS {

  const EOL = "\r\n";
  
  public static function echoln($contents = '') {
    echo($contents.self::EOL);
  }
  
  /**
   * Outputs Javascript which creates the given item as a Javascript object and stores it in the JS DBStore.
   *
   * @param DBEntity $item
   * @param string $javascriptName
   */
  private static function CREATE_JS($item, $assign = '') {
    if (!$item->getId())
      throw new Exception('cannot export an item without an ID');
    
    $js = 'new '.get_class($item).'('.$item->getId().')';
    foreach ($item->getFieldNames() AS $field) {
      $value = $item->__get($field);
      if ($value !== null)
        $js .= '.set_'.$field.'('.$item::DB()->quote($value, $item::requiresQuoting($field)).')';
    }
    
    foreach ($item->getOwnedData() AS $data) {
      foreach ($data AS $subitem) {
        $js .= '.add_owned('.get_class($subitem).', '.self::CREATE_JS($subitem).')';
      }
    }
    
    return $js;
  }
  
  /**
   * Instantiates the given item or items in Javascript by outputting the javascript to do so.
   *
   * @param DBEntity|DBEntity[] $items
   */
  public static function LOAD_JS($items) {
    if (!is_array($items))
      $items = [ $items ];
    foreach ($items AS $item) {
      self::echoln(self::CREATE_JS($item).';');
      self::echoln('/* '.$item->saveToJson().' */');
    }
  }
  
}
