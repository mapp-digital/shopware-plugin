<?php declare(strict_types=1);

namespace Mapp\Connect\Shopware\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MappEventCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_mapp_event_collection';
    }

    protected function getExpectedClass(): string
    {
        return MappEventEntity::class;
    }
}
