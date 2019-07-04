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

use OstDynamicCategories\Bundle\SearchBundle\Condition\SearchLiveCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

abstract class SearchLiveConditionHandler implements ConditionHandlerInterface
{
    protected $searchFields = [];

    /**
     * Handles the passed condition object.
     * Extends the provided query builder with the specify conditions.
     * Should use the andWhere function, otherwise other conditions would be overwritten.
     *
     * @param ConditionInterface $condition
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    )
    {
        if (!($condition instanceof SearchLiveCondition)) {
            return;
        }

        /** @var SearchLiveCondition $condition */
        $term = $condition->getTerm();
        $orTerms = explode(';', $term);

        $parameter = [];
        $andConditions = [];
        foreach ($this->searchFields as $searchField) {
            $orConditions = [];
            foreach ($orTerms as $orTerm) {
                if ($orTerm === '') {
                    continue;
                }

                $andTerms = explode(' ', $orTerm);

                $likeConditions = [];
                foreach ($andTerms as $andTerm) {
                    $id = 'term' . random_int(0, PHP_INT_MAX - 1);

                    $andTerm = strtoupper($andTerm);

                    if (strpos($andTerm, '^') === 0) {
                        $andTerm = ' ' . substr($andTerm, 1);
                    }

                    if (strpos($andTerm, '$') === strlen($andTerm) - 1) {
                        $andTerm = substr($andTerm, 0, -1) . ' ';
                    }

                    if (strpos($andTerm, '!') !== 0) {
                        $andTerm = '%' . $andTerm;
                    }

                    if (strpos($andTerm, '!') !== strlen($andTerm) - 1) {
                        $andTerm .= '%';
                    }

                    $parameter[$id] = $andTerm;

                    if ($condition->isNot()) {
                        $likeConditions[] = $query->expr()->andX(
                            $query->expr()->notLike($searchField, ':' . $id),
                            $query->expr()->neq($searchField, ':' . $id)
                        );
                    } else {
                        $likeConditions[] = $query->expr()->orX(
                            $query->expr()->like($searchField, ':' . $id),
                            $query->expr()->eq($searchField, ':' . $id)
                        );
                    }
                }

                $orConditions[] = $query->expr()->andX(...$likeConditions);
            }
            if ($condition->isNot()) {
                $andConditions[] = $query->expr()->andX(...$orConditions);
            } else {
                $andConditions[] = $query->expr()->orX(...$orConditions);
            }
        }

        foreach ($parameter as $parameterName => $term) {
            $query->setParameter($parameterName, trim($term));
        }

        $query->andWhere($query->expr()->orX(...$andConditions));
    }
}
