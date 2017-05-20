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
namespace FrankFoerster\Filter\View\Helper;

use Cake\Routing\Router;
use Cake\Utility\Hash;
use Cake\View\Helper;
use Cake\View\View;
use Cake\View\Helper\HtmlHelper;
use FrankFoerster\Filter\Controller\Component\FilterComponent;

/**
 * FilterHelper
 *
 * @property View $_View
 * @property HtmlHelper $Html
 */
class FilterHelper extends Helper
{
    /**
     * Helpers used by this helper.
     *
     * @var array
     */
    public $helpers = [
        'Html'
    ];

    /**
     * Holds all active filters and their values.
     *
     * @var array
     */
    public $activeFilters = [];

    /**
     * Filter fields registered on the controller.
     *
     * @var array
     */
    public $filterFields = [];

    /**
     * Holds the active sort field (key) and its direction (value).
     *
     * @var array
     */
    public $activeSort = [];

    /**
     * Sort fields registered on the controller.
     *
     * @var array
     */
    public $sortFields = [];

    /**
     * Holds the default sort field (key) and its direction (value).
     *
     * @var array
     */
    public $defaultSort = [];

    /**
     * Holds all pagination params.
     *
     * @var array
     */
    public $paginationParams = [];

    /**
     * Holds additional params that should be passed to the filter url.
     *
     * @var array
     */
    protected $_passParams = [];

    /**
     * Constructor
     *
     * @param View $View The view initializing the Filter helper instance.
     * @param array $config Configuration options passed to the constructor.
     */
    public function __construct(View $View, array $config = [])
    {
        $filterOptions = Hash::get($View->viewVars, 'filter', []);

        foreach ($filterOptions as $key => $val) {
            $this->{$key} = $val;
        }

        parent::__construct($View, $config);
    }

    public function sortLink($name, $field, $options = [])
    {
        $url = $this->_getSortUrl($field);

        $iClass = 'icon-sortable';
        if (isset($this->activeSort[$field])) {
            $iClass .= '-' . $this->activeSort[$field];
        }

        if (isset($options['icon-theme'])) {
            $iClass .= ' ' . $options['icon-theme'];
            unset($options['icon-theme']);
        }

        $name = '<span>' . $name . '</span><i class="' . $iClass . '"></i>';
        $options['escape'] = false;

        return $this->Html->link($name, $url, $options);
    }

    /**
     * Render the pagination.
     *
     * @param int $maxPageNumbers The maximum number of pages to show in the link list.
     * @param string $itemType The item type that is paginated.
     * @param string $class An optional css class for the pagination.
     * @param string $element The pagination element to render.
     * @param array $passParams Optional params passed to the filter urls.
     * @return string
     */
    public function pagination($maxPageNumbers = 10, $itemType = 'Items', $class = '', $element = 'Filter/pagination', $passParams = [])
    {
        if (empty($this->paginationParams)) {
            return '';
        }

        $this->_passParams = array_merge($this->paginationParams['passParams'], $passParams);

        $page = (integer)$this->paginationParams['page'];
        $pages = (integer)$this->paginationParams['pages'];
        $pagesOnLeft = floor($maxPageNumbers / 2);
        $pagesOnRight = $maxPageNumbers - $pagesOnLeft - 1;
        $minPage = $page - $pagesOnLeft;
        $maxPage = $page + $pagesOnRight;
        $firstUrl = false;
        $prevUrl = false;
        $pageLinks = [];
        $nextUrl = false;
        $lastUrl = false;

        if ($page > 1) {
            $firstUrl = $this->_getPaginatedUrl(1);
            $prevUrl = $this->_getPaginatedUrl($page - 1);
        }
        if ($minPage < 1) {
            $minPage = 1;
            $maxPage = $maxPageNumbers;
        }
        if ($maxPage > $pages) {
            $maxPage = $pages;
            $minPage = $maxPage + 1 - $maxPageNumbers;
        }
        if ($minPage < 1) {
            $minPage = 1;
        }
        foreach (range($minPage, $maxPage) as $p) {
            $link = [
                'page' => (int)$p,
                'url' => $this->_getPaginatedUrl($p),
                'active' => ((int)$p === $page)
            ];
            $pageLinks[] = $link;
        }
        if ($page < $pages) {
            $nextUrl = $this->_getPaginatedUrl($page + 1);
            $lastUrl = $this->_getPaginatedUrl($pages);
        }
        return $this->_View->element($element, [
            'total' => $this->paginationParams['total'],
            'itemType' => $itemType,
            'first' => $firstUrl,
            'prev' => $prevUrl,
            'pages' => $pageLinks,
            'next' => $nextUrl,
            'last' => $lastUrl,
            'class' => $class,
            'currentPage' => $page,
            'baseUrl' => Router::url($this->_getFilterUrl(false)),
            'from' => $this->paginationParams['from'],
            'to' => $this->paginationParams['to'],
            'nrOfPages' => $pages
        ]);
    }

    /**
     * Try to retrieve a filtered backlink from the session.
     *
     * @param array $url proper route array
     * @return array
     */
    public function getBacklink($url)
    {
        return FilterComponent::getBacklink($url, $this->request);
    }

    /**
     * Get a paginated url for the given $page number.
     *
     * @param int $page
     * @return array
     */
    protected function _getPaginatedUrl($page)
    {
        $url = $this->_getSortUrl();

        if ($page > 1) {
            if (!isset($url['?'])) {
                $url['?'] = [];
            }
            $url['?']['p'] = $page;
        }

        return $url;
    }

    /**
     * Get a sort url for the given $field.
     *
     * @param string $field The field to sort.
     * @return array
     */
    protected function _getSortUrl($field = '')
    {
        $url = $this->_getFilterUrl();

        if ($field === '' && empty($this->activeSort)) {
            return $url;
        }

        if (!isset($url['?'])) {
            $url['?'] = [];
        }

        if ($field !== '') {
            $url['?']['s'] = $field;
            $dir = 'asc';
            if (isset($this->activeSort[$field])) {
                if ($this->activeSort[$field] === 'asc') {
                    $url['?']['d'] = 'desc';
                    $dir = 'desc';
                }
            }
            if ($field === $this->defaultSort['field'] && $dir === $this->defaultSort['dir']) {
                unset($url['?']['s']);
                if (isset($url['?']['d'])) {
                    unset($url['?']['d']);
                }
            }

            return $url;
        }

        if (!empty($this->activeSort)) {
            $field = array_keys($this->activeSort)[0];
            $dir = $this->activeSort[$field];
            if (($field === $this->defaultSort['field'] && $dir !== $this->defaultSort['dir']) ||
                $field !== $this->defaultSort['field']
            ) {
                $url['?']['s'] = $field;
                if ($this->activeSort[$field] !== 'asc') {
                    $url['?']['d'] = 'desc';
                }
            }
        }

        return $url;
    }

    /**
     * Get the filter url.
     *
     * @param boolean $withLimit
     * @return array
     */
    protected function _getFilterUrl($withLimit = true)
    {
        $url = [
            'plugin' => $this->request->params['plugin'],
            'controller' => $this->request->params['controller'],
            'action' => $this->request->params['action'],
        ];

        foreach($this->_passParams as $name => $value) {
            $url[$name] = $value;
        }

        if (isset($this->request->params['sluggedFilter'])) {
            $url['sluggedFilter'] = $this->request->params['sluggedFilter'];
        };

        if ($withLimit &&
            isset($this->request->data['l']) &&
            $this->request->data['l'] !== $this->paginationParams['defaultLimit']
        ) {
            $url['?']['l'] = $this->request->data['l'];
        }

        return $url;
    }
}
