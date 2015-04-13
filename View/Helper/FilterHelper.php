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

App::uses('AppHelper', 'View/Helper');

/**
 * @property View $_View
 * @property HtmlHelper $Html
 */
class FilterHelper extends AppHelper {

	/**
	 * Helpers used by this helper.
	 *
	 * @var array
	 */
	public $helpers = array(
		'Html'
	);

	/**
	 * Render a sort link to use in table headers.
	 *
	 * @param string $name The name(title) of the link.
	 * @param string $field The column name to sort on.
	 * @param array $options
	 * @return string
	 */
	public function sortLink($name, $field, $options = array()) {
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

	/**
	 * Render the pagination.
	 *
	 * @param int $maxPageNumbers
	 * @param string $itemType
	 * @param string $class
	 * @return string
	 */
	public function pagination($maxPageNumbers = 10, $itemType = 'Items', $class = '') {
		if (empty($this->_View->paginationParams)) {
			return '';
		}
		$page = (integer) $this->_View->paginationParams['page'];
		$pages = (integer) $this->_View->paginationParams['pages'];
		$pagesOnLeft = floor($maxPageNumbers/2) - 1;
		$pagesOnRight = $maxPageNumbers - $pagesOnLeft - 1;
		$minPage = $page - $pagesOnLeft;
		$maxPage = $page + $pagesOnRight;
		$firstUrl = false;
		$prevUrl = false;
		$pageLinks = array();
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
				'page' => (int) $p,
				'url' => $this->_getPaginatedUrl($p),
				'active' => ((int) $p === $page)
			);
			$pageLinks[] = $link;
		}
		if ($page < $pages) {
			$nextUrl = $this->_getPaginatedUrl($page + 1);
			$lastUrl = $this->_getPaginatedUrl($pages);
		}
		return $this->_View->element('Filter.pagination', array(
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
	public function getBacklink($url) {
		App::uses('FilterComponent', 'Filter.Controller/Component');
		return FilterComponent::getBacklink($url);
	}

    /**
     * Get a paginated url for the provided $page number with or without limit.
     *
     * @param int $page
     * @param bool $withLimit
     * @return array
     */
    public function getPaginatedUrl($page, $withLimit = true) {
        $url = $this->_getPaginatedUrl($page);

        if (!$withLimit && isset($url['?']) && isset($url['?']['l'])) {
            unset($url['?']['l']);
        }

        return $url;
    }

	/**
	 * Get a paginated url for the provided $page number.
	 *
	 * @param int $page
	 * @return array
	 */
	protected function _getPaginatedUrl($page) {
		$url = $this->_getSortUrl();

		if ($page > 1) {
			if (!isset($url['?'])) {
				$url['?'] = array();
			}
			$url['?']['p'] = $page;
		}

		return $url;
	}

	/**
	 * Get the sort url for the provided $field.
	 *
	 * @param string $field
	 * @return array
	 */
	protected function _getSortUrl($field = '') {
		$url = $this->_getFilterUrl();

		if ($field === '' && empty($this->_View->activeSort)) {
			return $url;
		}

		if (!isset($url['?'])) {
			$url['?'] = array();
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
			$fields = array_keys($this->_View->activeSort);
            $field = $fields[0];
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
	 * Get the filter url.
	 *
	 * @param bool $withLimit
	 * @return array
	 */
	protected function _getFilterUrl($withLimit = true) {
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
