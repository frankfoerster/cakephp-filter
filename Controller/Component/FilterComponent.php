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

/**
 * Class FilterComponent
 *
 * @property SessionComponent $Session
 */
class FilterComponent extends Component {

	/**
	 * Other components used by this component.
	 *
	 * @var array
	 */
	public $components = array(
		'Session'
	);

	/**
	 * Request object.
	 *
	 * @var CakeRequest
	 */
	public $request;

	/**
	 * Filter fields registered on the controller.
	 *
	 * @var array
	 */
	public $filterFields = array();

	/**
	 * Filter options for find calls.
	 *
	 * @var array
	 */
	public $filterOptions = array();

	/**
	 * Holds all active filters and their values.
	 *
	 * @var array
	 */
	public $activeFilters = array();

	/**
	 * Holds the slug for the current active filters.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Sort fields registered on the controller.
	 *
	 * @var array
	 */
	public $sortFields = array();

	/**
	 * Holds the active sort field (key) and its direction (value).
	 *
	 * @var array
	 */
	public $activeSort = array();

	/**
	 * Holds the active limit.
	 *
	 * @var integer
	 */
	public $activeLimit;

	/**
	 * Holds the default sort field (key) and its direction (value).
	 *
	 * @var array
	 */
	public $defaultSort = array();

	/**
	 * @var integer The current page.
	 */
	public $page = 1;

	/**
	 * Holds all pagination params.
	 *
	 * @var array
	 */
	public $paginationParams = array();

	/**
	 * Default pagination limit.
	 *
	 * @var integer
	 */
	public $defaultLimit = 10;

	/**
	 * Holds all available pagination limits.
	 *
	 * @var array
	 */
	public $limits = array();

	/**
	 * Tells if sort is enabled after initialization.
	 *
	 * @var boolean
	 */
	protected $_sortEnabled = false;

	/**
	 * Tells if filters are enabled after initialization.
	 *
	 * @var boolean
	 */
	protected $_filterEnabled = false;

	/**
	 * Tells if pagination is enabled after initialization.
	 *
	 * @var boolean
	 */
	protected $_paginationEnabled = false;

	/**
	 * Called before the Controller::beforeFilter().
	 *
	 * @param Controller $controller Controller with components to initialize
	 * @return void
	 */
	public function initialize(Controller $controller) {
		parent::initialize($controller);

		$this->_sortEnabled = $this->_isSortEnabled($controller);
		$this->_filterEnabled = $this->_isFilterEnabled($controller);
		$this->_paginationEnabled = $this->_isPaginationEnabled($controller);

		if ($this->_sortEnabled || $this->_filterEnabled || $this->_paginationEnabled) {
			$this->request = $controller->request;

			if ($this->_sortEnabled) {
				$this->sortFields = $this->_getSortFields($controller);

				foreach ($this->sortFields as $field => $options) {
					if (!isset($options['default'])) {
						continue;
					}
					$dir = strtolower($options['default']);
					if (in_array($dir, array('asc', 'desc'))) {
						$this->defaultSort = array(
							'field' => $field,
							'dir' => $dir
						);
					}
				}
			}

			if ($this->_filterEnabled) {

				if (isset($this->request->params['sluggedFilter']) && $this->request->params['sluggedFilter'] !== '') {
					/** @var SluggedFilter $SluggedFilter */
					$SluggedFilter = ClassRegistry::init('Filter.SluggedFilter');
					$filterData = $SluggedFilter->findFilterDataBySlug($controller->request);
					if (!empty($filterData)) {
						$this->request->data = array_merge($this->request->data, $filterData);
						$this->slug = $this->request->params['sluggedFilter'];
					}
				}

				$this->filterFields = $this->_getFilterFields($controller);
			}

			if ($this->_paginationEnabled) {
				$this->defaultLimit = $controller->limits[$this->request->params['action']]['default'];
				$this->limits = $controller->limits[$this->request->params['action']]['limits'];
			}
		}
	}

