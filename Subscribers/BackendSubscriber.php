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

namespace OstDynamicCategories\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Enlight\Event\SubscriberInterface;
use Shopware_Components_Snippet_Manager;

class BackendSubscriber implements SubscriberInterface
{
    private $snippets;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * BackendSubscriber constructor.
     *
     * @param Shopware_Components_Snippet_Manager $snippets
     * @param EntityManagerInterface              $entityManager
     */
    public function __construct(Shopware_Components_Snippet_Manager $snippets, EntityManagerInterface $entityManager)
    {
        $this->snippets = $snippets;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_ProductStream' => 'extendProductStream'
        ];
    }

    public function extendProductStream(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware_Controllers_Backend_ProductStream $subject */
        $subject = $args->getSubject();

        $subject->View()->addTemplateDir(__DIR__ . '/../Resources/Views');

        $subject->View()->extendsTemplate('backend/plugins/ost_dynamic_categories/product/product_stream_extension.js');
    }
}
