<?php
/**
 * Copyright (c) Frank Förster (http://frankfoerster.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Frank Förster (http://frankfoerster.com)
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace FrankFoerster\Filter\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\Network\Request;
use Cake\Network\Session;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use FrankFoerster\Filter\Model\Table\FiltersTable;

/**
 * Class FilterComponent
 */
class FilterComponent extends Component
{
    /**
     * Other components used by this component.
     *
     * @var array
     */
    public $components = [
        'Session'
    ];

    /**
     * Request object.
     *
     * @var Request
     */
    public $request;

    /**
     * Controller object this Component is operating on.
     *
     * @var Controller
     */
    public $controller;

    /**
     * The requested controller action
     *
     * @var string
     */
    public $action;

    /**
     * Filter fields registered on the controller.
     *
     * @var array
     */
    public $filterFields = [];

    /**
     * Filter options for find calls.
     *
     * @var array
     */
    public $filterOptions = [];

    /**
     * Holds all active filters and their values.
     *
     * @var array
     */
    public $activeFilters = [];

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
    public $sortFields = [];

    /**
     * Holds the active sort field (key) and its direction (value).
     *
     * @var array
     */
    public $activeSort = [];

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
    public $defaultSort = [];

    /**
     * @var integer The current page.
     */
    public $page = 1;