	/**
	 * Called after the Controller::beforeFilter() and before the controller action.
	 *
	 * @param Controller $controller
	 * @return void
	 */
	public function startup(Controller $controller) {
		$this->_initFilterOptions();

		if ($controller->request->is('post') &&
			isset($controller->sluggedFilterActions) &&
			is_array($controller->sluggedFilterActions) &&
			in_array($controller->request->params['action'], $controller->sluggedFilterActions)
		) {
			$rawFilterData = $controller->request->data;
			$filterData = array();
			foreach ($this->filterFields as $filterField => $options) {
				if (isset($rawFilterData[$filterField]) && $rawFilterData[$filterField] !== '') {
					$filterData[$filterField] = $rawFilterData[$filterField];
				}
			}
			if (!empty($filterData)) {
				/** @var SluggedFilter $SluggedFilter */
				$SluggedFilter = ClassRegistry::init('Filter.SluggedFilter');
				$slug = $SluggedFilter->findSlugByFilterData($controller->request, $filterData);
				if (!$slug) {
					$slug = $SluggedFilter->createSlugForFilterData($controller->request, $filterData);
				}
				$controller->redirect(array('action' => $controller->request->params['action'], 'sluggedFilter' => $slug));
				return;
			} else {
				$controller->redirect(array('action' => $controller->request->params['action']));
				return;
			}
		}
	}

	/**
	 * Paginate over a specific model with the given query $options.
	 *
	 * Return query option array that contains all filter, sort and pagination specific options.
	 *
	 * @param Model $model
	 * @param array $options
	 * @param boolean $total
	 * @return array
	 */
	public function paginate(Model $model, $options, $total = false) {
		$limit = $this->defaultLimit;
		if (isset($this->request->query['l']) && in_array((integer) $this->request->query['l'], $this->limits)) {
			$limit = (integer) $this->request->query['l'];
			$this->activeLimit = $limit;
		}
		$this->request->data['l'] = $limit;

		if ($total === false) {
			$countOptions = $options;
			if (isset($options['count_fields'])) {
				$countOptions['fields'] = $options['count_fields'];
			}
			$total = $model->find('count', $countOptions);
		}

		if ($limit !== null && $total !== null) {
			$pages = ceil($total / $limit);
			if ($this->page > $pages) {
				$this->page = $pages;
			}
			if ($this->page < 1) {
				$this->page = 1;
			}
			$offset = ($this->page > 1) ? ($this->page - 1) * $limit : 0;
			$from = ($offset > 0) ? $offset + 1 : 1;
			$to = (($from + $limit - 1) > $total) ? $total : $from + $limit - 1;

			$options['limit'] = $limit;
			$options['offset'] = $offset;

			$this->paginationParams = array(
				'from' => ($total === 0) ? 0 : $from,
				'page' => $this->page,
				'pages' => ($total === 0) ? 1 : $pages,
				'to' => $to,
				'total' => $total,
				'defaultLimit' => $this->defaultLimit,
				'limits' => array_combine($this->limits, $this->limits)
			);
		}

		return $options;
	}

	/**
	 * beforeRender callback
	 *
	 * Is called after the controller executes the requested action’s logic, but before the controller’s renders views and layout.
	 *
	 * - Save the filter, sort and pagination params to the session.
	 * - Can be later retrieved via FilterHelper::getBacklink($url)
	 *
	 * @param Controller $controller
	 */
	public function beforeRender(Controller $controller) {
		$filterOptions = array();

		if ($this->slug) {
			$filterOptions['slug'] = $this->slug;
		}

		if (!empty($this->activeSort)) {
			foreach ($this->activeSort as $key => $val) {
				if ((isset($this->defaultSort) && $this->defaultSort['field'] !== $key) ||
					(isset($this->defaultSort) && $this->defaultSort['field'] === $key && $this->defaultSort['dir'] !== $val)
				) {
					$filterOptions['s'] = $key;
					$filterOptions['d'] = $val;
				}
			}
		}

		if (!empty($this->paginationParams) &&
			isset($this->paginationParams['page']) &&
			$this->paginationParams['page'] != 1
		) {
			$filterOptions['p'] = $this->paginationParams['page'];
		}

		if ($this->activeLimit && $this->activeLimit !== $this->defaultLimit) {
			$filterOptions['l'] = $this->activeLimit;
		}

		$path = 'FILTER_' . join('.', array(
				$controller->request->params['plugin'],
				$controller->request->params['controller'],
				$controller->request->params['action']
			));

		if (!empty($filterOptions)) {
			$this->Session->write($path, $filterOptions);
		} else {
			$this->Session->delete($path);
		}

		parent::beforeRender($controller);
	}

