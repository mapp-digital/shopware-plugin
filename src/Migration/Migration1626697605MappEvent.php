<?php declare(strict_types=1);

namespace Mapp\Connect\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1626697605MappEvent extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626697605;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `mapp_event` (
              `id` BINARY(16) NOT NULL,
              `title` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `event_name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
              `message_id` BINARY(16) NOT NULL,
              `active` TINYINT(1) NOT NULL DEFAULT \'1\',
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx.mapp_event.event_name` (`event_name`),
              KEY `idx.mapp_event.message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
          ');

        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `mapp_event_sales_channel` (
                `mapp_event_id` binary(16) NOT NULL,
                `sales_channel_id` binary(16) NOT NULL,
                PRIMARY KEY (`mapp_event_id`,`sales_channel_id`),
                KEY `sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.mapp_event_sales_channel.mapp_event_id` FOREIGN KEY (`mapp_event_id`) REFERENCES `mapp_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.mapp_event_sales_channel.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
