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

use Doctrine\DBAL\Connection;
use Neos\Arboretum\Domain\Entity\NodeAdapter;
use Neos\Arboretum\DoctrineDbalAdapter\Infrastructure\Service\DbalClient;
use Neos\Arboretum\Domain\Repository\AbstractContentSubgraph;
use Neos\ContentRepository\Domain as ContentRepository;
use Neos\Flow\Annotations as Flow;

/**
 * The content subgraph application repository
 *
 * To be used as a read-only source of nodes
 *
 * @api
 */
class ContentSubgraph extends AbstractContentSubgraph
{
    /**
     * @Flow\Inject
     * @var DbalClient
     */
    protected $client;

    /**
     * @param string $nodeIdentifier
     * @return ContentRepository\Model\NodeInterface|null
     */
    public function findNodeByIdentifier(string $nodeIdentifier)
    {
        $nodeData = $this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_arboretum_node n
 INNER JOIN neos_arboretum_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE n.identifierinsubgraph = :nodeIdentifier
 AND h.subgraphidentifier = :subgraphIdentifier',
            [
                'nodeIdentifier' => $nodeIdentifier,
                'subgraphIdentifier' => $this->identifier
            ]
        )->fetch();

        return $nodeData ? $this->mapRawDataToNode($nodeData) : null;
    }

    /**
     * @param string $parentIdentifier
     * @return array|ContentRepository\Model\NodeInterface[]
     */
    public function findNodesByParent(string $parentIdentifier): array
    {
        $result = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT c.* FROM neos_arboretum_node p
 INNER JOIN neos_arboretum_hierarchyedge h ON h.parentnodesidentifieringraph = p.identifieringraph
 INNER JOIN neos_arboretum_node c ON h.childnodesidentifieringraph = c.identifieringraph
 WHERE p.identifierinsubgraph = :parentIdentifier
 AND h.subgraphidentifier = :subgraphIdentifier
 ORDER BY h.position',
            [
                'parentIdentifier' => $parentIdentifier,
                'subgraphIdentifier' => $this->identifier
            ]
        )->fetchAll() as $nodeData) {
            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }

    /**
     * @param string $nodeTypeName
     * @return array|ContentRepository\Model\NodeInterface[]
     */
    public function findNodesByType(string $nodeTypeName): array
    {
        $result = [];
        foreach ($this->getDatabaseConnection()->executeQuery(
            'SELECT n.* FROM neos_arboretum_node n
 INNER JOIN neos_arboretum_hierarchyedge h ON h.childnodesidentifieringraph = n.identifieringraph
 WHERE n.nodetypename = :nodeTypeName
 AND h.subgraphidentifier = :subgraphIdentifier
 ORDER BY h.position',
            [
                'nodeTypeName' => $nodeTypeName,
                'subgraphIdentifier' => $this->identifier
            ]
        )->fetchAll() as $nodeData) {
            $result[] = $this->mapRawDataToNode($nodeData);
        }

        return $result;
    }

    public function traverse(ContentRepository\Model\NodeInterface $parent, callable $callback)
    {
        $callback($parent);
        foreach ($this->findNodesByParent($parent->getIdentifier()) as $childNode) {
            $this->traverse($childNode, $callback);
        }
    }

    protected function mapRawDataToNode(array $nodeData): ContentRepository\Model\NodeInterface
    {
        $node = new NodeAdapter($this);
        $node->nodeType = $this->nodeTypeManager->getNodeType($nodeData['nodetypename']);
        $node->identifier = $nodeData['identifierinsubgraph'];
        $node->properties = new ContentRepository\Model\PropertyCollection(json_decode($nodeData['properties'], true));

        return $node;
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->client->getConnection();
    }
}
