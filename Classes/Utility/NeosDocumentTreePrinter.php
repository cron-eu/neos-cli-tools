<?php

namespace CRON\NeosCliTools\Utility;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Exception\NodeException;
use Neos\Flow\Cli\ConsoleOutput;

/**
 * @property int maxDepth
 * @property TraversableNodeInterface rootNode
 * @property ConsoleOutput consoleOutput
 */
class NeosDocumentTreePrinter
{
    /**
     * @Flow\Inject
     * @var NodeTypeConstraintFactory
     */
    protected $nodeTypeConstraintFactory;

    public function __construct(TraversableNodeInterface $node, $maxDepth = 0) {
        $this->maxDepth = $maxDepth;
        $this->rootNode = $node;
    }

    private function trimPath($input) {
        return str_replace($this->rootNode->findNodePath(), '', $input);
    }

    /**
     * @param TraversableNodeInterface $document
     * @param int $currentDepth
     *
     * @param array $currentURLPathPrefix
     *
     * @throws NodeException
     */
    private function printDocument(TraversableNodeInterface $document, int $currentDepth = 0, array $currentURLPathPrefix = [])
    {
        $urlPathPrefix = array_merge($currentURLPathPrefix, [$document->getProperty('uriPathSegment')]);
        $url = join('/', $urlPathPrefix);

        $this->consoleOutput->outputFormatted('%s "%s" {%s} [%s]', [
            str_replace('home', '', $url),
            $document->getProperty('title'),
            $document->getNodeType()->getName(),
            $this->trimPath($document->findNodePath()),
        ], $currentDepth * 0);

        if ($currentDepth < $this->maxDepth) {
            $childDocuments = $document->findChildNodes($this->nodeTypeConstraintFactory->parseFilterString('Neos.Neos:Document'));
            foreach ($childDocuments as $childDocument) {
                $this->printDocument($childDocument, $currentDepth + 1, $urlPathPrefix);
            }

        } // bail out if we're over the configured depth limit
    }

    private $documentTree = [];

    /**
     * @param TraversableNodeInterface $document
     * @param int $currentDepth
     *
     * @param array $currentURLPathPrefix
     *
     * @throws NodeException
     */
    private function buildDocumentTreeRecursive(TraversableNodeInterface $document, int $currentDepth = 0, array $currentURLPathPrefix = [])
    {
        $urlPathPrefix = array_merge($currentURLPathPrefix, [$document->getProperty('uriPathSegment')]);
        $url = join('/', $urlPathPrefix);

        $this->documentTree[] = [
            str_replace('home', '', $url),
            $document->getProperty('title'),
            $document->getNodeType()->getName(),
            $this->trimPath($document->findNodePath()),
        ];

        if ($currentDepth < $this->maxDepth) {
            $childDocuments = $document->findChildNodes($this->nodeTypeConstraintFactory->parseFilterString('Neos.Neos:Document'));
            foreach ($childDocuments as $childDocument) {
                $this->buildDocumentTreeRecursive($childDocument, $currentDepth + 1, $urlPathPrefix);
            }

        } // bail out if we're over the configured depth limit
    }

    /**
     * @param ConsoleOutput $output
     *
     * @param bool $asTable
     *
     * @throws NodeException
     */
    public function printTree(ConsoleOutput $output, bool $asTable = true)
    {
        $this->consoleOutput = $output;
        if ($asTable) {
            $this->documentTree = [];
            $this->buildDocumentTreeRecursive($this->rootNode);
            $this->consoleOutput->outputTable($this->documentTree, [
                'URL path',
                'Page Title',
                'Node Type',
                'Neos Node Path',
            ]);
        } else {
            $this->printDocument($this->rootNode);
        }
    }
}
