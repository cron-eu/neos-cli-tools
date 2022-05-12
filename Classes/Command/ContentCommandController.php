<?php

namespace CRON\NeosCliTools\Command;

use CRON\NeosCliTools\Service\CRService;
use Exception;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;

class ContentCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var CRService
     */
    protected $cr;

    /**
     * List the content of a specified page
     * @param string $url URL of the page, e.g. '/news'
     * @param string $collection collection node name, defaults to 'main'
     * @param string $workspace workspace to use, e.g. 'user-admin', defaults to 'live'
     */
    public function listCommand(string $url, string $collection = 'main', string $workspace = 'live') {

        try {
            $this->cr->setup($workspace);
            $page = $this->cr->getNodeForURL($url);
            $collectionNode = $page->findNamedChildNode(NodeName::fromString($collection));

            if ($collectionNode === null) {
                throw new Exception(sprintf('page has no collection node named "%s"', $collection));
            }

            foreach ($collectionNode->findChildNodes() as $childNode) {
                $this->outputLine($childNode);
            }

        } catch (Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
        }
    }

    /**
     * Creates a new content element
     *
     * @param string $url page URL of the page where the content element should be inserted
     * @param string|null $properties node properties, as JSON, e.g. '{"myAttribute":"My Fancy Value"}'
     * @param string $type node type, defaults to Neos.Neos.NodeTypes:Text
     * @param string $collection collection name, defaults to 'main'
     * @param string|null $name name of the node, leave empty to get a random uuid like name
     * @param string $workspace workspace to use, e.g. 'user-admin', defaults to 'live'
     * @param string|null $componentPath path of the component within the content collection where the nested content element should be inserted
     * @param boolean $overwriteExisting whether to overwrite an existing node for that path instead of creating a new one with path segment "node-..."
     */
    public function createCommand(
        string $url,
        string $properties = null,
        string $type = 'Neos.Neos.NodeTypes:Text',
        string $collection = 'main',
        string $name = null,
        string $workspace = 'live',
        string $componentPath = null,
        bool $overwriteExisting = false
    ) {
        try {
            $this->cr->setup($workspace);

            $nodeType = $this->cr->getNodeType($type);
            $page = $this->cr->getNodeForURL($url);

            // Check if the content collection exists. If not, create it
            $collectionNode = $page->findNamedChildNode(NodeName::fromString($collection));

            if (!$collectionNode) {
                $this->outputLine(sprintf('Could not find collection \'%s\', creating... .', $collection));
                $collectionNode = $page->createNode($collection, $this->cr->getNodeType('Neos.Neos:ContentCollection'));
            }

            // Follow the nested component path, creating component nodes that don't exist
            $segmentNode = $collectionNode;

            if ($componentPath) {
                $componentPathSegments = explode('/', trim($componentPath, '/'));

                foreach ($componentPathSegments as $componentPathSegment) {
                    $nextSegmentNode = $segmentNode->findNamedChildNode(NodeName::fromString($componentPathSegment));
                    if (!$nextSegmentNode) {
                        $nextSegmentNode = $segmentNode->createNode($componentPathSegment);
                    }
                    $segmentNode = $nextSegmentNode;
                }
            }

            $existingNode = $segmentNode->findNamedChildNode(NodeName::fromString($name));

            if ($overwriteExisting && $existingNode) {
                $contentNode = $existingNode;
                $this->outputLine(sprintf('%s already exists, updating properties... .', $contentNode));
            } else {
                $contentNode = $segmentNode->createNode($this->cr->generateUniqNodeName($segmentNode, $name), $nodeType);
                $this->outputLine(sprintf('%s created.', $contentNode));
            }

            if ($properties) {
                $this->cr->setNodeProperties($contentNode, $properties);
            }
        } catch (Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
        }
    }

    /**
     * Updates properties, name and hidden state of a content node
     *
     * @param string $identifier node identifier of the content element
     * @param string|null $properties node properties, as JSON, e.g. '{"title":"My Fancy Title"}'
     * @param string|null $name name of the node, will also update the URL path segment
     * @param bool|null $hide
     * @param string $workspace workspace to use, e.g. 'user-admin', defaults to 'live'
     * @throws StopCommandException
     */
    public function updateCommand(string $identifier, string $properties = null, string $name = null, bool $hide = null, string $workspace = 'live')
    {
        try {
            $this->cr->setup($workspace);
            $node = $this->cr->getNodeForIdentifier($identifier);
            if (!$node) {
                $this->outputLine('Unable to find node.');
                $this->quit(1);
            }

            if (!$node->getNodeType()->isOfType('Neos.Neos:Content')) {
                $this->outputLine('The found node is not a content element.');
                $this->quit(1);
            }

            if ($properties !== null) {
                $this->cr->setNodeProperties($node, $properties);

                $this->outputLine('Updated properties.');
            }

            if (!empty($name)) {
                $parentNode = $node->findParentNode();
                $nodeName = $this->cr->generateUniqNodeName($parentNode, $name);
                $node->setName($nodeName);

                $this->outputLine('Updated node name: "%s"', [$nodeName]);
            }

            if ($hide !== null) {
                $node->setHidden($hide);

                $this->outputLine('Hidden state set to %s.', [$hide ? 'true' : 'false']);
            }
        } catch (Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
            $this->quit(1);
        }
    }
}
