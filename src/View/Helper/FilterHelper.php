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
use Cake\View\Helper;
use Cake\View\View;
use Cake\View\Helper\HtmlHelper;
use FrankFoerster\Filter\Controller\Component\FilterComponent;

/**
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
    public $helpers = array(
        'Html'
    );

    public function sortLink($name, $field, $options = [])
    {
        $url = $this->_getSortUrl($field);

        $iClass = 'icon-sortable';
        if (isset($this->_View->activeSort[$field])) {
            $iClass .= '-' . $this->_View->activeSort[$field];
        }

        if (isset($options['icon-theme'])) {
            $iClass .= ' ' . $options['icon-theme'];
            unset($options['icon-theme']);
        }

        $name = '<span>' . $name . '</span><i class="' . $iClass . '"></i>';
        $options['escape'] = false;

        return $this->Html->link($name, $url, $options);
    }

    public function pagination($maxPageNumbers = 10, $itemType = 'Items', $element = 'Filter/pagination', $class = '')
    {
        if (empty($this->_View->paginationParams)) {
            return '';
        }
        $page = (integer)$this->_View->paginationParams['page'];
        $pages = (integer)$this->_View->paginationParams['pages'];
        $pagesOnLeft = floor($maxPageNumbers / 2) - 1;
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
            $maxPage = $minPage + $maxPageNumbers;
        }
        if ($maxPage > $pages) {
            $maxPage = $pages;
            $minPage = $maxPage + 1 - $maxPageNumbers;
        }
        if ($minPage < 1) {
            $minPage = 1;
        }
        foreach (range($minPage, $maxPage) as $p) {
            $link = array(
                'page' => (int)$p,
                'url' => $this->_getPaginatedUrl($p),
                'active' => ((int)$p === $page)
            );
            $pageLinks[] = $link;
        }
        if ($page < $pages) {
            $nextUrl = $this->_getPaginatedUrl($page + 1);
            $lastUrl = $this->_getPaginatedUrl($pages);
        }
        return $this->_View->element($element, array(
            'total' => $this->_View->paginationParams['total'],
            'itemType' => $itemType,
            'first' => $firstUrl,
            'prev' => $prevUrl,
            'pages' => $pageLinks,
            'next' => $nextUrl,
            'last' => $lastUrl,
            'class' => $class,
            'baseUrl' => Router::url($this->_getFilterUrl(false)),
            'from' => $this->_View->paginationParams['from'],
            'to' => $this->_View->paginationParams['to']
        ));
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
     * @param string $field
     * @return array
     */
    protected function _getSortUrl($field = '')
    {
        $url = $this->_getFilterUrl();

        if ($field === '' && empty($this->_View->activeSort)) {
            return $url;
        }

        if (!isset($url['?'])) {
            $url['?'] = [];
        }

        if ($field !== '') {
            $url['?']['s'] = $field;
            $dir = 'asc';
            if (isset($this->_View->activeSort[$field])) {
                if ($this->_View->activeSort[$field] === 'asc') {
                    $url['?']['d'] = 'desc';
                    $dir = 'desc';
                }
            }
            if ($field === $this->_View->defaultSort['field'] && $dir === $this->_View->defaultSort['dir']) {
                unset($url['?']['s']);
                if (isset($url['?']['d'])) {
                    unset($url['?']['d']);
                }
            }

            return $url;
        }

        if (!empty($this->_View->activeSort)) {
            $field = array_keys($this->_View->activeSort)[0];
            $dir = $this->_View->activeSort[$field];
            if (($field === $this->_View->defaultSort['field'] && $dir !== $this->_View->defaultSort['dir']) ||
                $field !== $this->_View->defaultSort['field']
            ) {
                $url['?']['s'] = $field;
                if ($this->_View->activeSort[$field] !== 'asc') {
                    $url['?']['d'] = 'desc';
                }
            }
        }

        return $url;
    }

    /**
     * @param boolean $withLimit
     * @return array
     */
    protected function _getFilterUrl($withLimit = true)
    {
        $url = array(
            'plugin' => $this->request->params['plugin'],
            'controller' => $this->request->params['controller'],
            'action' => $this->request->params['action'],
        );

        if (isset($this->request->params['sluggedFilter'])) {
            $url['sluggedFilter'] = $this->request->params['sluggedFilter'];
        };

        if ($withLimit &&
            isset($this->request->data['l']) &&
            $this->request->data['l'] !== $this->_View->paginationParams['defaultLimit']
        ) {
            $url['?']['l'] = $this->request->data['l'];
        }

        return $url;
    }
}
