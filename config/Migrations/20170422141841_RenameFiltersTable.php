<?php
use Cake\Cache\Cache;
use Migrations\AbstractMigration;

class RenameFiltersTable extends AbstractMigration
{
    /**
     * Migrate up.
     *
     * @return void
     */
    public function up()
    {
        $this->table('ff_filters')->rename('frank_foerster_filter_filters')->save();

        Cache::clear();
    }

    /**
     * Migrate down.
     *
     * @return void
     */
    public function down()
    {
        $this->table('frank_foerster_filter_filters')->rename('ff_filters')->save();

        Cache::clear();
    }
}
