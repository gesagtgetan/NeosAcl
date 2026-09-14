<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Neos 9: store the subtree tag of each dynamic role and drop the privilege level';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
        );

        $tooLongNames = $this->connection->fetchFirstColumn('SELECT name FROM sandstorm_neosacl_domain_model_dynamicrole WHERE LENGTH(name) > 28');
        $this->abortIf(
            $tooLongNames !== [],
            'Dynamic role names are limited to 28 characters in Neos 9, rename first: ' . implode(', ', array_map(static fn (mixed $name): string => is_string($name) ? $name : '', $tooLongNames))
        );
        $viewOnlyNames = $this->connection->fetchFirstColumn("SELECT name FROM sandstorm_neosacl_domain_model_dynamicrole WHERE privilege = 'view' OR matcher LIKE '%whitelistedNodeTypes\":[\"%'");
        $this->warnIf(
            $viewOnlyNames !== [],
            'These roles lose their view-only level or node type filter and grant editing of the selected subtrees: ' . implode(', ', array_map(static fn (mixed $name): string => is_string($name) ? $name : '', $viewOnlyNames))
        );

        $this->addSql('ALTER TABLE sandstorm_neosacl_domain_model_dynamicrole ADD subtreetag VARCHAR(36) NOT NULL DEFAULT \'\'');
        $this->addSql('UPDATE sandstorm_neosacl_domain_model_dynamicrole SET subtreetag = CONCAT(\'neosacl-\', LOWER(name))');
        $this->addSql('ALTER TABLE sandstorm_neosacl_domain_model_dynamicrole ALTER COLUMN subtreetag DROP DEFAULT, DROP COLUMN privilege');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            "Migration can only be executed safely on '\Doctrine\DBAL\Platforms\AbstractMySQLPlatform'."
        );

        $this->addSql('ALTER TABLE sandstorm_neosacl_domain_model_dynamicrole ADD privilege VARCHAR(255) NOT NULL DEFAULT \'view_edit_create_delete\', DROP COLUMN subtreetag');
        $this->addSql('ALTER TABLE sandstorm_neosacl_domain_model_dynamicrole ALTER COLUMN privilege DROP DEFAULT');
    }
}
