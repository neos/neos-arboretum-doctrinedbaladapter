<?php

namespace Neos\Arboretum\DoctrineDbalAdapter\Domain\Repository;

/*
 * This file is part of the Neos.Arboretum.DoctrineDbalAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Neos\Arboretum\Domain as Arboretum;
use Neos\Flow\Annotations as Flow;

/**
 * The Doctrine DBAL adapter content graph
 *
 * To be used as a read-only source of nodes
 *
 * @Flow\Scope("singleton")
 * @api
 */
class ContentGraph extends Arboretum\Repository\AbstractContentGraph
{
    protected function createSubgraph(string $editingSessionName, array $dimensionValues): Arboretum\Repository\ContentSubgraphInterface
    {
        return new ContentSubgraph($editingSessionName, $dimensionValues);
    }
}
