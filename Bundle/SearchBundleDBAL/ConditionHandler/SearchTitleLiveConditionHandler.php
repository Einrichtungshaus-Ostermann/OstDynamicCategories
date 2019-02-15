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

namespace OstDynamicCategories\Bundle\SearchBundleDBAL\ConditionHandler;

use OstDynamicCategories\Bundle\SearchBundle\Condition\SearchTitleLiveCondition;
use OstDynamicCategories\Bundle\SearchBundle\Condition\SearchTitleNotLiveCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class SearchTitleLiveConditionHandler extends SearchLiveConditionHandler
{
    protected $searchFields = [
        'UPPER(product.name)',
    ];

    /**
     * Checks if the passed condition can be handled by this class.
     *
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof SearchTitleLiveCondition || $condition instanceof SearchTitleNotLiveCondition;
    }
}