    /**
     * Holds all pagination params.
     *
     * @var array
     */
    public $paginationParams = [];

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
    public $limits = [];

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
     * Default config
     *
     * These are merged with user-provided config when the component is used.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'filterTable' => 'FrankFoerster/Filter.Filters'
    ];

    /**
     * Called before the controller’s beforeFilter method, but after the controller’s initialize() method.
     *
     * @param Event $event
     */
    public function beforeFilter(Event $event)
    {
        $this->controller = $event->subject();
        $this->request = $this->controller->request;
        $this->action = $this->request->params['action'];
        $this->_sortEnabled = $this->_isSortEnabled();
        $this->_filterEnabled = $this->_isFilterEnabled();
        $this->_paginationEnabled = $this->_isPaginationEnabled();

        if ($this->_sortEnabled) {
            $this->sortFields = $this->_getSortFields();

            foreach ($this->sortFields as $field => $options) {
                if (!isset($options['default'])) {
                    continue;
                }
                $dir = strtolower($options['default']);
                if (in_array($dir, ['asc', 'desc'])) {
                    $this->defaultSort = [
                        'field' => $field,
                        'dir' => $dir
                    ];
                }
            }
        }

        if ($this->_filterEnabled) {

            if (isset($this->request->params['sluggedFilter']) && $this->request->params['sluggedFilter'] !== '') {
                /** @var \FrankFoerster\Filter\Model\Table\FiltersTable $FiltersTable */
                $FiltersTable = TableRegistry::get($this->config('filterTable'));
                $filterData = $FiltersTable->find('filterDataBySlug', ['request' => $this->request]);
                if (!empty($filterData)) {
                    $this->request->data = array_merge($this->request->data, $filterData);
                    $this->slug = $this->request->params['sluggedFilter'];
                }
            }

            $this->filterFields = $this->_getFilterFields();
        }

        if ($this->_paginationEnabled) {
            $this->defaultLimit = $this->controller->limits[$this->action]['default'];
            $this->limits = $this->controller->limits[$this->action]['limits'];
        }
    }

    /**
     * Called after the Controller::beforeFilter() and before the controller action.
     *
     * @param Event $event
     * @return bool
     */
    public function startup(Event $event)
    {
        if (!$this->controller || !$this->request || !$this->action) {
            return true;
        }
        $this->_initFilterOptions();

        if ($this->request->is('post') &&
            isset($this->controller->filterActions) &&
            is_array($this->controller->filterActions) &&
            in_array($this->action, $this->controller->filterActions)
        ) {
            $rawFilterData = $this->request->data;
            $filterData = [];
            foreach ($this->filterFields as $filterField => $options) {
                if (isset($rawFilterData[$filterField]) && $rawFilterData[$filterField] !== '') {
                    $filterData[$filterField] = $rawFilterData[$filterField];
                }
            }
            if (!empty($filterData)) {
                /** @var FiltersTable $FiltersTable */
                $FiltersTable = TableRegistry::get($this->config('filterTable'));
                $filter = $FiltersTable->find('slugForFilterData', [
                    'request' => $this->request,
                    'filterData' => $filterData
                ])->first();
                if (!$filter) {
                    $slug = $FiltersTable->createFilterForFilterData($this->request, $filterData);
                } else {
                    $slug = $filter->slug;
                }
                $sort = array_keys($this->activeSort)[0];
                $useDefaultSort = ($this->defaultSort['field'] === $sort && $this->activeSort[$sort] === $this->defaultSort['dir']);
                $url = [
                    'action' => $this->action,
                    'sluggedFilter' => $slug
                ];
                if (!$useDefaultSort) {
                    $url['?'] = [
                        's' => $sort
                    ];
                    if (!isset($this->sortFields[$sort]['custom'])) {
                        $url['?']['d'] = $this->activeSort[$sort];
                    }
                }
                $this->controller->redirect($url);
                return false;
            } else {
                $this->controller->redirect(['action' => $this->action]);
                return false;
            }
        }

        return true;
    }

    public function filter(Query $query)
    {
        if (!empty($this->filterOptions['conditions'])) {
            $query->where($this->filterOptions['conditions']);
        }
        if (!empty($this->filterOptions['order'])) {
            $query->order($this->filterOptions['order']);
        }

        return $query;
    }

    /**
     * Paginate over a specific model with the given query $options.
     *
     * Return query option array that contains all filter, sort and pagination specific options.
     *
     * @param Query $query
     * @param array $countFields
     * @param boolean $total
     * @return Query
     */
    public function paginate(Query $query, array $countFields = [], $total = false)
    {
        $limit = $this->defaultLimit;
        if (isset($this->request->query['l']) && in_array((integer)$this->request->query['l'], $this->limits)) {
            $limit = (integer)$this->request->query['l'];
            $this->activeLimit = $limit;
        }
        if (!$this->activeLimit) {
            $lastLimit = $this->request->session()->read(join('.', [
                'LIMIT_' . $this->request->params['plugin'],
                $this->controller->name,
                $this->action
            ]));
            if ($lastLimit) {
                $limit = $lastLimit;
                $this->activeLimit = $limit;
            }
        }
        $this->request->data['l'] = $limit;

        if ($total === false) {
            $countQuery = clone $query;
            if (!empty($countFields)) {
                $countQuery->select($countFields);
            }
            $total = $countQuery->count();
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

            $query->limit($limit)->offset($offset);

            $this->paginationParams = [
                'from' => ($total === 0) ? 0 : $from,
                'page' => $this->page,
                'pages' => ($total === 0) ? 1 : $pages,
                'to' => $to,
                'total' => $total,
                'defaultLimit' => $this->defaultLimit,
                'limits' => array_combine($this->limits, $this->limits)
            ];
        }

        return $query;
    }

    /**
     * beforeRender callback
     *
     * Is called after the controller executes the requested action’s logic, but before the controller’s renders views and layout.
     *
     * - Save the filter, sort and pagination params to the session.
     * - Can be later retrieved via FilterHelper::getBacklink($url)
     *
     * @param Event $event
     * @return bool
     */
    public function beforeRender(Event $event)
    {
        if (!$this->controller || !$this->request || !$this->action) {
            return true;
        }
        $filterOptions = [];

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

        $path = join('.', [
            'FILTER_' . $this->request->params['plugin'],
            $this->controller->name,
            $this->action
        ]);
        $limitPath = str_replace('FILTER_', 'LIMIT_', $path);

        if ($this->activeLimit && $this->activeLimit !== $this->defaultLimit) {
            $this->request->session()->write($limitPath, $this->activeLimit);
        } else {
            $this->request->session()->delete($limitPath);
        }

        if (!empty($filterOptions)) {
            $this->request->session()->write($path, $filterOptions);
        } else {
            $this->request->session()->delete($path);
        }

        $this->controller->set('filter', [
            'activeFilters' => $this->activeFilters,
            'filterFields' => $this->filterFields,
            'activeSort' => $this->activeSort,
            'sortFields' => $this->sortFields,
            'paginationParams' => $this->paginationParams,
            'defaultSort' => $this->defaultSort
        ]);

        return true;
    }

    /**
     * Try to retrieve a filtered backlink from the session.
     *
     * @param array $url
     * @param Request $request
     * @return array
     */
    public static function getBacklink($url, Request $request)
    {
        $path = join('.', [
            'FILTER_' . ($url['plugin'] ? $url['plugin'] : ''),
            $url['controller'],
            $url['action']
        ]);

        if (($filterOptions = $request->session()->read($path))) {
            if (isset($filterOptions['slug'])) {
                $url['sluggedFilter'] = $filterOptions['slug'];
                unset($filterOptions['slug']);
            }
            if (!empty($filterOptions)) {
                if (!isset($url['?'])) {
                    $url['?'] = [];
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
    protected function _initFilterOptions()
    {
        if ((empty($this->request->query) && empty($this->defaultSort)) ||
            (empty($this->filterFields) && empty($this->sortFields))
        ) {
            return;
        }

        $options = [
            'conditions' => [],
            'order' => []
        ];

        // check filter params
        if (!empty($this->request->data)) {
            foreach ($this->request->data as $field => $value) {
                if (!isset($this->filterFields[$field]) || $value === '') {
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
                if (in_array($dir, ['asc', 'desc'])) {
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
     * @return boolean
     */
    protected function _isSortEnabled()
    {
        if (!isset($this->controller->sortFields)) {
            return false;
        }

        foreach ($this->controller->sortFields as $field) {
            if (isset($field['actions']) &&
                is_array($field['actions']) &&
                in_array($this->action, $field['actions'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if filtering for the current controller action is enabled.
     *
     * @return boolean
     */
    protected function _isFilterEnabled()
    {
        if (!isset($this->controller->filterFields)) {
            return false;
        }

        foreach ($this->controller->filterFields as $field) {
            if (isset($field['actions']) &&
                is_array($field['actions']) &&
                in_array($this->action, $field['actions'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if pagination is enabled for the current controller action.
     *
     * @return boolean
     */
    protected function _isPaginationEnabled()
    {
        if (!isset($this->controller->limits) ||
            !isset($this->controller->limits[$this->action]) ||
            !isset($this->controller->limits[$this->action]['default']) ||
            !isset($this->controller->limits[$this->action]['limits'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get all available sort fields for the current controller action.
     *
     * @return array
     */
    protected function _getSortFields()
    {
        $sortFields = [];

        foreach ($this->controller->sortFields as $field => $options) {
            if (isset($options['actions']) &&
                is_array($options['actions']) &&
                in_array($this->action, $options['actions'])
            ) {
                $sortFields[$field] = $options;
            }
        }

        return $sortFields;
    }

    /**
     * Get all available filter fields for the current controller action.
     *
     * @return array
     */
    protected function _getFilterFields()
    {
        $filterFields = [];

        foreach ($this->controller->filterFields as $field => $options) {
            if (isset($options['actions']) &&
                is_array($options['actions']) &&
                in_array($this->action, $options['actions'])
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
    protected function _createSortFieldOption($field, $dir, $options)
    {
        $sortField = $this->sortFields[$field];
        if (isset($sortField['custom'])) {
            if (!is_array($sortField['custom'])) {
                $sortField['custom'] = [$sortField['custom']];
            }
            foreach ($sortField['custom'] as $sortEntry) {
                $options['order'][] = $sortEntry;
            }
        } else {
            $options['order'][] = $sortField['modelField'] . ' ' . $dir;
        }

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
    protected function _createFilterFieldOption($field, $value, $options)
    {
        $filterField = $this->filterFields[$field];

        if (isset($filterField['type'])) {
            switch ($filterField['type']) {
                case 'like':
                    if (!is_array($filterField['modelField'])) {
                        $filterField['modelField'] = [$filterField['modelField']];
                    }
                    $orConditions = [];
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
                    $val = !is_array($value) ? [$value] : $value;
                    $options['conditions'][] = $filterField['modelField'] . ' IN(' . implode(', ', $val) . ')';
                    break;
                case 'custom':
                    if (isset($filterField['ifValueIs']) && $filterField['ifValueIs'] !== $value || !isset($filterField['customConditions'])) {
                        break;
                    }
                    if (!is_array($filterField['customConditions'])) {
                        if (is_callable($filterField['customConditions'])) {
                            $options['conditions'] = Hash::merge($options['conditions'], $filterField['customConditions']($value));
                        }
                    } else {
                        $options['conditions'] = Hash::merge($options['conditions'], $filterField['customConditions']);
                    }
                    break;
                case 'having':
                    $options['group'] = 'HAVING ' . $filterField['modelField'] . ' LIKE "%' . $value . '%"';
                    break;
                case 'date_range':
//						$options['conditions'][] = '';
                    break;
            }
        }

        return $options;
    }
}
