<?php

namespace CRON\NeosCliTools\Service;

use DateTime;
use Exception;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\NodeServiceInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\ImageInterface;
use Neos\Neos\Domain\Model\Site;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;

/**
 * Content Repository related logic
 *
 * @property string sitePath
 * @property string workspaceName
 * @property Site currentSite
 *
 * @Flow\Scope("singleton")
 */
class CRService
{
    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @Flow\Inject
     * @var WorkspaceRepository
     */
    protected $workspaceRepository;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NodeServiceInterface
     */
    protected $nodeService;

    /**
     * @var ContentContext
     */
    public $context;

    /** @var NodeInterface */
    public $rootNode;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * Setup and configure the context to use, take care of the arguments like user name etc.
     *
     * @param string $workspace workspace name, defaults to the live workspace
     *
     * @throws Exception
     */
    public function setup(string $workspace = 'live')
    {
        // validate username, use the live workspace if null
        $this->workspaceName = $workspace;

        /** @noinspection PhpUndefinedMethodInspection */
        if (!$this->workspaceRepository->findByName($this->workspaceName)->count()) {
            throw new Exception(sprintf('Workspace "%s" is invalid', $this->workspaceName));
        }

        $this->context = $this->contextFactory->create([
            'workspaceName' => $this->workspaceName,
            'currentSite' => $this->currentSite,
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);

        $this->rootNode = $this->context->getNode($this->sitePath);
    }

    /**
     * @param NodeInterface $document
     * @param $url
     *
     * @return string
     *
     * @throws Exception
     */
    public function getNodePathForURL(NodeInterface $document, $url): string
    {
        $parts = explode('/', $url);
        foreach ($parts as $segment) {
            if (!$segment) { continue; }
            $document = $this->getChildDocumentByURIPathSegment($document, $segment);
        }

        return $document->getPath();
    }

    /**
     * @param NodeInterface $document
     * @param $pathSegment
     *
     * @return NodeInterface
     * @throws Exception
     */
    private function getChildDocumentByURIPathSegment(NodeInterface $document, $pathSegment): NodeInterface
    {
        $found = array_filter($document->getChildNodes('Neos.Neos:Document'),
            function (NodeInterface $document) use ($pathSegment ){
                return $document->getProperty('uriPathSegment') === $pathSegment;
            }
        );

        if (count($found) === 0) {
            throw new Exception(sprintf('Could not find any child document for URL path segment: "%s" on "%s',
                $pathSegment,
                $document->getPath()
            ));
        }
        return array_pop($found);
    }

    /**
     * Fetches the associated NoteType object for the specified node type
     *
     * @param string $type NodeType name, e.g. 'YPO3.Neos.NodeTypes:Page'
     *
     * @return NodeType
     *
     * @throws Exception
     */
    public function getNodeType(string $type): NodeType
    {
        if (!$this->nodeTypeManager->hasNodeType($type)) {
            throw new Exception('specified node type is not valid');
        }

        return $this->nodeTypeManager->getNodeType($type);
    }

    /**
     * Sets the node properties
     *
     * @param NodeInterface $node
     * @param string $propertiesJSON JSON string of node properties
     *
     * @throws Exception
     */
    public function setNodeProperties(NodeInterface $node, string $propertiesJSON)
    {
        $data = json_decode($propertiesJSON, true);

        if ($data === null) {
            throw new Exception('could not decode JSON data');
        }

        foreach ($data as $name => $value) {
            $value = $this->propertyMapper($node, $name, $value);
            $node->setProperty($name, $value);
        }
    }

    /**
     * Fetches an existing node by URL
     *
     * @param string $url URL of the node, e.g. '/news/my-news'
     *
     * @return NodeInterface
     * @throws Exception
     */
    public function getNodeForURL(string $url): NodeInterface
    {
        return $this->context->getNode($this->getNodePathForURL($this->rootNode, $url));
    }

    /**
     * Fetches an existing node by relative path
     *
     * @param string $path relative path of the page
     *
     * @return NodeInterface
     * @throws Exception
     */
    public function getNodeForPath(string $path): NodeInterface
    {
        return $this->context->getNode($this->sitePath . $path);
    }

    /**
     * Publishes the configured workspace
     *
     * @throws Exception
     */
    public function publish()
    {
        $liveWorkspace = $this->workspaceRepository->findByIdentifier('live');
        if (!$liveWorkspace) {
            throw new Exception('Could not find the live workspace.');
        }
        $this->context->getWorkspace()->publish($liveWorkspace);
    }

    /**
     * @param NodeInterface $parentNode
     * @param string|null $idealNodeName
     *
     * @return string
     */
    public function generateUniqNodeName(NodeInterface $parentNode, string $idealNodeName = null): string
    {
        return $this->nodeService->generateUniqueNodeName($parentNode->getPath(), $idealNodeName);
    }

    /**
     * @throws Exception
     */
    public function initializeObject()
    {
        $currentSite = $this->siteRepository->findFirstOnline();
        if (!$currentSite) {
            throw new Exception('No site found');
        }
        $this->sitePath = '/sites/' . $currentSite->getNodeName();
        $this->currentSite = $currentSite;
    }

    /**
     * Map a String Value to the corresponding Neos Object
     *
     * @param $node NodeInterface
     * @param $propertyName string
     * @param $stringInput string
     *
     * @return mixed
     * @throws Exception
     */
    protected function propertyMapper(NodeInterface $node, string $propertyName, string $stringInput)
    {

        if ($stringInput === 'NULL') {
            return null;
        }

        switch ($node->getNodeType()->getConfiguration('properties.' . $propertyName . '.type')) {

            case 'references':
                $value = array_map(function ($path) { return $this->getNodeForPath($path); },
                    preg_split('/,\w*/', $stringInput));
                break;

            case 'reference':
                $value = $this->getNodeForPath($stringInput);
                break;

            case 'DateTime':
                $value = new DateTime($stringInput);
                break;

            case 'integer':
                $value = intval($stringInput);
                break;

            case 'boolean':
                $value = boolval($stringInput);
                break;

            case ImageInterface::class:
                $value = new Image($this->resourceManager->importResource($stringInput));
                break;

            default:
                $value = $stringInput;
                break;
        }

        return $value;
    }
}
