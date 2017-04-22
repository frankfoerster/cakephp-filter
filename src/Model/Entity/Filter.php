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
namespace FrankFoerster\Filter\Model\Entity;

use Cake\ORM\Entity;

/**
 * Class Filter
 *
 * @property int $id
 * @property string $plugin
 * @property string $controller
 * @property string $action
 * @property string $slug
 * @property string $filter_data
 * @property \DateTime $created
 */
class Filter extends Entity
{
}
