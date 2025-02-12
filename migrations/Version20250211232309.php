<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250211232309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE advice_month (advice_id INT NOT NULL, month_id INT NOT NULL, INDEX IDX_111E58B112998205 (advice_id), INDEX IDX_111E58B1A0CBDE4 (month_id), PRIMARY KEY(advice_id, month_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE month (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(15) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE advice_month ADD CONSTRAINT FK_111E58B112998205 FOREIGN KEY (advice_id) REFERENCES advice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE advice_month ADD CONSTRAINT FK_111E58B1A0CBDE4 FOREIGN KEY (month_id) REFERENCES month (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE advice DROP month');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE advice_month DROP FOREIGN KEY FK_111E58B112998205');
        $this->addSql('ALTER TABLE advice_month DROP FOREIGN KEY FK_111E58B1A0CBDE4');
        $this->addSql('DROP TABLE advice_month');
        $this->addSql('DROP TABLE month');
        $this->addSql('ALTER TABLE advice ADD month INT NOT NULL');
    }
}
