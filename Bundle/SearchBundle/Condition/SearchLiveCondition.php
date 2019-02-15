<?php declare(strict_types=1);
/**
 * Einrichtungshaus Ostermann GmbH & Co. KG - Dynamic Categories
 *
 * Adds the functionality to create ProductStream like Streams as Category definition.
 *
 * @package   OstDynamicCategories
 *
 * @author    Tim Windelschmidt <tim.windelschmidt@ostermann.de>
 * @copyright 2018 Einrichtungshaus Ostermann GmbH & Co. KG
 * @license   proprietary
 */

namespace OstDynamicCategories\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

abstract class SearchLiveCondition implements ConditionInterface
{
    /**
     * @var string
     */
    private $term;
    /**
     * @var bool
     */
    protected $not = false;

    /**
     * SearchCondition constructor.
     *
     * @param string $term
     * @param bool $not
     */
    public function __construct(string $term)
    {
        $this->term = $term;
    }

    /**
     * Defines the unique name for the facet for re identification.
     *
     * @return string
     */
    public function getName()
    {
        $path = explode('\\', static::class);
        return array_pop($path);
    }

    /**
     * @return string
     */
    public function getTerm()
    {
        return $this->term;
    }

    /**
     * @return bool
     */
    public function isNot()
    {
        return $this->not;
    }
}
