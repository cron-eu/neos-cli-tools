<?php
namespace CRON\NeosCliTools\Utility;

use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * @property int limit
 * @property NodeInterface rootNode
 * @property NodeInterface[] nodes
 */
class NeosDocumentWalker
{
    public function __construct(NodeInterface $rootNode)
    {
        $this->rootNode = $rootNode;
    }

    private function walk(NodeInterface $node) {

        foreach ($node->getChildNodes('Neos.Neos:Document') as $childNode) {
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
