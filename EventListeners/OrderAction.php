<?php

namespace LegacyProductAttributes\EventListeners;

use LegacyProductAttributes\Model\LegacyCartItemAttributeCombination;
use LegacyProductAttributes\Model\LegacyCartItemAttributeCombinationQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderProductQuery;

/**
 * Listener for order related events.
 */
class OrderAction implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_PRODUCT_AFTER_CREATE => ['createOrderProductAttributeCombinations', 128],
        ];
    }

    /**
     * Save the attribute combinations for the order from our cart item attribute combinations.
     *
     * @param OrderEvent $event
     *
     * @throws PropelException
     */
    public function createOrderProductAttributeCombinations(OrderEvent $event)
    {
        $legacyCartItemAttributeCombinations = LegacyCartItemAttributeCombinationQuery::create()
            ->findByCartItemId($event->getCartItemId());

        // works with Thelia 2.2
        if (method_exists($event, 'getId')) {
            $orderProductId = $event->getId();
        } else {
            // Thelia 2.1 however does not provides the order product id in the event

            // Since the order contains potentially identical (for Thelia) cart items that are only differentiated
            // by the cart item attribute combinations that we are storing ourselves, we cannot use information
            // such as PSE id to cross reference the cart item we are given to the order product that was created from
            // it (as far as I can tell).

            // So we will ASSUME that the last created order product is the one created from this cart item.
            // This is PROBABLY TRUE on a basic Thelia install with no modules messing with the cart and orders in a way
            // that create additional order products, BUT NOT IN GENERAL !

            // FIXME: THIS IS NOT A SANE WAY TO DO THIS

            $orderProductId = OrderProductQuery::create()
                ->orderByCreatedAt(Criteria::DESC)
                ->findOne()
                ->getId();
        }

        /** @var LegacyCartItemAttributeCombination $legacyCartItemAttributeCombination */
        foreach ($legacyCartItemAttributeCombinations as $legacyCartItemAttributeCombination) {
            $attribute = $legacyCartItemAttributeCombination->getAttribute();
            $attributeAv = $legacyCartItemAttributeCombination->getAttributeAv();

            (new OrderProductAttributeCombination())
                ->setOrderProductId($orderProductId)
                ->setAttributeTitle($attribute->getTitle())
                ->setAttributeChapo($attribute->getChapo())
                ->setAttributeDescription($attribute->getDescription())
                ->setAttributePostscriptum($attribute->getPostscriptum())
                ->setAttributeAvTitle($attributeAv->getTitle())
                ->setAttributeAvChapo($attributeAv->getChapo())
                ->setAttributeAvDescription($attributeAv->getDescription())
                ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                ->save();
        }
    }
}
