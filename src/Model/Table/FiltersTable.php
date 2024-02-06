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
namespace FrankFoerster\Filter\Model\Table;

use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\ORM\Table;
use FrankFoerster\Filter\Model\Entity\Filter;

class FiltersTable extends Table
{
    /**
     * Initialize a table instance. Called after the constructor.
     *
     * @param array $config
     */
    public function initialize(array $config): void
    {
        $this->setTable('frank_foerster_filter_filters');
        $this->addBehavior('Timestamp');
    }

    /**
     * Find a slug by the provided filter data ($options['filterData'].
     *
     * @param Query $query
     * @param array $options
     * @return Query
     */
    public function findSlugForFilterData(Query $query, array $options): Query
    {
        if (!isset($options['request']) || get_class($options['request']) !== ServerRequest::class) {
            user_error('The request query option must exist and must be of type Cake\Http\ServerRequest.');
        }

        if (!isset($options['filterData'])) {
            user_error('No filterData option provided.');
        }

        /** @var ServerRequest $request */
        $request = $options['request'];
        $filterData = $options['filterData'];

        return $query
            ->select($this->getAlias() . '.slug')
            ->where([
                $this->getAlias() . '.controller' => $request->getParam('controller'),
                $this->getAlias() . '.action' => $request->getParam('action'),
                $this->getAlias() . '.filter_data' => $this->_encodeFilterData($filterData)
            ])
            ->where($this->_pluginCondition($request));
    }

    /**
     * Find stored filter data for a given slug.
     *
     * @param Query $query
     * @param array $options
     * @return array
     */
    public function findFilterDataBySlug(Query $query, array $options): array
    {
        if (!isset($options['request']) || get_class($options['request']) !== ServerRequest::class) {
            user_error('The request query option must exist and must be of type Cake\Http\ServerRequest.');
        }

        $encryptedFilterData = $this->_findEncryptedFilterData($query, $options['request'])->first();

        if ($encryptedFilterData) {
            $encryptedFilterData = $encryptedFilterData->toArray();
            return $this->_decodeFilterData($encryptedFilterData['filter_data']);
        }

        return [];
    }

    /**
     * Create a new filter entry for the given request and filter data.
     *
     * @param ServerRequest $request
     * @param array $filterData
     * @return string The slug representing the given $filterData.
     */
    public function createFilterForFilterData(ServerRequest $request, array $filterData): string
    {
        $charlist = 'abcdefghikmnopqrstuvwxyz';

        do {
            $slug = '';
            for ($i = 0; $i < 14; $i++) {
                $slug .= substr($charlist, rand(0, 31), 1);
            }
        } while ($this->_slugExists($slug, $request));

        $this->save(new Filter([
            'plugin' => $request->getParam('plugin'),
            'controller' => $request->getParam('controller'),
            'action' => $request->getParam('action'),
            'slug' => $slug,
            'filter_data' => $this->_encodeFilterData($filterData)
        ]));

        return $slug;
    }

    /**
     * Encode a filter data array to a string.
     *
     * @param array $filterData
     * @return string
     */
    protected function _encodeFilterData(array $filterData): string
    {
        return json_encode($filterData);
    }

    /**
     * Decode a filter data string to the original filter data array.
     *
     * @param string $encodedFilterData
     * @return array
     */
    protected function _decodeFilterData(string $encodedFilterData): array
    {
        return json_decode($encodedFilterData, true);
    }

    /**
     * Get the plugin query condition for a given request.
     *
     * @param ServerRequest $request
     * @return array
     */
    protected function _pluginCondition(ServerRequest $request): array
    {
        if ($request->getParam('plugin') !== null) {
            return [$this->getAlias() . '.plugin' => $request->getParam('plugin')];
        }

        return [$this->getAlias() . '.plugin IS NULL'];
    }

    /**
     * Check if a slug for the given request params already exists.
     *
     * @param string $slug
     * @param ServerRequest $request
     * @return bool
     */
    protected function _slugExists(string $slug, ServerRequest $request): bool
    {
        $existingSlug = $this->find('all')
            ->select($this->getAlias() . '.slug')
            ->where([
                $this->getAlias() . '.slug' => $slug,
                $this->getAlias() . '.controller' => $request->getParam('controller'),
                $this->getAlias() . '.action' => $request->getParam('action')
            ])
            ->where($this->_pluginCondition($request))
            ->enableHydration(false)
            ->first();

        return $existingSlug !== null;
    }

    /**
     * Find encrypted filter data for the given request and the provided sluggedFilter.
     *
     * @param Query $query
     * @param ServerRequest $request
     * @return Query
     */
    protected function _findEncryptedFilterData(Query $query, ServerRequest $request): Query
    {
        return $query
            ->select($this->getAlias() . '.filter_data')
            ->where([
                $this->getAlias() . '.controller' => $request->getParam('controller'),
                $this->getAlias() . '.action' => $request->getParam('action'),
                $this->getAlias() . '.slug' => $request->getParam('sluggedFilter')
            ])
            ->where($this->_pluginCondition($request));
    }
}
