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
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use FrankFoerster\Filter\Model\Entity\Filter;
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
    public array $components = [
        'Session'
    ];

    /**
     * Request object.
     *
     * @var ServerRequest|null
     */
    public ?ServerRequest $request = null;

    /**
     * Controller object this Component is operating on.
     *
     * @var Controller|null
     */
    public ?Controller $controller = null;

    /**
     * The requested controller action
     *
     * @var string|null
     */
    public ?string $action = null;

    /**
     * Filter fields registered on the controller.
     *
     * @var array
     */
    public array $filterFields = [];

    /**
     * Filter options for find calls.
     *
     * @var array
     */
    public array $filterOptions = [];

    /**
     * Holds all active filters and their values.
     *
     * @var array
     */
    public array $activeFilters = [];

    /**
     * Holds the slug for the current active filters.
     *
     * @var string|null
     */
    public ?string $slug = null;

    /**
     * Sort fields registered on the controller.
     *
     * @var array
     */
    public array $sortFields = [];

    /**
     * Holds the active sort field (key) and its direction (value).
     *
     * @var array
     */
    public array $activeSort = [];

    /**
     * Holds the active limit.
     *
     * @var int
     */
    public int $activeLimit = -1;

    /**
     * Holds the default sort field (key) and its direction (value).
     *
     * @var array
     */
    public array $defaultSort = [];

    /**
     * @var int The current page.
     */
    public int $page = 1;

    /**
     * Holds all pagination params.
     *
     * @var array
     */
    public array $paginationParams = [];

    /**
     * Default pagination limit.
     *
     * @var int
     */
    public int $defaultLimit = 10;

    /**
     * Holds all available pagination limits.
     *
     * @var array
     */
    public array $limits = [];

    /**
     * Holds the FiltersTable instance.
     *
     * @var FiltersTable|Table
     */
    public FiltersTable|Table $Filters;

    /**
     * Tells if sort is enabled after initialization.
     *
     * @var bool
     */
    protected bool $_sortEnabled = false;

    /**
     * Tells if filters are enabled after initialization.
     *
     * @var bool
     */
    protected bool $_filterEnabled = false;

    /**
     * Tells if pagination is enabled after initialization.
     *
     * @var bool
     */
    protected bool $_paginationEnabled = false;


    /**
     * Passed Params should be declared in Controller::$filterPassParams[$action]
     * and are automatically passed through to the resulting filter url.
     *
     * @var array
     */
    protected array $_passParams = [];

    /**
     * Default config
     *
     * These are merged with user-provided config when the component is instantiated.
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'filterTable' => 'FrankFoerster/Filter.Filters'
    ];

    /**
     * Called before the controller’s beforeFilter method, but after the controller’s initialize() method.
     *
     * @param EventInterface $event
     */
    public function beforeFilter(EventInterface $event): void
    {
        $this->controller = $event->getSubject();
        $this->request = $this->controller->getRequest();
        $this->action = $this->request->getParam('action');
        $this->Filters = TableRegistry::getTableLocator()->get($this->getConfig('filterTable'));;

        $this->_setupSort();
        $this->_setupFilters();
        $this->_setupPagination();
    }

    /**
     * Called after the Controller::beforeFilter() and before the controller action.
     *
     * @param EventInterface $event
     * @return bool
     */
    public function startup(EventInterface $event): bool
    {
        if (!$this->_isFilterRequest()) {
            return true;
        }

        $this->_initFilterOptions();

        if ($this->request->is('post')) {
            $this->_extractPassParams();

            $url = ['action' => $this->action];

            $url = $this->_applyFilterData($url);
            $url = $this->_applyPassedParams($url);
            $url = $this->_applySort($url);

            $this->controller->redirect($url);
            return false;
        }

        return true;
    }

    /**
     * Apply filter conditions and order options to the given query.
     *
     * @param Query $query
     * @return Query
     */
    public function filter(Query $query): Query
    {
        if (!empty($this->filterOptions['conditions'])) {
            $query->where($this->filterOptions['conditions']);
        }
        if (!empty($this->filterOptions['order'])) {
            $query->orderBy($this->filterOptions['order']);
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
     * @param bool $total
     * @return Query
     */
    public function paginate(Query $query, array $countFields = [], bool $total = false): Query
    {
        $limit = $this->defaultLimit;
        if (in_array((integer)$this->request->getQuery('l', 0), $this->limits)) {
            $limit = (integer)$this->request->getQuery('l');
            $this->activeLimit = $limit;
        }
        if (!$this->activeLimit) {
            $lastLimit = $this->request->getSession()->read(join('.', [
                'LIMIT_' . $this->request->getParam('plugin'),
                $this->controller->getName(),
                $this->action
            ]));
            if ($lastLimit) {
                $limit = $lastLimit;
            }
            $this->activeLimit = $limit;
        }
        $this->request = $this->request->withData('l', $limit);

        if ($total === false) {
            $countQuery = clone $query;
            if (!empty($countFields)) {
                $countQuery->select($countFields);
            }
            $total = $countQuery->count();
        }

        if ($total !== null) {
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
                'limits' => array_combine($this->limits, $this->limits),
                'passParams' => []
            ];

            if (!empty($this->_passParams)) {
                $this->paginationParams['passParams'] = $this->_passParams;
            }
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
     * @param EventInterface $event
     * @return bool
     */
    public function beforeRender(EventInterface $event): bool
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

        $rememberPage = Configure::read('Filter.rememberPage');
        if ($rememberPage === null) {
            $rememberPage = true;
        }

        if (!empty($this->paginationParams) &&
            isset($this->paginationParams['page']) &&
            $this->paginationParams['page'] != 1 &&
            $rememberPage
        ) {
            $filterOptions['p'] = $this->paginationParams['page'];
        }

        $path = join('.', [
            'FILTER_' . $this->request->getParam('plugin'),
            $this->controller->getName(),
            $this->action
        ]);
        $limitPath = str_replace('FILTER_', 'LIMIT_', $path);

        if ($this->activeLimit && $this->activeLimit !== $this->defaultLimit) {
            $this->request->getSession()->write($limitPath, $this->activeLimit);
        } else {
            $this->request->getSession()->delete($limitPath);
        }

        if (!empty($filterOptions)) {
            $this->request->getSession()->write($path, $filterOptions);
        } else {
            $this->request->getSession()->delete($path);
        }

        $this->controller->set('filter', [
            'activeFilters' => $this->activeFilters,
            'filterFields' => $this->filterFields,
            'activeSort' => $this->activeSort,
            'sortFields' => $this->sortFields,
            'paginationParams' => $this->paginationParams,
            'defaultSort' => $this->defaultSort,
            'passParams' => $this->_passParams
        ]);

        return true;
    }

    /**
     * Try to retrieve a filtered backlink from the session.
     *
     * @param array $url
     * @param ServerRequest $request
     * @return array
     */
    public static function getBacklink(array $url, ServerRequest $request): array
    {
        if (!isset($url['plugin'])) {
            $url['plugin'] = $request->getParam('plugin');
        }
        if (!isset($url['controller'])) {
            $url['controller'] = $request->getParam('controller');
        }

        $path = join('.', [
            'FILTER_' . ($url['plugin'] ? $url['plugin'] : ''),
            $url['controller'],
            $url['action']
        ]);

        if (($filterOptions = $request->getSession()->read($path))) {
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
    protected function _initFilterOptions(): void
    {
        if (!$this->_filterEnabled && !$this->_sortEnabled) {
            return;
        }

        $options = [
            'conditions' => [],
            'order' => []
        ];

        // check filter params
        if (!empty($this->request->getData())) {
            foreach ($this->request->getData() as $field => $value) {
                if (!isset($this->filterFields[$field]) || $value === '') {
                    continue;
                }

                $options = $this->_createFilterFieldOption($field, $value, $options);

                $this->activeFilters[$field] = $value;
            }
        }

        if (isset($this->sortFields[$this->request->getQuery('s')])) {
            $d = 'asc';
            if ($this->request->getQuery('d')) {
                $dir = strtolower($this->request->getQuery('d'));
                if (in_array($dir, ['asc', 'desc'])) {
                    $d = $dir;
                }
            }
            $field = $this->request->getQuery('s');
            $options = $this->_createSortFieldOption($field, $d, $options);
            $this->activeSort[$field] = $d;
        } elseif (!empty($this->defaultSort)) {
            $options = $this->_createSortFieldOption($this->defaultSort['field'], $this->defaultSort['dir'], $options);
            $this->activeSort[$this->defaultSort['field']] = $this->defaultSort['dir'];
        }

        if ($this->request->getQuery('p')) {
            $this->page = $this->request->getQuery('p');
        }

        $this->filterOptions = $options;
    }

    /**
     * Check if sorting for the current controller action is enabled.
     *
     * @return bool
     */
    protected function _isSortEnabled(): bool
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
     * @return bool
     */
    protected function _isFilterEnabled(): bool
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
     * @return bool
     */
    protected function _isPaginationEnabled(): bool
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
    protected function _getSortFields(): array
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
    protected function _getFilterFields(): array
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
     * @return array
     */
    protected function _createSortFieldOption(string $field, string $dir, array $options): array
    {
        $sortField = $this->sortFields[$field];
        if (isset($sortField['custom'])) {
            if (!is_array($sortField['custom'])) {
                $sortField['custom'] = [$sortField['custom']];
            }
            foreach ($sortField['custom'] as $sortEntry) {
                $options['order'][] = preg_replace('/:dir/', $dir, $sortEntry);
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
    protected function _createFilterFieldOption(string $field, mixed $value, array $options): array
    {
        $filterField = $this->filterFields[$field];

        if (isset($filterField['type'])) {
            switch ($filterField['type']) {
                case 'like':
                    if (!is_array($filterField['modelField'])) {
                        $filterField['modelField'] = [$filterField['modelField']];
                    }
                    $conditions = [];
                    foreach ($filterField['modelField'] as $modelField) {
                        $conditions[] = $modelField . ' LIKE "%' . $value . '%"';
                    }
                    if (count($conditions) > 1) {
                        $conditions = ['OR' => $conditions];
                    }
                    $options['conditions'][] = $conditions;
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

    /**
     * Puts the values in the passParams array
     */
    protected function _extractPassParams(): void
    {
        if (!empty($this->controller->filterPassParams[$this->action])) {
            foreach ($this->controller->filterPassParams[$this->action] as $key) {
                if (!empty($this->request->getParam($key))) {
                    $this->_passParams[$key] = $this->request->getParam($key);
                }
            }
        }
    }

    /**
     * Get the filter data.
     *
     * @return array
     */
    protected function _getFilterData(): array
    {
        $rawFilterData = $this->request->getData();
        $filterData = [];
        foreach ($this->filterFields as $filterField => $options) {
            if (isset($rawFilterData[$filterField]) && $rawFilterData[$filterField] !== '') {
                $filterData[$filterField] = $rawFilterData[$filterField];
            }
        }

        return $filterData;
    }

    /**
     * Set up default sort options.
     *
     * @return void
     */
    protected function _setupSort(): void
    {
        if (!($this->_sortEnabled = $this->_isSortEnabled())) {
            return;
        }

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

    /**
     * Set up the filter field options.
     *
     * If the current request is provided with the sluggedFilter param,
     * then the corresponding filter data will be fetched and set on the request data.
     *
     * @return void
     */
    protected function _setupFilters(): void
    {
        if (!($this->_filterEnabled = $this->_isFilterEnabled())) {
            return;
        }

        $this->filterFields = $this->_getFilterFields();
        $sluggedFilter = $this->request->getParam('sluggedFilter', '');

        if ($sluggedFilter === '') {
            return;
        }

        $filterData = $this->Filters->find('filterDataBySlug', ['request' => $this->request])->toArray();

        if (empty($filterData)) {
            return;
        }

        $data = array_merge($this->request->getData(), $filterData);
        foreach ($data as $key => $value) {
            $this->request = $this->request->withData($key, $value);
        }
        $this->slug = $sluggedFilter;
    }

    /**
     * Set up the default pagination params.
     *
     * @return void
     */
    protected function _setupPagination(): void
    {
        if (!($this->_paginationEnabled = $this->_isPaginationEnabled())) {
            return;
        }

        $this->defaultLimit = $this->controller->limits[$this->action]['default'];
        $this->limits = $this->controller->limits[$this->action]['limits'];
    }

    /**
     * Check if the current request is a filter request.
     *
     * @return bool
     */
    protected function _isFilterRequest(): bool
    {
        return (
            $this->controller !== null  &&
            $this->request !== null &&
            $this->action !== null &&
            $this->_filterEnabled
        );
    }

    /**
     * Create a filter slug for the given filter data.
     *
     * @param array $filterData
     * @return string The slug.
     */
    protected function _createFilterSlug(array $filterData): string
    {
        /** @var Filter $existingFilter */
        $existingFilter = $this->Filters->find('slugForFilterData', [
            'request' => $this->request,
            'filterData' => $filterData
        ])->first();

        if ($existingFilter) {
            return $existingFilter->slug;
        }

        return $this->Filters->createFilterForFilterData($this->request, $filterData);
    }

    /**
     * Apply filter data to the given url.
     *
     * @param array $url
     * @return array modified url array
     */
    protected function _applyFilterData(array $url): array
    {
        $filterData = $this->_getFilterData();
        if (empty($filterData)) {
            return $url;
        }

        return $url + [
            'sluggedFilter' => $this->_createFilterSlug($filterData),
            '?' => $this->request->getQuery()
        ];
    }

    /**
     * Apply configured pass params to the url array.
     *
     * @param array $url
     * @return array modified url array
     */
    protected function _applyPassedParams(array $url): array
    {
        if (empty($this->_passParams)) {
            return $url;
        }

        return array_merge($url, $this->_passParams);
    }

    /**
     * Pass sort options through to the filtered url.
     *
     * @param array $url
     * @return array modified url array
     */
    protected function _applySort(array $url): array
    {
        if (!$this->_sortEnabled) {
            return $url;
        }

        if (!empty($this->request->getQuery('s'))) {
            $url['?']['s'] = $this->request->getQuery('s');
        }

        if (!empty($this->request->getQuery('d'))) {
            $url['?']['d'] = $this->request->getQuery('d');
        }

        return $url;
    }
}
