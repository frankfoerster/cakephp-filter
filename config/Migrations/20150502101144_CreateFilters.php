<?php
use Cake\Cache\Cache;
use Phinx\Db\Table\Column;
use Phinx\Migration\AbstractMigration;

class CreateFilters extends AbstractMigration
{
    /**
     * Migrate up.
     *
     * @return void
     */
    public function up()
    {
        $table = $this->table('ff_filters');
        $table->addColumn('plugin', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('controller', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('action', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('slug', 'string', ['limit' => 14, 'null' => false])
            ->addColumn('filter_data', 'text', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP']);
        $table->addIndex('slug', ['name' => 'BY_SLUG', 'unique' => true]);
        $table->create();

        $id = new Column();
        $id->setIdentity(true)
            ->setType('integer')
            ->setOptions(['limit' => 11, 'signed' => false, 'null' => false]);

        $table->changeColumn('id', $id)->save();

        Cache::clear();
    }

    /**
     * Migrate down
     *
     * @return void
     */
    public function down() {
        $this->table('ff_filters')->drop();

        Cache::clear();
    }
}
