<?php
namespace CRON\NeosCliTools\Utility;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;

/**
 * @property int limit
 * @property NodeInterface rootNode
 * @property NodeInterface[] nodes
 */
class NeosDocumentWalker
{
    /**
     * @Flow\Inject
     * @var NodeTypeConstraintFactory
     */
    protected $nodeTypeConstraintFactory;

    public function __construct(NodeInterface $rootNode)
    {
        $this->rootNode = $rootNode;
    }

    private function walk(NodeInterface $node) {

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
     * @return array|NodeInterface[]
     */
    public function getNodes(int $limit = 0): array
    {
        $this->limit = $limit;
        $this->nodes = [];
        $this->walk($this->rootNode);

        return $this->nodes;
    }
}
