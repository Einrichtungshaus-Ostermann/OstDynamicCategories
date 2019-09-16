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

namespace OstDynamicCategories\Commands;

use Doctrine\DBAL\DBALException;
use Exception;
use OstDynamicCategories\Bundle\SearchBundle\Condition\NoCategoryLiveCondition;
use PDO;
use Shopware\Bundle\ESIndexingBundle\Struct\Backlog;
use Shopware\Bundle\ESIndexingBundle\Subscriber\ORMBacklogSubscriber;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Service\CategoryServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Models\ProductStream\ProductStream;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function count;

class WriteCategoryToArticlesCommand extends ShopwareCommand
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var ContextServiceInterface
     */
    private $contextService;

    /**
     * @var QueryBuilderFactoryInterface
     */
    private $queryBuilderFactory;

    /**
     * @var CategoryServiceInterface
     */
    private $categoryService;

    /**
     * @var ShopContext
     */
    private $shopContext;

    public function __construct(
        ModelManager $modelManager,
        RepositoryInterface $repository,
        ContextServiceInterface $contextService,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        CategoryServiceInterface $categoryService
    )
    {
        parent::__construct();

        $this->modelManager = $modelManager;
        $this->repository = $repository;
        $this->contextService = $contextService;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->categoryService = $categoryService;

    }

    protected function configure()
    {
        $this->addOption('skipRebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->shopContext = $this->getShopContext();

        $output->writeln('<info>Collecting Categories</info>');
        $categoriesWithProductStreams = $this->getCategoriesWithProductStreams();

        // Clearing s_article_categories
        try {
            $output->writeln('<info>Truncating s_articles_categories</info>');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories');
        } catch (DBALException $e) {
            $output->writeln('<error>Error truncating s_articles_categories</error>');

            return;
        }

        // Clearing s_articles_categories_ro
        try {
            $output->writeln('<info>Truncating s_articles_categories_ro</info>');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories_ro');
        } catch (DBALException $e) {
            $output->writeln('<error>Error truncating s_articles_categories_ro</error>');

            return;
        }

        // Clearing s_articles_categories_seo
        try {
            $output->writeln('<info>Truncating s_articles_categories_seo</info>');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories_seo');
        } catch (DBALException $e) {
            $output->writeln('<error>Error truncating s_articles_categories_seo</error>');

            return;
        }

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('very_verbose');
        $progressBar->setRedrawFrequency(200);
        $backlog = [];

        $output->writeln('<info>Sorting Categories</info>');
        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, $progressBar, false);
        $output->writeln('');

        if (count($articleCategories) !== 0) {
            $output->writeln('<info>Writing Product Categories</info>');

            $this->writeArticleCategories($articleCategories, $progressBar, $backlog);

            $output->writeln('');
            $output->writeln('<info>Finished writing Product Categories</info>');
        } else {
            $output->writeln('<info>No Products found</info>');
        }
        unset($articleCategories);

        $output->writeln('<info>Sorting Categories</info>');
        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, $progressBar, true);
        $output->writeln('');

        if (count($articleCategories) !== 0) {
            $output->writeln('<info>Writing Product Categories for NoCategoryStreams</info>');

            $this->writeArticleCategories($articleCategories, $progressBar, $backlog);

            $output->writeln('');
            $output->writeln('<info>Finished writing Product Categories for NoCategoryStreams</info>');
        } else {
            $output->writeln('<info>No Products found</info>');
        }


        $output->writeln('<info>Sorting SEO Categories</info>');
        $seoCategories = $this->buildSeoCategoryAssociation();

        if (count($articleCategories) !== 0) {
            $output->writeln('<info>Writing Product SEO Categories</info>');

            $progressBar->start(count($seoCategories));
            foreach ($seoCategories as $articleID => $seoCategory) {
                $this->writeArticleSeoCategory($articleID, $seoCategory);
                $progressBar->advance();
            }
            $progressBar->finish();

            $output->writeln('');
            $output->writeln('<info>Finished writing Product SEO Categories</info>');
        } else {
            $output->writeln('<info>No SEO Categories found</info>');
        }

        if (!$input->getOption('skipRebuild')) {
            if ($this->container->getParameter('shopware.es.enabled')) {
                $output->writeln('<info>Writing Backlog</info>');
                $this->container->get('shopware_elastic_search.backlog_processor')->add($backlog);
            }

            $output->writeln('<info>Executing Category Tree Rebuild</info>');
            $this->modelManager->getConnection()->exec(
                'create temporary table cat_pathIds
                                                                        select id, pathId from (
                                                                            select id, path, id as pathId from s_categories where path is not null
                                                                            union select id, path, 0+SUBSTRING_INDEX(path,\'|\',1) pathId from s_categories where path is not null
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',2),\'|\',-1) pathId from s_categories
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',3),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',4),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',5),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',6),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',7),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',8),\'|\',-1) pathId from s_categories 
                                                                            union select id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,\'|\',9),\'|\',-1) pathId from s_categories 
                                                                        ) pathIds where pathId > 0
                                                                    ;
                                                                    
                                                                    create temporary table RECREATED_articles_categories_ro
                                                                        select s_articles_categories.articleID, pathIds.pathId as categoryID, 
                                                                                s_articles_categories.categoryID as parentCategoryID 
                                                                            from s_articles_categories
                                                                            inner join cat_pathIds as pathIds on (s_articles_categories.categoryID = pathIds.id and pathIds.pathId > 0)
                                                                        order by articleID, parentCategoryID, categoryID ;
                                                                    
                                                                    TRUNCATE s_articles_categories_ro;
                                                                    
                                                                    INSERT INTO s_articles_categories_ro
                                                                        (articleID,categoryID,parentCategoryID)
                                                                    (
                                                                        SELECT RECREATED_articles_categories_ro.articleID,
                                                                            RECREATED_articles_categories_ro.categoryID,
                                                                            RECREATED_articles_categories_ro.parentCategoryID
                                                                        FROM RECREATED_articles_categories_ro
                                                                    );'
            );
            $output->writeln('<info>Finished Rebuild</info>');
        }
    }

    private function writeArticleCategories(array $articleCategories, ProgressBar $progressBar, array &$backlog = [])
    {
        $progressBar->start(count($articleCategories));
        foreach ($articleCategories as $article => $categories) {
            $progressBar->setMessage('Writing Article: ' . $article);

            $this->writeArticleCategory($article, $categories);
            $backlog[] = new Backlog(ORMBacklogSubscriber::EVENT_ARTICLE_UPDATED, ['id' => $article]);

            /** @noinspection DisconnectedForeachInstructionInspection */
            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * @param int $shopId
     *
     * @return null|ShopContextInterface
     */
    private function getShopContext($shopId = 1)
    {
        $shopRepository = $this->modelManager->getRepository(Shop::class);

        /** @var Shop $shop */
        $shop = $shopRepository->find($shopId);

        if ($shop === null) {
            return null;
        }

        $currencyId = $shop->getCurrency()->getId();
        $customerGroupKey = ContextService::FALLBACK_CUSTOMER_GROUP;

        return $this->contextService->createShopContext($shopId, $currencyId, $customerGroupKey);
    }

    /**
     * @return array[]
     */
    private function getCategoriesWithProductStreams(): array
    {
        $qb = $this->modelManager->getDBALQueryBuilder();
        $qb = $qb
            ->select('category.id AS id')
            ->addSelect('category_attribute.category_writer_stream_ids AS streamIDs')
            ->addSelect('category_attribute.category_writer_seo_priority AS seoPriority')
            ->from('s_categories', 'category')
            ->innerJoin('category', 's_categories_attributes', 'category_attribute', 'category.id = category_attribute.categoryID')
            ->where('category_attribute.category_writer_stream_ids IS NOT NULL');
        $categoriesWithStreams = $qb->execute()->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($categoriesWithStreams as $row) {
            $data[$row['id']] = $this->getArrayStringAsArray($row['streamIDs']);
        }

        return $data;
    }

    /**
     * @return array
     */
    private function buildSeoCategoryAssociation(): array
    {
        $qb = $this->modelManager->getDBALQueryBuilder();
        $qb = $qb
            ->select('articleID')
            ->addSelect('categoryID')
            ->from('s_articles_categories', 'ac');
        $articleCategoriesResult = $qb->execute()->fetchAll(PDO::FETCH_ASSOC);

        $sortedCategories = [];
        foreach ($articleCategoriesResult as $row) {
            $sortedCategories[$row['articleID']][] = $row['categoryID'];
        }
        unset($articleCategoriesResult);

        $qb = $this->modelManager->getDBALQueryBuilder();
        $qb = $qb
            ->select('category.id AS id')
            ->addSelect('category.path AS path')
            ->addSelect('category_attribute.category_writer_seo_priority AS seoPriority')
            ->from('s_categories', 'category')
            ->innerJoin('category', 's_categories_attributes', 'category_attribute', 'category.id = category_attribute.categoryID');
        $categoriesResult = $qb->execute()->fetchAll(PDO::FETCH_ASSOC);

        $categoryData = [];
        foreach ($categoriesResult as $row) {
            if ($row['path'] === null) {
                continue;
            }

            $categoryData[$row['id']] = [
                'id' => $row['id'],
                'path' => $this->getArrayStringAsArray($row['path']),
                'seoPriority' => $row['seoPriority'],
            ];
        }
        unset($categoriesResult);

        $data = [];
        foreach ($sortedCategories as $article => $articleCategories) {
            $seoCategory = null;
            $currentPriority = 0;
            foreach ($articleCategories as $articleCategory) {
                $articleCategory = $categoryData[$articleCategory];

                if ($articleCategory['seoPriority'] !== null) {
                    if ($articleCategory['seoPriority'] > $currentPriority) {
                        $seoCategory = $articleCategory;
                        $currentPriority = $seoCategory['seoPriority'];
                    }

                    continue;
                }

                foreach ($categoryData['path'] as $categoryPath) {
                    $categoryPath = $categoryData[$categoryPath];
                    if ($categoryPath['seoPriority'] === null) {
                        continue;
                    }

                    if ($categoryPath['seoPriority'] > $currentPriority) {
                        $seoCategory = $categoryPath;
                        $currentPriority = $seoCategory['seoPriority'];
                    }
                }
            }

            if ($seoCategory !== null) {
                $data[$article] = (int) $seoCategory['id'];
            }
        }

        return $data;
    }


    /**
     * @param $arrayString
     *
     * @return int[]
     */
    private function getArrayStringAsArray($arrayString): array
    {
        $arrayString = ltrim($arrayString, '|');
        $arrayString = rtrim($arrayString, '|');

        return explode('|', $arrayString);
    }

    /**
     * Get Article-Category association for the given ProductStreams
     * @param array $categoriesWithProductStreams
     * @param ProgressBar $progressBar
     * @param $noCategoryCondition
     * @return array
     */
    private function getSortedArticleArray(array $categoriesWithProductStreams, ProgressBar $progressBar, $noCategoryCondition): array
    {
        $articleCategories = [];
        $progressBar->start(count($categoriesWithProductStreams));
        foreach ($categoriesWithProductStreams as $categoryID => $productStreams) {
            foreach ($productStreams as $productStream) {
                $articles = $this->getArticlesForProductStream((int)$productStream, $noCategoryCondition);

                foreach ($articles as $article) {
                    $articleCategories[$article][] = $categoryID;
                }
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        return $articleCategories;
    }

    /**
     * @param int $streamID
     * @param bool $noCategoryCondition
     *
     * @return int[]
     */
    private function getArticlesForProductStream(int $streamID, $noCategoryCondition = false): array
    {
        $productStreamRepository = $this->modelManager->getRepository(ProductStream::class);

        /** @var ProductStream|null $stream */
        $stream = $productStreamRepository->find($streamID);

        if ($stream === null) {
            return [];
        }

        /** @var string $conditions */
        $conditions = $stream->getConditions();

        if ($conditions === null) {
            return [];
        }

        $criteria = new Criteria();

        $conditions = json_decode($conditions, true);

        /** @var array $conditions */
        $conditions = $this->repository->unserialize($conditions);

        $hasNoCategoryCondition = true;
        /* @var $condition ConditionInterface */
        foreach ($conditions as $condition) {
            if ($condition instanceof NoCategoryLiveCondition) {
                $hasNoCategoryCondition = false;

                if (!$noCategoryCondition) {
                    return [];
                }
            }

            $criteria->addCondition($condition);
        }

        if ($noCategoryCondition && $hasNoCategoryCondition) {
            return [];
        }

        $productQuery = $this->queryBuilderFactory->createProductQuery($criteria, $this->shopContext);

        $queryJoins = $productQuery->getQueryPart('join');

        foreach ($queryJoins as $fromAlias => &$queryJoin) {
            if ($fromAlias === 'product') {
                $joinCondition = &$queryJoin[0]['joinCondition'];

                $joinCondition = str_replace(['AND product.active = 1', 'AND variant.active = 1'], '', $joinCondition);

                unset($joinCondition);
            }
        }
        unset($queryJoin);

        $productQuery->add('join', $queryJoins, false);

        return array_map(function ($entry) {
            return $entry['__product_id'];
        }, $productQuery->execute()->fetchAll(PDO::FETCH_ASSOC));
    }

    private function writeArticleCategory(int $article, array $categories)
    {
        foreach ($categories as $category) {
            $qb = $this->modelManager->getDBALQueryBuilder();

            try {
                $qb = $qb->insert('s_articles_categories')
                    ->values([
                        'articleID' => $article,
                        'categoryID' => $category
                    ]);
                $qb->execute();
                unset($qb);
            } catch (Exception $e) {
                //Insert ignore
            }
        }
    }

    private function writeArticleSeoCategory(int $article, int $category)
    {
        $qb = $this->modelManager->getDBALQueryBuilder();

        try {
            $qb = $qb->insert('s_articles_categories_seo')
                ->values([
                    'shop_id' => $this->shopContext->getShop()->getId(),
                    'article_id' => $article,
                    'category_id' => $category
                ]);
            $qb->execute();
            unset($qb);
        } catch (Exception $e) {
            //Insert ignore
        }
    }
}
