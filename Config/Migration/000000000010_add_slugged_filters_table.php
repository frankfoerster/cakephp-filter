<?php
/**
 * Copyright (c) Frank Förster (http://frankfoerster.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Frank Förster (http://frankfoerster.com)
 * @link          http://github.com/frankfoerster/cakephp-filter
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('Migration', 'Migrations.Model');
App::uses('MigrationInterface', 'Migrations.Model');

class AddSluggedFiltersTable extends Migration implements MigrationInterface {

	public function up() {
		$this->createTable('slugged_filters', array(
			'id' => array('type' => 'integer', 'length' => 11, 'unsigned' => true, 'null' => false, 'key' => 'primary'),
			'plugin' => array('type' => 'string', 'length' => 255, 'null' => true, 'default' => null),
			'controller' => array('type' => 'string', 'length' => 255, 'null' => false),
			'action' => array('type' => 'string', 'length' => 255, 'null' => false),
			'slug' => array('type' => 'string', 'length' => 14, 'null' => false),
			'filter_data' => array('type' => 'text', 'null' => false),
			'created' => array('type' => 'datetime', 'null' => false),
			// modified is not needed here, since those entries will only be created and never modified.

			'indexes' => array(
				'PRIMARY' => array(
					'column' => 'id',
					'unique' => 1
				),
				'plugin_controller_action_slug' => array(
					'column' => array('plugin', 'controller', 'action', 'slug')
				)
			),

			'tableParameters' => array(
				'comment' => 'Mapping von Filter-Parametern eines Formulars auf einen Slug zum einfachen URL-Sharing und Bookmarking.'
			)
		));
	}

	public function down() {
		$this->dropTable('slugged_filters');
	}

}
