<?php

/**
 * Lightweight Category Scanner
 * @license GPL 3
 * @author simon04
 */

error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');

// MediaWiki namespaces
define('NS_MAIN', 0);
define('NS_CATEGORY', 14);


class CategoryLister {

  public function __construct($lang) {
    $ts_pw = posix_getpwuid(posix_getuid());
    $ts_mycnf = parse_ini_file($ts_pw['dir'] . "/.my.cnf");
    $this->db = new PDO("mysql:host={$lang}wiki-p.rrdb.toolserver.org;dbname={$lang}wiki_p",
      $ts_mycnf['user'], $ts_mycnf['password']);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function listContent($cat, $ns) {
    $cat = str_replace(' ', '_', $cat);
    $sql = "select p.page_title from categorylinks c join page p on c.cl_from = p.page_id where c.cl_to = :cat and p.page_is_redirect = 0 and p.page_namespace = :ns";
    $q = $this->db->prepare($sql);
    $q->execute(array(':cat' => $cat, ':ns' => $ns));
    return $q->fetchAll(PDO::FETCH_COLUMN);
  }

  public function listRecursively($cat, $depth = 3) {
    $pages = $this->listContent($cat, NS_MAIN);
    if ($depth > 0) {
      foreach ($this->listContent($cat, NS_CATEGORY) as $subcat) {
        $pages = array_merge($pages, $this->listRecursively($subcat, $depth - 1));
      }
    }
    return $pages;
  }

}

if (!$_REQUEST['lang'] || !$_REQUEST['cat']) {
  header('HTTP/1.1 400');
  echo "Lightweight Category Scanner by simon04 (licensed GPL 3)\n";
  echo "Required parameters: lang, cat\n";
  echo "Optional parameters: depth";
  exit();
}

$lister = new CategoryLister($_REQUEST['lang']);
$r = $lister->listRecursively($_REQUEST['cat'],
  $_REQUEST['depth'] ? intval($_REQUEST['depth']) : 3);

if (FALSE !== strpos($_SERVER['HTTP_ACCEPT'], 'application/json')) {
  header('Content-Type: application/json');
  echo json_encode($r);
} else {
  header('Content-Type: text/plain; charset=utf-8');
  echo implode($r, "\n");
}
