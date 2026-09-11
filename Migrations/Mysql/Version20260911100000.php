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
