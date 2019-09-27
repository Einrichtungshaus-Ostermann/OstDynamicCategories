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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;

class WriteCategoryToArticlesCommand extends ShopwareCommand
{
    /**
     * ...
     *
     * @var ModelManager
     */
    private $modelManager;

    /**
     * ...
     *
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * ...
     *
     * @var ContextServiceInterface
     */
    private $contextService;

    /**
     * ...
     *
     * @var QueryBuilderFactoryInterface
     */
    private $queryBuilderFactory;

    /**
     * ...
     *
     * @var CategoryServiceInterface
     */
    private $categoryService;

    /**
     * ...
     *
     * @var ShopContext
     */
    private $shopContext;

    /**
     * @param ModelManager                 $modelManager
     * @param RepositoryInterface          $repository
     * @param ContextServiceInterface      $contextService
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     * @param CategoryServiceInterface     $categoryService
     */
    public function __construct(
        ModelManager $modelManager,
        RepositoryInterface $repository,
        ContextServiceInterface $contextService,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        CategoryServiceInterface $categoryService
    )
    {
        // call parent constructor
        parent::__construct();

        // set parameters
        $this->modelManager = $modelManager;
        $this->repository = $repository;
        $this->contextService = $contextService;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->categoryService = $categoryService;

    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        // configure
        $this->setName('ost-dynamic-category:rebuild-categories')
            ->setHelp('The <info>%command.name%</info> writes article-categories.')
            ->setDescription('Write the article-categories.')
            ->addOption('skip-rebuild', null, InputOption::VALUE_NONE, "Skip rebuilding the category-ro tree.")
            ->addOption('sql-rebuild', null, InputOption::VALUE_NONE, "Use custom plain sql query to rebuild the category-ro tree? Otherwise we copy the default shopware command to rebuild the tree.")
            ->addOption('debug', null, InputOption::VALUE_NONE, "In debug-mode we will only read 10 random categories.");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get the shop context - may be given through command option
        $this->shopContext = $this->getShopContext();

        // load categories
        $output->writeln('');
        $output->writeln('loading categories...');
        $categoriesWithProductStreams = $this->getCategoriesWithProductStreams(
            ($input->getOption('debug') === true)
        );

        // clear s_article_categories
        try {
            $output->writeln('truncating s_articles_categories...');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories');
        } catch (DBALException $e) {
            $output->writeln('error truncating s_articles_categories');
            return;
        }

        // clear s_articles_categories_ro
        try {
            $output->writeln('truncating s_articles_categories_ro...');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories_ro');
        } catch (DBALException $e) {
            $output->writeln('error truncating s_articles_categories_ro');
            return;
        }

        // clear s_articles_categories_seo
        try {
            $output->writeln('truncating s_articles_categories_seo...');
            $this->modelManager->getConnection()->exec('TRUNCATE s_articles_categories_seo');
        } catch (DBALException $e) {
            $output->writeln('error truncating s_articles_categories_seo');
            return;
        }

        // start progress bar
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat("%message:-37s%\n %current%/%max% %bar% %percent:3s%%\nremaining: %remaining:-10s%\n");
        $progressBar->setRedrawFrequency(1);

        // set backlog array
        $backlog = [];

        // sort the categories for product-streams
        $output->writeln('');
        $progressBar->setMessage('sorting categories for product-streams...');
        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, $progressBar, false);
        $output->writeln('');

        // none found?!
        if (count($articleCategories) > 0) {
            // write articles to categories
            $output->writeln('');
            $output->writeln('');
            $output->writeln('');
            $this->writeArticleCategories($articleCategories, $progressBar, $backlog);
            $output->writeln('');
            $output->writeln('');
            $output->writeln('');
        } else {
            // done
            $output->writeln('no products found...');
            $output->writeln('');
        }

        // clear
        unset($articleCategories);

        // sort the categories for no-category-product-stream
        $output->writeln('');
        $progressBar->setMessage('sorting categories for no-category-product-stream...');
        $articleCategories = $this->getSortedArticleArray($categoriesWithProductStreams, $progressBar, true);
        $output->writeln('');

        // none found?!
        if (count($articleCategories) > 0) {
            // write articles to categories
            $output->writeln('');
            $output->writeln('');
            $output->writeln('');
            $this->writeArticleCategories($articleCategories, $progressBar, $backlog);
            $output->writeln('');
            $output->writeln('');
            $output->writeln('');
            $output->writeln('');
        } else {
            // done
            $output->writeln('no products found...');
            $output->writeln('');
        }

        // clear
        unset($articleCategories);

        // build seo categories
        $this->buildSeoCategories($output, $progressBar);
        $output->writeln('');

