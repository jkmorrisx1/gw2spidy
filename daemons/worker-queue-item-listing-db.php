<?php

use GW2Spidy\DB\ItemQuery;

use GW2Spidy\Dataset\DatasetManager;
use GW2Spidy\Dataset\ItemDataset;

require dirname(__FILE__) . '/../autoload.php';

$t = microtime(true);
function mytime() {
    $r = (microtime(true) - $GLOBALS['t']);
    $GLOBALS['t'] = microtime(true);

    return $r;
}

$q = ItemQuery::create();
if (isset($argv[1])) {
    $q->filterByDataId($argv[1]);
}

foreach ($q->find() as $item) {
    $cleaner = new ItemDataset($item->getDataId(), ItemDataset::TYPE_SELL_LISTING);
    $cleaner->clean();

    var_dump(mytime());
}