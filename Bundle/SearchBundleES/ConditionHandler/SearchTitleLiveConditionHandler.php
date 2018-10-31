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

namespace OstDynamicCategories\Bundle\SearchBundleES\ConditionHandler;

use OstDynamicCategories\Bundle\SearchBundle\Condition\SearchTitleLiveCondition;
use Shopware\Bundle\SearchBundle\CriteriaPartInterface;

class SearchTitleLiveConditionHandler extends SearchLiveConditionHandler
{
    /**
     * Validates if the criteria part can be handled by this handler
     *
     * @param CriteriaPartInterface $criteriaPart
     *
     * @return bool
     */
    public function supports(CriteriaPartInterface $criteriaPart)
    {
        return $criteriaPart instanceof SearchTitleLiveCondition;
    }
}
