<?php declare(strict_types=1);

/**
 * Einrichtungshaus Ostermann GmbH & Co. KG - Dynamic Categories
 *
 * @package   OstDynamicCategories
 *
 * @author    Tim Windelschmidt <tim.windelschmidt@ostermann.de>
 * @copyright 2018 Einrichtungshaus Ostermann GmbH & Co. KG
 * @license   proprietary
 */

namespace OstDynamicCategories\Setup;

use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Models\ProductStream\ProductStream;

class Install
{
    /**
     * Main bootstrap object.
     *
     * @var Plugin
     */
    protected $plugin;



    /**
     * ...
     *
     * @var InstallContext
     */
    protected $context;



    /**
     * ...
     *
     * @var ModelManager
     */
    protected $modelManager;



    /**
     * ...
     *
     * @var CrudService
     */
    protected $crudService;



    /**
     * ...
     *
     * @param Plugin         $plugin
     * @param InstallContext $context
     * @param ModelManager   $modelManager
     * @param CrudService    $crudService
     */
    public function __construct(Plugin $plugin, InstallContext $context, ModelManager $modelManager, CrudService $crudService)
    {
        // set params
        $this->plugin = $plugin;
        $this->context = $context;
        $this->modelManager = $modelManager;
        $this->crudService = $crudService;
    }



    /**
     * ...
     *
     * @throws \Exception
     */
    public function install()
    {
        try {
            $this->crudService->update(
                's_categories_attributes',
                'category_writer_stream_ids',
                'multi_selection',
                [
                    'entity'           => ProductStream::class,
                    'displayInBackend' => true,
                    'label'            => 'Productstreams for Categorywriter',
                ],
                null,
                true
            );
        } catch (\Exception $e) {
            return false;
        }
    }
}
