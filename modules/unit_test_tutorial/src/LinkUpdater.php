<?php

namespace Drupal\unit_test_tutorial;

use \Drupal\Core\Database\Database;


class LinkUpdater {
  /**
   * List of link patterns.
   *
   * Note that it is very important for this to be sorted
   * from most specific to least specific patterns in order for
   * some methods in this class to work properly.
   *
   * @var array
   */
  private $linkPatterns = array(
    // Internal link, with absolute URL
    'internal_absolute' => '/((http|https):\/\/([a-z]+).tek.com)(\/.*)/',
    // Internal node link. i.e. internal: /node/1234.
    'node_internal' => '/(internal:)?\/?node\/([0-9]+)/',
    // External link.
    'external' => '/(http|https):\/\/(.*)/',
    // Other internal links.
    'internal_other' => '/(.*)/',
  );

  /**
   * LinkHelper constructor.
   */
  public function __construct() {

  }

  /**
   * Compares link against several patterns and returns type of link.
   *
   * @param string $url
   *   Relative or absolute URL string.
   *
   * @return bool|string
   *   Returns link type, or false if no match was found.
   */
  public function getLinkType($url) {
    $type = FALSE;
    foreach ($this->linkPatterns as $pattern_type => $pattern) {
      if (preg_match($pattern, $url)) {
        $type = $pattern_type;
        break;
      }
    }

    return $type;
  }

  /**
   * Method for replacing Internal Node Links.
   *
   * Example: internal:node/643
   * For these links, we will update the NID to the D8 node and update the
   * pattern to match the D8 equivalent.
   *
   * If the source node has not yet been migrated, we will also create a stub.
   *
   * @param string $url
   *   URL string to be processed.
   *
   * @return string
   *   Processed URL string.
   */
  public function replaceNodeInternalLink($url) {
    $database = Database::getConnection('default', 'default');
    $legacy_db = Database::getConnection('legacy', 'legacy');

    // Extract Legacy Node ID from URL.
    preg_match($this->linkPatterns['node_internal'], $url, $matches);

    if (empty($matches[2])) {
      return $url;
    }

    $legacy_nid = $matches[2];

    // Get node type
    $query = $legacy_db->select('node', 'n');
    $query->fields('n', array('type'));
    $query->condition('n.nid', $legacy_nid);
    $result = $query->execute()->fetchAssoc();
    if ($result['type']) {
      $node_type = $result['type'];
    }
    else {
      return FALSE;
    }

    // Get node ID.
    $d8_nid = NULL;
    $table_name = 'migrate_map_upgrade_d6_node_' . $node_type;
    if ($database->schema()->tableExists($table_name)) {
      $query = $database->select($table_name, 'mapping');
      $query->fields('mapping', array('destid1'));
      $query->condition('mapping.sourceid1', $legacy_nid);
      $result = $query->execute()->fetchAssoc();
      if ($result['destid1']) {
        $d8_nid = $result['destid1'];
      }
    }

    if ($d8_nid) {
      $url = '/node/' . $d8_nid;
    }
    else {
      return FALSE;
    }

    return $url;
  }

}