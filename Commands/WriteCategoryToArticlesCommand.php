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
use OstDynamicCategories\Bundle\SearchBundle\Condition\NoCategoryLiveCondition;
use Shopware\Bundle\ESIndexingBundle\Struct\Backlog;
use Shopware\Bundle\ESIndexingBundle\Subscriber\ORMBacklogSubscriber;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Service\CategoryServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;
use Shopware\Commands\ShopwareCommand;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Plugin\ConfigWriter;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Models\ProductStream\ProductStream;
use Shopware\Models\Shop\Shop;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var ConfigReader
     */
    private $configReader;

    /**
     * @var ConfigWriter
     */
    private $configWriter;

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
    ) {
        parent::__construct('ost-dynamic-category:rebuild-categories');

        $this->modelManager = $modelManager;
        $this->repository = $repository;
        $this->contextService = $contextService;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->categoryService = $categoryService;

        $this->addOption('skipRebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->shopContext = $this->getShopContext();

        $output->writeln('<info>Collecting Categories</info>');
        $categoriesWithProductStreams = $this->getCategoriesWithProductStreams();

        try {
            $output->writeln('<info>Truncating s_articles_categories</info>');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories');
        } catch (DBALException $e) {
            $output->writeln('<error>Error truncating s_articles_categories</error>');

            return;
        }

        try {
            $output->writeln('<info>Truncating s_articles_categories_ro</info>');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories_ro');
        } catch (DBALException $e) {
            $output->writeln('<error>Error truncating s_articles_categories_ro</error>');

            return;
        }

        $output->writeln('<info>Sorting Categories</info>');
        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, false, $output);

        $backlog = [];
        $output->writeln('<info>Writing Product Categories</info>');

        $progressBar = new ProgressBar($output, \count($articleCategories));
        $progressBar->setFormat('very_verbose');
        $progressBar->setRedrawFrequency(200);
        $progressBar->start();

        foreach ($articleCategories as $article => $categories) {
            $progressBar->setMessage('Writing Article: ' . $article);

            $this->updateArticle($article, $categories);
            $backlog[] = new Backlog(ORMBacklogSubscriber::EVENT_ARTICLE_UPDATED, ['id' => $article]);

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Finished writing Product Categories</info>');
        unset($articleCategories, $progressBar);

        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, true, $output);

        $output->writeln('<info>Writing Product Categories for NoCategoryStreams</info>');

        $progressBar = new ProgressBar($output, \count($articleCategories));
        $progressBar->setFormat('very_verbose');
        $progressBar->setRedrawFrequency(200);
        $progressBar->start();

        foreach ($articleCategories as $article => $categories) {
            $progressBar->setMessage('Writing Article: ' . $article);

            $this->updateArticle($article, $categories);
            $backlog[] = new Backlog(ORMBacklogSubscriber::EVENT_ARTICLE_UPDATED, ['id' => $article]);

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Finished writing Product Categories for NoCategoryStreams</info>');

        if (!$input->getOption('skipRebuild')) {
            $emptyInput = new ArrayInput([]);
            $nullOutput = new NullOutput();

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

    /**
     * @param int $shopId
     *
     * @return null|\Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface
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
            ->from('s_categories', 'category')
            ->innerJoin('category', 's_categories_attributes', 'category_attribute', 'category.id = category_attribute.categoryID')
            ->where('category_attribute.category_writer_stream_ids IS NOT NULL');
        $categoriesWithStreams = $qb->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $data = [];
        foreach ($categoriesWithStreams as $row) {
            $data[$row['id']] = $this->getArrayStringAsArray($row['streamIDs']);
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

    private function getSortedArticleArray(array $categoriesWithProductStreams, $noCategoryCondition = false, OutputInterface $output): array
    {
        $articleCategories = [];
        $pb = new ProgressBar($output, \count($categoriesWithProductStreams));
        $pb->start();
        foreach ($categoriesWithProductStreams as $categoryID => $productStreams) {
            foreach ($productStreams as $productStream) {
                $articles = $this->getArticlesForProductStream((int) $productStream, $noCategoryCondition);

                foreach ($articles as $article) {
                    $articleCategories[$article][] = $categoryID;
                }
            }
            $pb->advance();
        }
        $pb->finish();
        $output->writeln('');

        return $articleCategories;
    }

    /**
     * @param int  $streamID
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
        /* @var $condition \Shopware\Bundle\SearchBundle\ConditionInterface */
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
        }, $productQuery->execute()->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function updateArticle(int $article, array $categories)
    {
        foreach ($categories as $category) {
            $qb = $this->modelManager->getDBALQueryBuilder();

            try {
                $qb = $qb->insert('s_articles_categories')
                    ->values([
                        'articleID'  => $article,
                        'categoryID' => $category
                    ]);
                $qb->execute();
                unset($qb);
            } catch (\Exception $e) {
                //Insert ignore
            }
        }
    }
}