        // is elastic search enabled?!
        if ($this->container->getParameter('shopware.es.enabled')) {
            // write the backlog
            $output->writeln('writing elastic search backlog...');
            $this->container->get('shopware_elastic_search.backlog_processor')->add($backlog);
        }

        // do we want to skip rebuilding the tree?
        if ($input->getOption('skip-rebuild') === true) {
            // done
            $output->writeln('skipping rebuilding the category-ro tree');
            $output->writeln('');
            return;
        }

        // rebuild...
        $output->writeln('rebuilding category-ro tree...');

        // rebuild depending on option
        if ($input->getOption('sql-rebuild') === true) {
            // call sub method
            $this->rebuildCategoryTreeSql($output);
        } else {
            // default shopware command
            $this->rebuildCategoryTreeSwCommand($output);
        }


        // and we are done
        $output->writeln('');
    }

    /**
     * ...
     *
     * @param OutputInterface $output
     * @param ProgressBar     $progressBar
     */
    private function buildSeoCategories(OutputInterface $output, ProgressBar $progressBar)
    {
        // get every category with its priority
        $query = '
            SELECT category.id, attribute.category_writer_seo_priority AS priority
            FROM s_categories AS category
                LEFT JOIN s_categories_attributes AS attribute
                    ON category.id = attribute.categoryID
            ORDER BY id ASC
        ';
        $categories = Shopware()->Db()->fetchPairs($query);

        // loop every category
        foreach ($categories as $id => $category) {
            // force number
            $categories[(int) $id] = (int) $category;
        }

        // get every article with their categories
        $query = '
            SELECT article.id AS articleId, category.id AS categoryId, category.path 
            FROM s_articles AS article
                LEFT JOIN s_articles_categories AS articleCategory 
                    ON article.id = articleCategory.articleID
                LEFT JOIN s_categories AS category 
                    ON category.id = articleCategory.categoryID
            WHERE category.id IS NOT NULL
                AND category.path IS NOT NULL
                AND category.path != ""
            ORDER BY article.id ASC, category.id ASC
        ';
        $articlesToCategories = Shopware()->Db()->fetchAll($query);

        // every article with the categories
        $articles = array();

        // loop the links
        foreach ($articlesToCategories as $link) {
            // set vars
            $articleId = (int) $link['articleId'];
            $categoryId = (int) $link['categoryId'];

            // set default
            if (!isset($articles[$articleId])) {
                // set array
                $articles[$articleId] = array();
            }

            // add category
            $articles[$articleId][$categoryId] = $this->getArrayStringAsArray((string) $link['path']);
        }

        // reset the progress bar
        $progressBar->start(count($articles));

        // loop every article
        foreach ($articles as $articleId => $article) {
            // set weight array for every weight for every category
            $weight = array();

            // output
            $progressBar->setMessage('writing seo category for article - id: ' . $articleId);
            $progressBar->advance();

            // loop every category and its path
            foreach ($article as $categoryId => $path) {
                // start with current category priority
                $weight[$categoryId] = $categories[$categoryId];

                // loop every category within the path
                foreach ($path as $subCategory) {
                    // do we have a new maximum in priority?
                    if ((int) $categories[(int) $subCategory] > $weight[$categoryId]) {
                        // set it
                        $weight[$categoryId] = (int) $categories[(int) $subCategory];
                    }
                }
            }

            // no weight given at any category?
            if (array_sum($weight) === 0) {
                // no seo category for this article
                continue;
            }

            // get the category with the highest weight
            $seoCategoryId = array_search(max($weight), $weight);

            // write the seo category
            $this->writeArticleSeoCategory($articleId, $seoCategoryId);
        }

        // we are done
        $progressBar->finish();
    }

    /**
     * ...
     *
     * @param OutputInterface $output
     *
     * @throws DBALException
     */
    private function rebuildCategoryTreeSql(OutputInterface $output)
    {
        // the query to rebuild
        $query = '
            CREATE TEMPORARY TABLE cat_pathIds
                SELECT
                    id,
                    pathId
                FROM (
                    SELECT id, path, id AS pathId FROM s_categories WHERE path IS NOT NULL
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(path,"|",1) AS pathId FROM s_categories WHERE path IS NOT NULL
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",2),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",3),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",4),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",5),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",6),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",7),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",8),"|",-1) AS pathId FROM s_categories
                    UNION SELECT id, path, 0+SUBSTRING_INDEX(SUBSTRING_INDEX(path,"|",9),"|",-1) AS pathId FROM s_categories
                ) pathIds
                WHERE pathId > 0;
                
            CREATE TEMPORARY TABLE RECREATED_articles_categories_ro
                SELECT
                    s_articles_categories.articleID,
                    pathIds.pathId AS categoryID,
                    s_articles_categories.categoryID AS parentCategoryID
                FROM s_articles_categories
                INNER JOIN cat_pathIds AS pathIds ON (s_articles_categories.categoryID = pathIds.id and pathIds.pathId > 0)
                ORDER BY articleID, parentCategoryID, categoryID;
                
            TRUNCATE s_articles_categories_ro;
            
            INSERT INTO s_articles_categories_ro (articleID,categoryID,parentCategoryID) (
                SELECT
                    RECREATED_articles_categories_ro.articleID,
                    RECREATED_articles_categories_ro.categoryID,
                    RECREATED_articles_categories_ro.parentCategoryID
                FROM RECREATED_articles_categories_ro
            );
        ';
        $this->modelManager->getConnection()->exec($query);
    }

    /**
     * ...
     *
     * @param OutputInterface $output
     *
     * @throws Exception
     */
    private function rebuildCategoryTreeSwCommand(OutputInterface $output)
    {
        // get the default rebuild command
        $command = $this->getApplication()->find('sw:rebuild:category:tree');

        // run the command widh custom input and our output
        $command->run(
            new ArrayInput(array()),
            $output
        );
    }

    /**
     * ...
     *
     * @param array       $articleCategories
     * @param ProgressBar $progressBar
     * @param array       $backlog
     */
    private function writeArticleCategories(array $articleCategories, ProgressBar $progressBar, array &$backlog = [])
    {
        // reset our counter
        $progressBar->start(count($articleCategories));

        // loop every article
        foreach ($articleCategories as $article => $categories) {
            // set progress bar message
            $progressBar->setMessage('writing article - id: ' . $article);

            // write the category for this article
            $this->writeArticleCategory($article, $categories);

            // add to backlog
            $backlog[] = new Backlog(
                ORMBacklogSubscriber::EVENT_ARTICLE_UPDATED,
                ['id' => $article]
            );

            // and next
            $progressBar->advance();
        }

        // done
        $progressBar->finish();
    }

    /**
     * ...
     *
     * @param int $shopId
     *
     * @return ShopContextInterface
     */
    private function getShopContext($shopId = 1)
    {
        /** @var \Shopware\Models\Shop\Repository $repository */
        $repository = $this->modelManager->getRepository(Shop::class);

        /** @var Shop $shop */
        $shop = $repository->getActiveById($shopId);

        // not a shop?
        $shop = (!$shop instanceof Shop)
            ? $repository->getActiveDefault()
            : $shop;

        // return shop context
        return $this->contextService->createShopContext(
            $shop->getId(),
            $shop->getCurrency()->getId(),
            ContextService::FALLBACK_CUSTOMER_GROUP
        );
    }

    /**
     * ...
     *
     * @param boolean $debug
     *
     * @return array[]
     */
    private function getCategoriesWithProductStreams($debug = false): array
    {
        // get every category with the query builder
        $builder = $this->modelManager->getDBALQueryBuilder()
            ->select('category.id AS id')
            ->addSelect('category_attribute.category_writer_stream_ids AS streamIDs')
            ->addSelect('category_attribute.category_writer_seo_priority AS seoPriority')
            ->from('s_categories', 'category')
            ->innerJoin('category', 's_categories_attributes', 'category_attribute', 'category.id = category_attribute.categoryID')
            ->where('category_attribute.category_writer_stream_ids IS NOT NULL')
            ->andWhere('category.id NOT IN (SELECT DISTINCT parent FROM `s_categories` WHERE parent IS NOT NULL)');
        $categoriesWithStreams = $builder->execute()->fetchAll(PDO::FETCH_ASSOC);

        // every category
        $data = [];

        // loop every result
        foreach ($categoriesWithStreams as $row) {
            // and split the stream ids
            $data[$row['id']] = $this->getArrayStringAsArray($row['streamIDs']);
        }

        // debug mode?!
        if ($debug === true) {
            // only first 10 categories
            $data = array_slice($data, 0, 10, true);
        }

        // return category with stream ids
        return $data;
    }

    /**
     * ...
     *
     * @param $arrayString
     *
     * @return int[]
     */
    private function getArrayStringAsArray($arrayString): array
    {
        // trim leading and trailing pipe
        $arrayString = ltrim($arrayString, '|');
        $arrayString = rtrim($arrayString, '|');

        // return every element as array
        return explode('|', $arrayString);
    }

    /**
     * Get article-category association for the given product-streams.
     *
     * @param array $categoriesWithProductStreams
     * @param ProgressBar $progressBar
     * @param $noCategoryCondition
     *
     * @return array
     */
    private function getSortedArticleArray(array $categoriesWithProductStreams, ProgressBar $progressBar, $noCategoryCondition): array
    {
        // the output array
        $articleCategories = [];

        // reset the progress bar
        $progressBar->start(count($categoriesWithProductStreams));

        // loop every category
        foreach ($categoriesWithProductStreams as $categoryID => $productStreams) {
            // loop every product stream for this category
            foreach ($productStreams as $productStream) {
                // get every article for this product stream
                $articles = $this->getArticlesForProductStream((int) $productStream, $noCategoryCondition);

                // loop every article
                foreach ($articles as $article) {
                    // not set yet?
                    if (!isset($articleCategories[$article])) {
                        // set default
                        $articleCategories[$article] = array();
                    }

                    // and add this category to it
                    $articleCategories[$article][] = $categoryID;
                }
            }

            // next category
            $progressBar->advance();
        }

        // done
        $progressBar->finish();

        // return articles with category association
        return $articleCategories;
    }

    /**
     * ...
     *
     * @param int $streamID
     * @param bool $noCategoryCondition
     *
     * @return int[]
     */
    private function getArticlesForProductStream(int $streamID, $noCategoryCondition = false): array
    {
        // get the repository
        $productStreamRepository = $this->modelManager->getRepository(ProductStream::class);

        /** @var ProductStream|null $stream */
        $stream = $productStreamRepository->find($streamID);

        // invalid stream?
        if ($stream === null) {
            // ignore
            return [];
        }

        /** @var string $conditions */
        $conditions = $stream->getConditions();

        // we need conditions
        if ($conditions === null) {
            // ignore
            return [];
        }

        // create a criteria object for the search
        $criteria = new Criteria();

        // decode the conditions
        $conditions = json_decode($conditions, true);

        /** @var array $conditions */
        $conditions = $this->repository->unserialize($conditions);

        // is this the no-category condition?!
        $hasNoCategoryCondition = true;

        /** @var $condition ConditionInterface */
        foreach ($conditions as $condition) {
            // is this a no-category condition?!
            if ($condition instanceof NoCategoryLiveCondition) {
                // set flag
                $hasNoCategoryCondition = false;

                // do we want the no-category condition?
                if (!$noCategoryCondition) {
                    // ignore
                    return [];
                }
            }

            // add this condition
            $criteria->addCondition($condition);
        }

        // we want no-category condition but didnt find it
        if ($noCategoryCondition && $hasNoCategoryCondition) {
            // ignore it
            return [];
        }

        // get the product query with our criteria.
        // this will find every article for our streams.
        // we have to add offline products as well.
        $productQuery = $this->queryBuilderFactory->createProductQuery($criteria, $this->shopContext);

        // extract
        $queryJoins = $productQuery->getQueryPart('join');

        // loop every join
        foreach ($queryJoins as $fromAlias => &$queryJoin) {
            // is this a join to products?
            if ($fromAlias === 'product') {
                // get the condition
                $joinCondition = &$queryJoin[0]['joinCondition'];

                // remove join condition with online articles
                $joinCondition = str_replace(['AND product.active = 1', 'AND variant.active = 1'], '', $joinCondition);

                // and clear the variable
                unset($joinCondition);
            }
        }

        // clear variable
        unset($queryJoin);

        // add our fixed joins
        $productQuery->add('join', $queryJoins, false);

        // return mapped articles
        return array_map(function ($entry) {
            return $entry['__product_id'];
        }, $productQuery->execute()->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * ...
     *
     * @param int   $article
     * @param array $categories
     */
    private function writeArticleCategory(int $article, array $categories)
    {
        // loop every category
        foreach ($categories as $category) {
            try {
                // try to insert into articles-to-categories
                $builder = $this->modelManager->getDBALQueryBuilder()
                    ->insert('s_articles_categories')
                    ->values([
                        'articleID' => $article,
                        'categoryID' => $category
                    ]);
                $builder->execute();

                // clear builder
                unset($builder);
            } catch (Exception $e) {
            }
        }
    }

    /**
     * ...
     *
     * @param int $article
     * @param int $category
     */
    private function writeArticleSeoCategory(int $article, int $category)
    {
        try {
            // try to insert
            $builder = $this->modelManager->getDBALQueryBuilder()
                ->insert('s_articles_categories_seo')
                ->values([
                    'shop_id' => $this->shopContext->getShop()->getId(),
                    'article_id' => $article,
                    'category_id' => $category
                ]);
            $builder->execute();

            // clear builder
            unset($builder);
        } catch (Exception $e) {
        }
    }
}
