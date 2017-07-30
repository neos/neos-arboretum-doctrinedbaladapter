<?php

namespace Neos\Arboretum\DoctrineDbalAdapter\Domain\Projection;

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
use Neos\Arboretum\DoctrineDbalAdapter\Domain\Repository\ProjectionContentGraph;
use Neos\Arboretum\DoctrineDbalAdapter\Infrastructure\Dto\HierarchyEdge;
use Neos\Arboretum\DoctrineDbalAdapter\Infrastructure\Service\DbalClient;
use Neos\Arboretum\Domain\Projection\AbstractGraphProjector;
use Neos\Arboretum\Infrastructure\Dto\Node;
use Neos\Flow\Annotations as Flow;

/**
 * The alternate reality-aware graph projector for the Doctrine backend
 *
 * @Flow\Scope("singleton")
 */
class GraphProjector extends AbstractGraphProjector
{
    /**
     * @Flow\Inject
     * @var ProjectionContentGraph
     */
    protected $contentGraph;

    /**
     * @Flow\Inject
     * @var DbalClient
     */
    protected $client;

    public function reset()
    {
        $this->getDatabaseConnection()->transactional(function () {
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table neos_arboretum_node');
            $this->getDatabaseConnection()->executeQuery('TRUNCATE table neos_arboretum_hierarchyedge');
        });
    }

    public function isEmpty(): bool
    {
        return $this->contentGraph->isEmpty();
    }

    protected function connectRelation(
        string $startNodesIdentifierInGraph,
        string $endNodesIdentifierInGraph,
        string $relationshipName,
        array $properties,
        array $subgraphIdentifiers
    ) {
        // TODO: Implement connectRelation() method.
    }


    /*
    public function whenPropertiesWereUpdated(Event\PropertiesWereUpdated $event)
    {
        $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier());
        $node->properties = Arrays::arrayMergeRecursiveOverrule($node->properties, $event->getProperties());
        $this->update($node);

        $this->projectionPersistenceManager->persistAll();
    }*/

    /*
    public function whenNodeWasMoved(Event\NodeWasMoved $event)
    {
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);
        $affectedSubgraphIdentifiers = $event->getStrategy() === Event\NodeWasMoved::STRATEGY_CASCADE_TO_ALL_VARIANTS
            ? $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier)
            : $this->fallbackGraphService->determineConnectedSubgraphIdentifiers($subgraphIdentifier);

        foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($event->getVariantIdentifier(),
            $affectedSubgraphIdentifiers) as $variantEdge) {
            if ($event->getNewParentVariantIdentifier()) {
                $variantEdge->parentNodesIdentifierInGraph = $event->getNewParentVariantIdentifier();
            }
            // @todo: handle new older sibling

            $this->update($variantEdge);
        }

        $this->projectionPersistenceManager->persistAll();
    }*/

    /*
    public function whenNodeWasRemoved(Event\NodeWasRemoved $event)
    {
        $node = $this->nodeFinder->findOneByIdentifierInGraph($event->getVariantIdentifier());
        $subgraphIdentifier = $this->extractSubgraphIdentifierFromEvent($event);

        if ($node->subgraphIdentifier === $subgraphIdentifier) {
            foreach ($this->hierarchyEdgeFinder->findByChildNodeIdentifierInGraph($event->getVariantIdentifier()) as $inboundEdge) {
                $this->remove($inboundEdge);
            }
            $this->remove($node);
        } else {
            $affectedSubgraphIdentifiers = $this->fallbackGraphService->determineAffectedVariantSubgraphIdentifiers($subgraphIdentifier);
            foreach ($this->hierarchyEdgeFinder->findInboundByNodeAndSubgraphs($event->getVariantIdentifier(),
                $affectedSubgraphIdentifiers) as $affectedInboundEdge) {
                $this->remove($affectedInboundEdge);
            }
        }

        // @todo handle reference edges

        $this->projectionPersistenceManager->persistAll();
    }*/

    /*
    public function whenNodeReferenceWasAdded(Event\NodeReferenceWasAdded $event)
    {
        $referencingNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencingNodeIdentifier());
        $referencedNode = $this->nodeFinder->findOneByIdentifierInGraph($event->getReferencedNodeIdentifier());

        $affectedSubgraphIdentifiers = [];
        // Which variant reference edges are created alongside is determined by what subgraph this node belongs to
        // @todo define a more fitting method in a service for this
        $inboundHierarchyEdges = $this->hierarchyEdgeFinder->findByChildNodeIdentifierInGraph($event->getReferencingNodeIdentifier());

        foreach ($inboundHierarchyEdges as $hierarchyEdge) {
            $referencedNodeVariant = $this->nodeFinder->findInSubgraphByIdentifierInSubgraph($referencingNode->identifierInSubgraph,
                $hierarchyEdge->subgraphIdentifier);
            if ($referencedNodeVariant) {
                $referenceEdge = new ReferenceEdge();
                $referenceEdge->connect($referencingNode, $referencedNode, $hierarchyEdge->name,
                    $event->getReferenceName());
                // @todo fetch position among siblings
                // @todo implement auto-triggering of position recalculation
            }
        }
    }*/