	/**
	 * Try to retrieve a filtered backlink from the session.
	 *
	 * @param array $url
     * @param bool $onlyCheck
	 * @return array|bool
	 */
	public static function getBacklink($url, $onlyCheck = false) {
		$path = 'FILTER_' . join('.', array(
				$url['plugin'] ? $url['plugin'] : '',
				$url['controller'],
				$url['action']
			));

		App::uses('CakeSession', 'Model.Datasource');
		if (($filterOptions = CakeSession::read($path))) {
            if ($onlyCheck !== false) {
                return true;
            }
			if (isset($filterOptions['slug'])) {
				$url['sluggedFilter'] = $filterOptions['slug'];
				unset($filterOptions['slug']);
			}
			if (!empty($filterOptions)) {
				if (!isset($url['?'])) {
					$url['?'] = array();
				}
				$url['?'] = array_merge($url['?'], $filterOptions);
			}
		}

		return $url;
	}

	/**
	 * Create the filter options that
	 * can be used for model find calls
	 * in the controller.
	 *
	 * @return void
	 */
	protected function _initFilterOptions() {
		if ((empty($this->filterFields) && empty($this->sortFields))) {
			return;
		}

		$options = array(
			'conditions' => array(),
			'order' => array()
		);

		// check filter params
		if (!empty($this->request->data)) {
			foreach ($this->request->data as $field => $value) {
				if (!isset($this->filterFields[$field]) || $value === '') {
					continue;
				}
                if (isset($this->filterFields[$field]['ifValueIs']) && $value !== $this->filterFields[$field]['ifValueIs']) {
                    continue;
                }

				$options = $this->_createFilterFieldOption($field, $value, $options);

				$this->activeFilters[$field] = $value;
			}
		}

		if (isset($this->request->query['s'], $this->sortFields[$this->request->query['s']])) {
			$d = 'asc';
			if (isset($this->request->query['d'])) {
				$dir = strtolower($this->request->query['d']);
				if (in_array($dir, array('asc', 'desc'))) {
					$d = $dir;
				}
			}
			$field = $this->request->query['s'];
			$options = $this->_createSortFieldOption($field, $d, $options);
			$this->activeSort[$field] = $d;
		} elseif (!empty($this->defaultSort)) {
			$options = $this->_createSortFieldOption($this->defaultSort['field'], $this->defaultSort['dir'], $options);
			$this->activeSort[$this->defaultSort['field']] = $this->defaultSort['dir'];
		}

		if (isset($this->request->query['p'])) {
			$this->page = $this->request->query['p'];
		}

		$this->filterOptions = $options;
	}

