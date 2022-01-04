<?php
namespace CRON\NeosCliTools\Utility;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;

/**
 * @property int limit
 * @property TraversableNodeInterface rootNode
 * @property TraversableNodeInterface[] nodes
 */
class NeosDocumentWalker
{
    /**
     * @Flow\Inject
     * @var NodeTypeConstraintFactory
     */
    protected $nodeTypeConstraintFactory;

    public function __construct(TraversableNodeInterface $rootNode)
    {
        $this->rootNode = $rootNode;
    }

    private function walk(TraversableNodeInterface $node) {

        foreach ($node->findChildNodes($this->nodeTypeConstraintFactory->parseFilterString('Neos.Neos:Document')) as $childNode) {
            if ($this->limit && count($this->nodes) >= $this->limit) {
                return;
            }
            $this->walk($childNode);
        }
        if ($this->limit && count($this->nodes) >= $this->limit) { return; }

        $this->nodes[] = $node;
    }

    /**
     * Walk all nodes recursively and returns the leaves first
     *
     * @param int $limit
     *
     * @return array|TraversableNodeInterface[]
     */
    public function getNodes(int $limit = 0): array
    {
        $this->limit = $limit;
        $this->nodes = [];
        $this->walk($this->rootNode);

        return $this->nodes;
    }
}
