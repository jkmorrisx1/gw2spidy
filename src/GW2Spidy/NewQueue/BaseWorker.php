<?php

namespace GW2Spidy\NewQueue;

use \DateTime;
use \DateInterval;
use \Criteria;
use \Exception;

use GW2Spidy\Util\Functions;

use GW2Spidy\DB\Item;
use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\ItemSubType;
use GW2Spidy\DB\BuyListing;
use GW2Spidy\DB\SellListing;
use GW2Spidy\DB\ItemQuery;
use GW2Spidy\DB\BuyListingQuery;
use GW2Spidy\DB\SellListingQuery;
use GW2Spidy\TradingPostSpider;


class BaseWorker {
    const ERROR_CODE_NO_LONGER_EXISTS = 444441;

    protected $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    protected function processListingsFromItemData($itemData, $item = null, $save = true) {
        $now  = new DateTime();
        $item = $item ?: ItemQuery::create()->findPK($itemData['data_id']);

        // ensure the expected keys are there
        $itemData['sale_availability']    = isset($itemData['sale_availability'])    ? $itemData['sale_availability']    : 0;
        $itemData['min_sale_unit_price']  = isset($itemData['min_sale_unit_price'])  ? $itemData['min_sale_unit_price']  : 0;
        $itemData['offer_availability']   = isset($itemData['offer_availability'])   ? $itemData['offer_availability']   : 0;
        $itemData['max_offer_unit_price'] = isset($itemData['max_offer_unit_price']) ? $itemData['max_offer_unit_price'] : 0;

        // sale
        $item->setSaleAvailability($itemData['sale_availability']);
        $sellListing = new SellListing();
        $sellListing->setItem($item);
        $sellListing->setListingDatetime($now);

        if ($itemData['sale_availability'] == 0 || $itemData['min_sale_unit_price'] == 0) {
            $sellListing->setQuantity(0);
            $sellListing->setUnitPrice($item->getMinSaleUnitPrice());
            $sellListing->setListings(0);
        } else {
            $item->setMinSaleUnitPrice($itemData['min_sale_unit_price']);
            $sellListing->setQuantity($itemData['sale_availability']);
            $sellListing->setUnitPrice($itemData['min_sale_unit_price']);
            $sellListing->setListings(1);
        }
        $sellListing->save();

        // offer
        $item->setOfferAvailability($itemData['offer_availability']);
        $buyListing = new BuyListing();
        $buyListing->setItem($item);
        $buyListing->setListingDatetime($now);

        if ($itemData['offer_availability'] == 0 || $itemData['max_offer_unit_price'] == 0) {
            $buyListing->setQuantity(0);
            $buyListing->setUnitPrice($item->getMaxOfferUnitPrice());
            $buyListing->setListings(0);
        } else {
            $item->setMaxOfferUnitPrice($itemData['max_offer_unit_price']);
            $buyListing->setQuantity($itemData['offer_availability']);
            $buyListing->setUnitPrice($itemData['max_offer_unit_price']);
            $buyListing->setListings(1);
        }
        $buyListing->save();

        // save if not done externally
        if ($save) {
            $item->save();
        }
    }
}