	/**
	 * Check if sorting for the current controller action is enabled.
	 *
	 * @param Controller $controller
	 * @return boolean
	 */
	protected function _isSortEnabled(Controller $controller) {
		if (!isset($controller->sortFields)) {
			return false;
		}

		foreach ($controller->sortFields as $field) {
			if (isset($field['actions']) &&
				is_array($field['actions']) &&
				in_array($controller->request->params['action'], $field['actions'])
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if filtering for the current controller action is enabled.
	 *
	 * @param Controller $controller
	 * @return boolean
	 */
	protected function _isFilterEnabled(Controller $controller) {
		if (!isset($controller->filterFields)) {
			return false;
		}

		foreach ($controller->filterFields as $field) {
			if (isset($field['actions']) &&
				is_array($field['actions']) &&
				in_array($controller->request->params['action'], $field['actions'])
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if pagination is enabled for the current controller action.
	 *
	 * @param Controller $controller
	 * @return boolean
	 */
	protected function _isPaginationEnabled(Controller $controller) {
		if (!isset($controller->limits) ||
			!isset($controller->limits[$controller->request->params['action']]) ||
			!isset($controller->limits[$controller->request->params['action']]['default']) ||
			!isset($controller->limits[$controller->request->params['action']]['limits'])
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get all available sort fields for the current controller action.
	 *
	 * @param Controller $controller
	 * @return array
	 */
	protected function _getSortFields(Controller $controller) {
		$sortFields = array();

		foreach ($controller->sortFields as $field => $options) {
			if (isset($options['actions']) &&
				is_array($options['actions']) &&
				in_array($controller->request->params['action'], $options['actions'])
			) {
				$sortFields[$field] = $options;
			}
		}

		return $sortFields;
	}

	/**
	 * Get all available filter fields for the current controller action.
	 *
	 * @param Controller $controller
	 * @return array
	 */
	protected function _getFilterFields(Controller $controller) {
		$filterFields = array();

		foreach ($controller->filterFields as $field => $options) {
			if (isset($options['actions']) &&
				is_array($options['actions']) &&
				in_array($controller->request->params['action'], $options['actions'])
			) {
				$filterFields[$field] = $options;
			}
		}

		return $filterFields;
	}

	/**
	 * Create the 'order' find condition part for a sorted field.
	 *
	 * @param string $field The field name to sort
	 * @param string $dir The sort direction (asc, desc)
	 * @param array $options The current find options where the sorting should be added.
	 * @return mixed
	 */
	protected function _createSortFieldOption($field, $dir, $options) {
		$sortField = $this->sortFields[$field];
		$options['order'][] = $sortField['modelField'] . ' ' . $dir;

		return $options;
	}

	/**
	 * Create a find condition for a specific filter field with the given value and options.
	 *
	 * @param string $field
	 * @param mixed $value
	 * @param array $options
	 * @return array
	 */
	protected function _createFilterFieldOption($field, $value, $options) {
		$filterField = $this->filterFields[$field];

		if (isset($filterField['type'])) {
			switch ($filterField['type']) {
				case 'like':
					if (!is_array($filterField['modelField'])) {
						$filterField['modelField'] = array($filterField['modelField']);
					}
					$orConditions = array();
					foreach ($filterField['modelField'] as $modelField) {
						$orConditions[] = $modelField . ' LIKE "%' . $value . '%"';
					}
					$options['conditions']['OR'] = $orConditions;
					break;
				case '=':
					$options['conditions'][] = $filterField['modelField'] . ' = "' . $value . '"';
					break;
				case '>':
					$options['conditions'][] = $filterField['modelField'] . ' > "' . $value . '"';
					break;
				case '>=':
					$options['conditions'][] = $filterField['modelField'] . ' >= "' . $value . '"';
					break;
				case '<':
					$options['conditions'][] = $filterField['modelField'] . ' < "' . $value . '"';
					break;
				case '<=':
					$options['conditions'][] = $filterField['modelField'] . ' <= "' . $value . '"';
					break;
				case '<>':
					$options['conditions'][] = $filterField['modelField'] . ' <> "' . $value . '"';
					break;
				case 'IN':
					$val = !is_array($value) ? array($value) : $value;
					$options['conditions'][] = $filterField['modelField'] . ' IN(' . implode(', ', $val) . ')';
					break;
				case 'custom':
					if (isset($filterField['ifValueIs']) && $filterField['ifValueIs'] !== $value) {
						break;
					}
					$options['conditions'] = Hash::merge($options['conditions'], $filterField['customConditions']);
					break;
				case 'having':
					$options['group'] = 'HAVING ' . $filterField['modelField'] . ' LIKE "%' . $value . '%"';
					break;
				case 'date_range':
//						$options['conditions'][] = '';
					break;
			}

			if (isset($filterField['contain'])) {
				if (!isset($options['contain'])) {
					$options['contain'] = array();
				}
				$options['contain'] = array_merge($options['contain'], $filterField['contain']);
			}
		}

		return $options;
	}

}
