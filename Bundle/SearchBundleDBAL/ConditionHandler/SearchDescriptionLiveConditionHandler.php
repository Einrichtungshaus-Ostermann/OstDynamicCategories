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

use OstDynamicCategories\Bundle\SearchBundle\Condition\SearchDescriptionLiveCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class SearchDescriptionLiveConditionHandler extends SearchLiveConditionHandler
{
    protected $searchFields = [
        'UPPER(product.description_long)',
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
        return $condition instanceof SearchDescriptionLiveCondition;
    }
}
