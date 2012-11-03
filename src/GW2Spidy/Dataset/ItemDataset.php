<?php

namespace GW2Spidy\Dataset;

use GW2Spidy\Application;

use GW2Spidy\DB\Item;

use \DateTime;
use \DateInterval;
use \DateTimeZone;
use GW2Spidy\DB\BuyListing;
use GW2Spidy\DB\SellListing;
use GW2Spidy\DB\BuyListingQuery;
use GW2Spidy\DB\SellListingQuery;

class ItemDataset {
    /*
     * different posible type of datasets we can have
     */
    const TYPE_SELL_LISTING = 'sell_listing';
    const TYPE_BUY_LISTING  = 'buy_listing';

    /*
     * just easy constants to make the code more readable
     */
    const TS_ONE_HOUR = 3600;
    const TS_ONE_DAY  = 86400;
    const TS_ONE_WEEK = 604800;

    /**
     * @var  int    $itemId
     */
    protected $itemId;

    /**
     * one of the self::TYPE_ constants
     * @var $type
     */
    protected $type;

    /*
     * helper methods to round timestamps by hour / day / week
     */
    public static function tsHour($ts) {
        return ceil($ts / self::TS_ONE_HOUR) * self::TS_ONE_HOUR;
    }
    public static function tsDay($ts) {
        return ceil($ts / self::TS_ONE_DAY) * self::TS_ONE_DAY;
    }
    public static function tsWeek($ts) {
        return ceil($ts / self::TS_ONE_WEEK) * self::TS_ONE_WEEK;
    }

    /**
     * @param  int       $itemId
     * @param  string    $type        should be one of self::TYPE_
     */
    public function __construct($itemId, $type) {
        $this->itemId = $itemId;
        $this->type = $type;
    }

    public function clean() {
        $con = \Propel::getConnection();

    $con->setLogLevel(\Propel::LOG_DEBUG);
    $con->useDebug(true);

        $q = $this->type == self::TYPE_SELL_LISTING ? SellListingQuery::create() : BuyListingQuery::create();
        $q->select(array('id', 'listingDatetime'))
          ->withColumn('MIN(unit_price)', 'unit_price')
          ->withColumn('SUM(listings)', 'listings')
          ->withColumn('SUM(quantity)', 'quantity')
          // ->filterByListingDatetime("2012-10-14 00:00:00", \Criteria::GREATER_EQUAL)
          // ->filterByListingDatetime("2012-10-15 00:00:00", \Criteria::LESS_THAN)
          ->groupBy('listingDatetime')
          ->filterByItemId($this->itemId);

        // ensure ordered data, makes our life a lot easier
        $q->orderByListingDatetime(\Criteria::ASC);

        $listings = $q->find();

        $ticks = array();
        $tsByHour = array();
        foreach ($listings as $listing) {
            $date = new DateTime("{$listing['listingDatetime']}");
            $ts   = $date->getTimestamp();
            $tsHr = self::tsHour($ts);
            
            $ticks[$ts] = $listing;
            $tsByHour[$tsHr][] = $ts;
        }

        $thres = self::tsHour(time() - self::TS_ONE_WEEK);
        foreach ($tsByHour as $tsHr => $tss) {
            if ($tsHr < $thres && count($tss) > 1) {
                $hourRates = array();
                $hourQuantities = array();
                $hourListings = array();
                $hourIds = array();

                foreach ($tss as $ts) {
                    $hourRates[] = $ticks[$ts]['unit_price'];
                    $hourQuantities[] = $ticks[$ts]['quantity'];
                    $hourListings[] = $ticks[$ts]['listings'];
                    $hourIds[] = $ticks[$ts]['id'];
                }

                $hourRAvg = array_sum($hourRates) / count($hourRates);
                $hourQAvg = array_sum($hourQuantities) / count($hourQuantities);
                $hourLAvg = array_sum($hourListings) / count($hourListings);

                $con->beginTransaction();

                $new = $this->type == self::TYPE_SELL_LISTING ? new SellListing() : new BuyListing();
                $new->setListingDatetime(date("Y-m-d H:i:s", $tsHr));
                $new->setItemId($this->itemId);
                $new->setUnitPrice($hourRAvg);
                $new->setQuantity($hourQAvg);
                $new->setListings($hourLAvg);
                $new->save();

                $q = $this->type == self::TYPE_SELL_LISTING ? new SellListingQuery() : new BuyListingQuery();
                $q->filterById($hourIds, \Criteria::IN)
                  ->delete();

                $con->commit();
            }
        }
    }
}
