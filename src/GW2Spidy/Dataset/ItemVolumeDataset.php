<?php

namespace GW2Spidy\Dataset;

use GW2Spidy\Application;

use GW2Spidy\DB\Item;

use \DateTime;
use \DateInterval;
use \DateTimeZone;
use GW2Spidy\DB\BuyListingQuery;
use GW2Spidy\DB\SellListingQuery;

class ItemVolumeDataset extends ItemDataset {
    /**
     * update the current dataset with new values from the database
     *  if posible only with values since our lastUpdated moment
     */
    public function updateDataset() {
        if ($this->updated) {
            return;
        }

        $limit = 5000;
        $end   = null;
        $start = $this->lastUpdated;

        $q = $this->type == self::TYPE_SELL_LISTING ? SellListingQuery::create() : BuyListingQuery::create();
        $q->select(array('listingDatetime'))
          ->withColumn('SUM(quantity)', 'quantity')
          ->groupBy('listingDatetime')
          ->filterByItemId($this->itemId);

        // only retrieve new ticks since last update
        if ($start) {
            $q->filterByListingDatetime($start, \Criteria::GREATER_THAN);
        }

        // fake 5 days ago so we can test new ticks being added
        // $fake = new DateTime();
        // $fake->sub(new DateInterval('P5D'));
        // $q->filterByRateDatetime($fake, \Criteria::LESS_THAN);

        // ensure ordered data, makes our life a lot easier
        $q->orderByListingDatetime(\Criteria::ASC);

        // limit so on a complete cache flush we can ease into building up the cache again
        $q->limit($limit);

        // loop and process ticks
        $listings = $q->find();
        foreach ($listings as $listing) {
            $date = new DateTime("{$listing['listingDatetime']}");
            $rate = intval($listing['quantity']);

            $end = $date;

            $this->processTick($date, $rate);
        }

        if (!($this->uptodate = count($listings) != $limit)) {
            $app = Application::getInstance();
            $app['no_cache'] = true;
        }

        // update for next time
        $this->updated = true;
        if ($end) {
            $this->lastUpdated = $end;
        }
    }
}
