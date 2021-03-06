<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\SyliusConsumerBundle\EventSubscriber;

use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use Sulu\Bundle\SyliusConsumerBundle\Model\Content\ContentInterface;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;

class ContentSerializerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
        ];
    }

    /**
     * @var StructureMetadataFactoryInterface
     */
    private $factory;

    public function __construct(StructureMetadataFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $object = $event->getObject();
        if (!$object instanceof ContentInterface) {
            return;
        }

        $metadata = $this->factory->getStructureMetadata($object->getResourceKey(), $object->getType());
        if (!$metadata) {
            return;
        }
        $data = $object->getData();

        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();
        foreach ($metadata->getProperties() as $property) {
            if (array_key_exists($property->getName(), $data)) {
                $visitor->setData($property->getName(), $data[$property->getName()]);

                continue;
            }

            $visitor->setData($property->getName(), null);
        }
    }
}
