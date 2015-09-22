<?php

namespace akavov\countries\components;

use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii\db\Query;


class CountriesBehavior extends Behavior
{
    /**
     * @var boolean whether to return tags as array instead of string
     */
    public $countryValuesAsArray = true;
    /**
     * @var string the countries relation name
     */
    public $countryRelation = 'countries';
    /**
     * @var string the countries model value attribute name
     */
    public $countryValueAttribute = 'name_en';
    /**
     * @var string the countries model value attribute name
     */
    public $countryFlagAttribute = 'alpha';
    /**
     * @var string[]
     */
    private $_countryValues;
    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_INSERT    => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE    => 'afterSave',
            ActiveRecord::EVENT_BEFORE_DELETE   => 'beforeDelete',
        ];
    }

    /**
     * Returns tags.
     * @param boolean|null $asArray
     * @return string|string[]
     */
    public function getCountryValues($asArray = null)
    {
        if (!$this->owner->getIsNewRecord() && $this->_countryValues === null) {
            $this->_countryValues = [];

            /* @var ActiveRecord $tag */
            foreach ($this->owner->{$this->countryRelation} as $country) {
                $this->_countryValues[] = $country->getAttribute($this->countryValueAttribute);
            }
        }

        if ($asArray === null) {
            $asArray = $this->countryValuesAsArray;
        }

        if ($asArray) {
            return $this->_countryValues === null ? [] : $this->_countryValues;
        } else {
            return $this->_countryValues === null ? '' : implode(', ', $this->_countryValues);
        }
    }

    /**
     * Sets tags.
     * @param string|string[] $values
     */
    public function setCountryValues($values)
    {
        $this->_countryValues = $this->filterCountryValues($values);
    }

    /**
     * Adds tags.
     * @param string|string[] $values
     */
    public function addCountryValues($values)
    {
        $this->_countryValues = array_unique(array_merge($this->getCountryValues(true), $this->filterCountryValues($values)));
    }

    /**
     * Removes tags.
     * @param string|string[] $values
     */
    public function removeCountryValues($values)
    {
        $this->_countryValues = array_diff($this->getCountryValues(true), $this->filterCountryValues($values));
    }

    /**
     * Removes all tags.
     */
    public function removeAllCountryValues()
    {
        $this->_countryValues = [];
    }

    /**
     * Returns a value indicating whether tags exists.
     * @param string|string[] $values
     * @return boolean
     */
    public function hasCountryValues($values)
    {
        $countryValues = $this->getCountryValues(true);

        foreach ($this->filterCountryValues($values) as $value) {
            if (!in_array($value, $countryValues)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return void
     */
    public function afterSave()
    {
        if ($this->_countryValues === null) {
            return;
        }

        if (!$this->owner->getIsNewRecord()) {
            $this->beforeDelete();
        }

        $countryRelation = $this->owner->getRelation($this->countryRelation);
        $pivot = $countryRelation->via->from[0];
        /* @var ActiveRecord $class */
        $class = $countryRelation->modelClass;
        $rows = [];

        foreach ($this->_countryValues as $value) {
            /* @var ActiveRecord $tag */
            $country = $class::findOne([$this->countryValueAttribute => $value]);

            if ($country === null) {
                $country = new $class();
                $country->setAttribute($this->countryValueAttribute, $value);
            }

            if ($country->save()) {
                $rows[] = [$this->owner->getPrimaryKey(), $country->getPrimaryKey()];
            }
        }

        if (!empty($rows)) {
            $this->owner->getDb()
                ->createCommand()
                ->batchInsert($pivot, [key($countryRelation->via->link), current($countryRelation->link)], $rows)
                ->execute();
        }
    }

    /**
     * @return void
     */
    public function beforeDelete()
    {
        $countryRelation = $this->owner->getRelation($this->countryRelation);
        $pivot = $countryRelation->via->from[0];


        /* @var ActiveRecord $class */
        $class = $countryRelation->modelClass;

        $pks = (new Query())
            ->select(current($countryRelation->link))
            ->from($pivot)
            ->where([key($countryRelation->via->link) => $this->owner->getPrimaryKey()])
            ->column($this->owner->getDb());

//            if (!empty($pks)) {
//                $class::updateAllCounters([$this->tagFrequencyAttribute => -1], ['in', $class::primaryKey(), $pks]);
//            }


        $this->owner->getDb()
            ->createCommand()
            ->delete($pivot, [key($countryRelation->via->link) => $this->owner->getPrimaryKey()])
            ->execute();
    }

    /**
     * Filters tags.
     * @param string|string[] $values
     * @return string[]
     */
    public function filterCountryValues($values)
    {
        return array_unique(preg_split(
            '/\s*,\s*/u',
            preg_replace('/\s+/u', ' ', is_array($values) ? implode(',', $values) : $values),
            -1,
            PREG_SPLIT_NO_EMPTY
        ));
    }

}
