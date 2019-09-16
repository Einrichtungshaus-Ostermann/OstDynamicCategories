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

    public static $attributes = [
        's_categories_attributes' => [
            [
                'column' => 'category_writer_stream_ids',
                'type'   => 'multi_selection',
                'data'   =>                 [
                    'label'            => 'Product Streams',
                    'helpText'         => 'Die Kategorie enthält alle Artikel der ausgewählten Product Streams. Bitte beachten Sie, dass die Verknüpfungen von Artikeln zu Kategorien via console command aktualisiert werden müssen.',
                    'translatable'     => false,
                    'position'         => 500,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'entity'           => ProductStream::class,
                ],
            ],
            [
                'column' => 'category_writer_seo_priority',
                'type'   => 'integer',
                'data'   => [
                    'label'            => 'SEO Priorität',
                    'helpText'         => 'Die Priorität dieser Kategorie bei der Erstellung der SEO Urls. Je höher die Priorität ist, desto wichtiger ist diese.',
                    'translatable'     => false,
                    'displayInBackend' => true,
                    'custom'           => false,
                    'position'         => 501
                ]
            ],
        ],
    ];

    /**
     * ...
     *
     * @param Plugin         $plugin
     * @param InstallContext $context
     */
    public function __construct(Plugin $plugin, InstallContext $context)
    {
        // set params
        $this->plugin = $plugin;
        $this->context = $context;
    }

    /**
     * ...
     *
     * @throws \Exception
     */
    public function install()
    {
    }
}