    protected function getNode(string $identifierInGraph): Node
    {
        return $this->contentGraph->getNode($identifierInGraph);
    }

    protected function addNode(Node $node)
    {
        $this->getDatabaseConnection()->insert('neos_arboretum_node', [
            'identifieringraph' => $node->identifierInGraph,
            'identifierinsubgraph' => $node->identifierInSubgraph,
            'subgraphidentifier' => $node->subgraphIdentifier,
            'properties' => json_encode($node->properties),
            'nodetypename' => $node->nodeTypeName
        ]);
    }

    protected function connectHierarchy(
        string $parentNodesIdentifierInGraph,
        string $childNodeIdentifierInGraph,
        string $elderSiblingsIdentifierInGraph = null,
        string $name = null,
        array $subgraphIdentifiers
    ) {
        $position = $this->getEdgePosition($parentNodesIdentifierInGraph, $elderSiblingsIdentifierInGraph, reset($subgraphIdentifiers));

        foreach ($subgraphIdentifiers as $subgraphIdentifier) {
            $hierarchyEdge = new HierarchyEdge(
                $parentNodesIdentifierInGraph,
                $childNodeIdentifierInGraph,
                $name,
                $subgraphIdentifier,
                $position
            );
            $this->addHierarchyEdge($hierarchyEdge);
        }
    }

    protected function getEdgePosition(string $parentIdentifier, string $elderSiblingIdentifier = null, string $subgraphIdentifier): int
    {
        $position = $this->contentGraph->getEdgePosition($parentIdentifier, $elderSiblingIdentifier, $subgraphIdentifier);

        if ($position % 2 !== 0) {
            $position = $this->getEdgePositionAfterRecalculation($parentIdentifier, $elderSiblingIdentifier, $subgraphIdentifier);
        }

        return $position;
    }

    protected function getEdgePositionAfterRecalculation(string $parentIdentifier, string $elderSiblingIdentifier, string $subgraphIdentifier): int
    {
        $offset = 0;
        $position = 0;
        foreach ($this->contentGraph->getOutboundHierarchyEdgesForNodeAndSubgraph($parentIdentifier, $subgraphIdentifier) as $edge) {
            $this->assignNewPositionToHierarchyEdge($edge, $offset);
            $offset += 128;
            if ($edge->getChildNodesIdentifierInGraph() === $elderSiblingIdentifier) {
                $position = $offset;
                $offset += 128;
            }
        }

        return $position;
    }

    protected function reconnectHierarchy(
        string $fallbackNodesIdentifierInGraph,
        string $newVariantNodesIdentifierInGraph,
        array $subgraphIdentifiers
    ) {
        $inboundEdges = $this->contentGraph->findInboundHierarchyEdgesForNodeAndSubgraphs(
            $fallbackNodesIdentifierInGraph,
            $subgraphIdentifiers
        );
        $outboundEdges = $this->contentGraph->findOutboundHierarchyEdgesForNodeAndSubgraphs(
            $fallbackNodesIdentifierInGraph,
            $subgraphIdentifiers
        );

        foreach ($inboundEdges as $inboundEdge) {
            $this->assignNewChildNodeToHierarchyEdge($inboundEdge, $newVariantNodesIdentifierInGraph);
        }
        foreach ($outboundEdges as $outboundEdge) {
            $this->assignNewParentNodeToHierarchyEdge($outboundEdge, $newVariantNodesIdentifierInGraph);
        }
    }

    protected function addHierarchyEdge(HierarchyEdge $edge)
    {
        $this->getDatabaseConnection()->insert('neos_arboretum_hierarchyedge', $edge->toDatabaseArray());
    }

    protected function assignNewPositionToHierarchyEdge(HierarchyEdge $edge, int $position)
    {
        $this->getDatabaseConnection()->update(
            'neos_arboretum_hierarchyedge',
            [
                'position' => $position
            ],
            $edge->getDatabaseIdentifier()
        );
    }

    protected function assignNewChildNodeToHierarchyEdge(HierarchyEdge $edge, string $childNodeIdentifierInGraph)
    {
        $this->getDatabaseConnection()->update(
            'neos_arboretum_hierarchyedge',
            [
                'childnodesidentifieringraph' => $childNodeIdentifierInGraph,
            ],
            $edge->getDatabaseIdentifier()
        );
    }

    protected function assignNewParentNodeToHierarchyEdge(HierarchyEdge $edge, string $parentNodeIdentifierInGraph)
    {
        $this->getDatabaseConnection()->update(
            'neos_arboretum_hierarchyedge',
            [
                'parentnodesidentifieringraph' => $parentNodeIdentifierInGraph,
            ],
            $edge->getDatabaseIdentifier()
        );
    }


    protected function transactional(callable $operations)
    {
        $this->getDatabaseConnection()->transactional($operations);
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->client->getConnection();
    }
}
