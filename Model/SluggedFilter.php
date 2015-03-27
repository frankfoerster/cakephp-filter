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

App::uses('AppModel', 'Model');

/**
 * Class SluggedFilter
 */
class SluggedFilter extends AppModel {

	/**
	 * Find a slug for the provided filter data.
	 *
	 * @param CakeRequest $request
	 * @param array $filterData
	 * @return string|boolean
	 */
	public function findSlugByFilterData(CakeRequest $request, $filterData) {
		return $this->field($this->alias . '.slug', array(
			'plugin' => $request->params['plugin'],
			'controller' => $request->params['controller'],
			'action' => $request->params['action'],
			'filter_data' => $this->_encodeFilterData($filterData)
		));
	}

	/**
	 * Create a new random slug for the provided $filderData
	 *
	 * @param CakeRequest $request
	 * @param array $filterData
	 * @return string
	 */
	public function createSlugForFilterData(CakeRequest $request, $filterData) {
		$charlist = 'abcdefghikmnopqrstuvwxyz';

		do {
			$slug = '';
			for ($i = 0; $i < 14; $i++) {
				$slug .= substr($charlist, rand(0, 31), 1);
			}
		} while (false !== $this->field('slug', array(
			'slug' => $slug,
			'plugin' => $request->params['plugin'],
			'controller' => $request->params['controller'],
			'action' => $request->params['action']
		)));

		$this->save(array(
			'plugin' => $request->params['plugin'],
			'controller' => $request->params['controller'],
			'action' => $request->params['action'],
			'slug' => $slug,
			'filter_data' => $this->_encodeFilterData($filterData)
		));

		return $slug;
	}

	/**
	 * Find a stored filter data combination for a given slug.
	 *
	 * @param CakeRequest $request
	 * @return array The filter data
	 */
	public function findFilterDataBySlug(CakeRequest $request) {
		$encryptedFilterData = $this->field($this->alias . '.filter_data', array(
			'plugin' => $request->params['plugin'],
			'controller' => $request->params['controller'],
			'action' => $request->params['action'],
			'slug' => $request->params['sluggedFilter']
		));

		if ($encryptedFilterData) {
			return $this->_decodeFilterData($encryptedFilterData);
		}

		return array();
	}

	/**
	 * Encode a filter data array to a string.
	 *
	 * @param $filterData
	 * @return string
	 */
	protected function _encodeFilterData($filterData) {
		return json_encode($filterData);
	}

	/**
	 * Decode a filter data string to the original filter data array.
	 *
	 * @param $encodedFilterData
	 * @return array
	 */
	protected function _decodeFilterData($encodedFilterData) {
		return json_decode($encodedFilterData, true);
	}

}
