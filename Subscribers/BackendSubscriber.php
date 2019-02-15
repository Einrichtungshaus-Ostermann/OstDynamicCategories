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

namespace OstDynamicCategories\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Enlight\Event\SubscriberInterface;
use Shopware_Components_Snippet_Manager as SnippetManager;
use Enlight_Event_EventArgs as EventArgs;

class BackendSubscriber implements SubscriberInterface
{
    /**
     * ...
     *
     * @var SnippetManager
     */
    private $snippets;

    /**
     * ...
     *
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * BackendSubscriber constructor.
     *
     * @param SnippetManager $snippets
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(SnippetManager $snippets, EntityManagerInterface $entityManager)
    {
        $this->snippets = $snippets;
        $this->entityManager = $entityManager;
    }

    /**
     * ...
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_ProductStream' => 'extendProductStream',
            'Enlight_Controller_Action_PostDispatch_Backend_Base' => 'extendBase'

        ];
    }

    /**
     * ...
     *
     * @param EventArgs $args
     *
     * @return void
     */
    public function extendProductStream(EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_ProductStream $subject */
        $subject = $args->getSubject();

        // extend our template
        $subject->View()->addTemplateDir(__DIR__ . '/../Resources/Views');
        $subject->View()->extendsTemplate('backend/plugins/ost_dynamic_categories/product/product_stream_extension.js');
    }

    /**
     * ...
     *
     * @param EventArgs $args
     *
     * @return void
     */
    public function extendBase(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_Base $subject */
        $subject = $args->getSubject();

        // extend our template
        $subject->View()->addTemplateDir(__DIR__ . '/../Resources/Views');
        $subject->View()->extendsTemplate('backend/base/attribute/field/product-stream-grid.js');
    }
}
